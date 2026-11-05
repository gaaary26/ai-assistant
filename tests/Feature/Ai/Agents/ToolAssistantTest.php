<?php

namespace Tests\Feature\Ai\Agents;

use App\Ai\Agents\ToolAssistant;
use App\Ai\Tools\FindNearbyBikeStations;
use App\Ai\Tools\GetBikeStationHistory;
use App\Ai\Tools\GetCurrentTime;
use App\Ai\Tools\GetDistrictBikeSummary;
use App\Ai\Tools\SearchBikeStations;
use Tests\TestCase;

class ToolAssistantTest extends TestCase
{
    public function test_it_passes_the_chat_history_to_the_agent_as_messages(): void
    {
        $agent = new ToolAssistant([
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello'],
        ]);

        $messages = collect($agent->messages());

        $this->assertCount(2, $messages);
        $this->assertSame(['Hi', 'Hello'], $messages->pluck('content')->all());
        $this->assertSame(
            [GetCurrentTime::class, SearchBikeStations::class, FindNearbyBikeStations::class, GetBikeStationHistory::class, GetDistrictBikeSummary::class],
            collect($agent->tools())
                ->map(fn ($tool) => $tool::class)
                ->all(),
        );
    }
}
