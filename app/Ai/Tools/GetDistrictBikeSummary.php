<?php

namespace App\Ai\Tools;

use App\Models\BikeStation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetDistrictBikeSummary implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Summarize Kaohsiung YouBike availability per district: station counts, total bikes to rent, electric bikes, free docks and empty stations.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'district' => ['nullable', 'string', 'max:20'],
        ]);

        $district = $validated['district'] ?? null;

        $districts = BikeStation::query()
            ->when(filled($district), fn ($query) => $query->where('district', 'like', "%{$district}%"))
            ->selectRaw('district')
            ->selectRaw('count(*) as station_count')
            ->selectRaw('sum(case when is_active then 1 else 0 end) as active_station_count')
            ->selectRaw('sum(capacity) as total_capacity')
            ->selectRaw('sum(available_rent_bikes) as available_rent_bikes')
            ->selectRaw('sum(coalesce(available_electric_bikes, 0)) as available_electric_bikes')
            ->selectRaw('sum(available_return_bikes) as available_return_bikes')
            ->selectRaw('sum(case when is_active and available_rent_bikes = 0 then 1 else 0 end) as empty_station_count')
            ->groupBy('district')
            ->orderByDesc('available_rent_bikes')
            ->toBase()
            ->get()
            ->map(fn (object $row) => [
                'district' => $row->district,
                'station_count' => (int) $row->station_count,
                'active_station_count' => (int) $row->active_station_count,
                'total_capacity' => (int) $row->total_capacity,
                'available_rent_bikes' => (int) $row->available_rent_bikes,
                'available_electric_bikes' => (int) $row->available_electric_bikes,
                'available_return_bikes' => (int) $row->available_return_bikes,
                'empty_station_count' => (int) $row->empty_station_count,
            ]);

        return json_encode([
            'data_updated_at' => BikeStation::latestSourceUpdate()?->setTimezone(BikeStation::LOCAL_TIMEZONE)->toIso8601String(),
            'districts' => $districts->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'district' => $schema->string()->description('Only summarize districts matching this name, e.g. 苓雅區. Omit for all districts.')->nullable(),
        ];
    }
}
