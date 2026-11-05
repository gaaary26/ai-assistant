<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\FindNearbyBikeStations;
use App\Models\BikeStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class FindNearbyBikeStationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_nearby_stations_sorted_by_distance(): void
    {
        $nearest = BikeStation::factory()
            ->state([
                'latitude' => 22.6310,
                'longitude' => 120.3020,
            ])
            ->create();
        $further = BikeStation::factory()
            ->state([
                'latitude' => 22.6330,
                'longitude' => 120.3020,
            ])
            ->create();
        BikeStation::factory()
            ->state([
                'latitude' => 22.7000,
                'longitude' => 120.3020,
            ])
            ->create();

        $stations = $this->runTool([
            'latitude' => 22.6300,
            'longitude' => 120.3020,
            'radius_meters' => 500,
        ])['stations'];

        $this->assertSame([$nearest->sno, $further->sno], array_column($stations, 'sno'));
        $this->assertSame(111, $stations[0]['distance_meters']);
        $this->assertSame(334, $stations[1]['distance_meters']);
    }

    public function test_it_requires_coordinates_to_find_nearby_stations(): void
    {
        $this->expectException(ValidationException::class);

        $this->runTool(['latitude' => 22.63]);
    }

    public function test_it_accepts_null_for_optional_arguments_since_some_models_send_them_explicitly(): void
    {
        BikeStation::factory()
            ->state([
                'latitude' => 22.6300,
                'longitude' => 120.3020,
            ])
            ->create();

        $this->assertCount(1, $this->runTool(['latitude' => 22.63, 'longitude' => 120.302, 'radius_meters' => null, 'availability' => null, 'limit' => null])['stations']);
    }

    /**
     * Run the tool with the given arguments and decode its JSON result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(array $arguments = []): array
    {
        return json_decode((string) (new FindNearbyBikeStations)->handle(new Request($arguments)), true);
    }
}
