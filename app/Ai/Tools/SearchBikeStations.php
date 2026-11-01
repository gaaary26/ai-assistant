<?php

namespace App\Ai\Tools;

use App\Models\BikeStation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchBikeStations implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search Kaohsiung YouBike stations by name/address keyword and/or district, returning the latest bike and dock availability.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:20'],
            'availability' => ['nullable', 'in:any,rentable,returnable'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $keyword = $validated['keyword'] ?? null;
        $district = $validated['district'] ?? null;

        $stations = BikeStation::query()
            ->when(filled($keyword), fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$keyword}%")
                ->orWhere('address', 'like', "%{$keyword}%")))
            ->when(filled($district), fn ($query) => $query->where('district', 'like', "%{$district}%"))
            ->withAvailability($validated['availability'] ?? null)
            ->orderByDesc('available_rent_bikes')
            ->limit($validated['limit'] ?? 10)
            ->get();

        return json_encode([
            'stations' => $stations->map(fn (BikeStation $station) => $station->toSummary())->all(),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'keyword' => $schema->string()->description('Part of the station name or address, e.g. 美麗島 or 中山一路.')->nullable(),
            'district' => $schema->string()->description('Kaohsiung district name, e.g. 新興區 or 左營.')->nullable(),
            'availability' => $schema->string()->enum(['any', 'rentable', 'returnable', null])->description('Only return stations with bikes to rent or free docks. Defaults to any.')->nullable(),
            'limit' => $schema->integer()->min(1)->max(20)->description('Maximum number of stations to return. Defaults to 10.')->nullable(),
        ];
    }
}
