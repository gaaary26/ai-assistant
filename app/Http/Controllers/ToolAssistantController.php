<?php

namespace App\Http\Controllers;

use App\Ai\Agents\ToolAssistant;
use App\Http\Requests\ToolChatRequest;
use App\Models\BikeStation;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Ai;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Responses\Data\ToolResult;

class ToolAssistantController extends Controller
{
    /**
     * Show the tool-calling assistant page.
     */
    public function index(): Response
    {
        return Inertia::render('ToolAssistant', [
            'stationStats' => $this->stationStats(),
            'providers' => array_map(fn (Lab $provider) => $provider->value, ToolChatRequest::SUPPORTED_PROVIDERS),
        ]);
    }

    /**
     * Send a message to the assistant using the visitor's own API key and return its reply with the tools it used.
     */
    public function chat(ToolChatRequest $request): JsonResponse
    {
        set_time_limit(120);

        try {
            $response = (new ToolAssistant($request->validated('history')))->prompt(
                $request->validated('message'),
                provider: [Ai::build([
                    'driver' => $request->validated('provider'),
                    'key' => $request->validated('api_key'),
                ])],
            );
        } catch (AiException|RequestException $exception) {
            Log::warning('AI provider rejected the chat request.', [
                'provider' => $request->validated('provider'),
                'error' => $exception->getMessage(),
                'cause' => $exception->getPrevious()?->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'message' => 'AI 供應商無法處理這次請求，請稍後再試或確認 API key。',
            ]);
        }

        return response()->json([
            'reply' => $response->text,
            'tool_calls' => $response->toolResults->map(fn (ToolResult $result) => [
                'name' => $result->name,
                'arguments' => $result->arguments,
                'result' => $result->text(),
                'successful' => $result->successful(),
            ])->all(),
            'stationStats' => $this->stationStats(),
        ]);
    }

    /**
     * Get an overview of the synced station data for display.
     *
     * @return array{station_count: int, data_updated_at: string|null}
     */
    private function stationStats(): array
    {
        return [
            'station_count' => BikeStation::count(),
            'data_updated_at' => BikeStation::latestSourceUpdate()?->setTimezone(BikeStation::LOCAL_TIMEZONE)->toDateTimeString(),
        ];
    }
}
