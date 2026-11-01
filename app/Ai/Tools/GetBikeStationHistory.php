<?php

namespace App\Ai\Tools;

use App\Models\BikeStation;
use App\Models\BikeStationSnapshot;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetBikeStationHistory implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Get the hourly bike availability trend of one Kaohsiung YouBike station over the past hours (up to '.BikeStationSnapshot::RETENTION_DAYS.' days). Use SearchBikeStations first if the station number (sno) is unknown.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'sno' => ['required', 'string', 'max:20'],
            'hours' => ['nullable', 'integer', 'min:1', 'max:'.BikeStationSnapshot::RETENTION_DAYS * 24],
        ]);

        $station = BikeStation::firstWhere('sno', $validated['sno']);

        if ($station === null) {
            return json_encode(['error' => "Station [{$validated['sno']}] does not exist."], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        $hourly = $station->snapshots()
            ->where('recorded_at', '>=', now()->subHours($validated['hours'] ?? 6))
            ->orderBy('recorded_at')
            ->get()
            ->groupBy(fn (BikeStationSnapshot $snapshot) => $snapshot->recorded_at
                ->setTimezone(BikeStation::LOCAL_TIMEZONE)
                ->format('Y-m-d H:00'))
            ->map(fn (Collection $snapshots, string $hour) => [
                'hour' => $hour,
                'average_rent_bikes' => round($snapshots->avg('available_rent_bikes'), 1),
                'min_rent_bikes' => $snapshots->min('available_rent_bikes'),
                'max_rent_bikes' => $snapshots->max('available_rent_bikes'),
                'average_return_bikes' => round($snapshots->avg('available_return_bikes'), 1),
                'samples' => $snapshots->count(),
            ])
            ->values();

        return json_encode([
            'station' => $station->toSummary(),
            'timezone' => BikeStation::LOCAL_TIMEZONE,
            'hourly' => $hourly->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'sno' => $schema->string()->description('The station number (sno), e.g. 501201001.')->required(),
            'hours' => $schema->integer()->min(1)->max(BikeStationSnapshot::RETENTION_DAYS * 24)->description('How many past hours to include. Defaults to 6.')->nullable(),
        ];
    }
}
