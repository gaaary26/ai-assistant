<?php

namespace App\Ai\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetCurrentTime implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the current date, time and weekday in a given IANA timezone. Use this whenever the user mentions relative dates such as "today", "tomorrow" or "next Friday".';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'timezone' => ['nullable', 'timezone:all'],
        ]);

        $now = now($validated['timezone'] ?? config('app.timezone'));

        return json_encode([
            'datetime' => $now->toDateTimeString(),
            'weekday' => $now->englishDayOfWeek,
            'timezone' => $now->timezoneName,
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'timezone' => $schema->string()->description('IANA timezone, e.g. Asia/Taipei. Defaults to the application timezone.')->nullable(),
        ];
    }
}
