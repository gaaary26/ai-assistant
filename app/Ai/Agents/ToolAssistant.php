<?php

namespace App\Ai\Agents;

use App\Ai\Tools\FindNearbyBikeStations;
use App\Ai\Tools\GetBikeStationHistory;
use App\Ai\Tools\GetCurrentTime;
use App\Ai\Tools\GetDistrictBikeSummary;
use App\Ai\Tools\SearchBikeStations;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Promptable;
use Laravel\Ai\Providers\Tools\ProviderTool;
use Stringable;

#[MaxSteps(8)]
#[Timeout(180)]
class ToolAssistant implements Agent, Conversational, HasTools
{
    use Promptable;

    /**
     * @param  list<array{role: string, content: string}>  $history
     */
    public function __construct(public array $history = []) {}

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
            You are a Kaohsiung YouBike assistant.
            The tools read station data that is synced from Kaohsiung City open data every few minutes.
            Always use the tools to look up stations, availability and trends instead of guessing, and mention how fresh the data is.
            To find stations near a place, search for a station there first and use its coordinates with FindNearbyBikeStations.
            When the user refers to relative times, call GetCurrentTime first to resolve them.
            Reply in the same language the user writes in, and keep answers concise.
            TEXT;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     *
     * @return Message[]
     */
    public function messages(): iterable
    {
        return array_map(
            fn (array $message) => new Message($message['role'], $message['content']),
            $this->history,
        );
    }

    /**
     * Get the tools available to the agent.
     *
     * @return list<Agent|Tool|ProviderTool>
     */
    public function tools(): iterable
    {
        return [
            new GetCurrentTime,
            new SearchBikeStations,
            new FindNearbyBikeStations,
            new GetBikeStationHistory,
            new GetDistrictBikeSummary,
        ];
    }
}
