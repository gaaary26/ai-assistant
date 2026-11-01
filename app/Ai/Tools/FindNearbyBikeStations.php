<?php

namespace App\Ai\Tools;

use App\Models\BikeStation;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FindNearbyBikeStations implements Tool
{
    /**
     * Mean Earth radius in meters, used for the haversine distance.
     */
    private const EARTH_RADIUS_METERS = 6371000;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Find the Kaohsiung YouBike stations closest to a coordinate, sorted by distance. Use SearchBikeStations first if you only know a place name, then use that station\'s coordinates.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['nullable', 'integer', 'min:50', 'max:3000'],
            'availability' => ['nullable', 'in:any,rentable,returnable'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $latitude = (float) $validated['latitude'];
        $longitude = (float) $validated['longitude'];
        $radius = $validated['radius_meters'] ?? 500;

        $latitudeDelta = rad2deg($radius / self::EARTH_RADIUS_METERS);
        $longitudeDelta = $latitudeDelta / max(cos(deg2rad($latitude)), 0.01);

        $stations = BikeStation::query()
            ->whereBetween('latitude', [$latitude - $latitudeDelta, $latitude + $latitudeDelta])
            ->whereBetween('longitude', [$longitude - $longitudeDelta, $longitude + $longitudeDelta])
            ->withAvailability($validated['availability'] ?? null)
            ->get()
            ->map(fn (BikeStation $station) => [
                ...$station->toSummary(),
                'distance_meters' => (int) round($this->distanceInMeters($latitude, $longitude, $station->latitude, $station->longitude)),
            ])
            ->filter(fn (array $station) => $station['distance_meters'] <= $radius)
            ->sortBy('distance_meters')
            ->take($validated['limit'] ?? 5)
            ->values();

        return json_encode(['stations' => $stations->all()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'latitude' => $schema->number()->description('Latitude of the origin point.')->required(),
            'longitude' => $schema->number()->description('Longitude of the origin point.')->required(),
            'radius_meters' => $schema->integer()->min(50)->max(3000)->description('Search radius in meters. Defaults to 500.')->nullable(),
            'availability' => $schema->string()->enum(['any', 'rentable', 'returnable', null])->description('Only return stations with bikes to rent or free docks. Defaults to any.')->nullable(),
            'limit' => $schema->integer()->min(1)->max(20)->description('Maximum number of stations to return. Defaults to 5.')->nullable(),
        ];
    }

    /**
     * Calculate the great-circle distance between two coordinates.
     */
    private function distanceInMeters(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);

        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($fromLatitude)) * cos(deg2rad($toLatitude)) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
