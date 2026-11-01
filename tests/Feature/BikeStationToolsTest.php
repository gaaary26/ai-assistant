<?php

namespace Tests\Feature;

use App\Ai\Tools\FindNearbyBikeStations;
use App\Ai\Tools\GetBikeStationHistory;
use App\Ai\Tools\GetDistrictBikeSummary;
use App\Ai\Tools\SearchBikeStations;
use App\Models\BikeStation;
use App\Models\BikeStationSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class BikeStationToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_searches_stations_by_keyword_and_district(): void
    {
        $formosa = BikeStation::factory()->create(['name' => 'YouBike2.0_捷運美麗島站', 'district' => '新興區']);
        BikeStation::factory()->create(['name' => 'YouBike2.0_駁二大義', 'district' => '鹽埕區', 'address' => '大義街2號']);

        $this->assertSame([$formosa->sno], $this->stationNumbers(new SearchBikeStations, ['keyword' => '美麗島']));
        $this->assertCount(1, $this->runTool(new SearchBikeStations, ['keyword' => '大義街'])['stations']);
        $this->assertSame([$formosa->sno], $this->stationNumbers(new SearchBikeStations, ['district' => '新興']));
    }

    public function test_it_filters_stations_by_availability(): void
    {
        $rentable = BikeStation::factory()->create(['available_return_bikes' => 0]);
        $empty = BikeStation::factory()->empty()->create();
        BikeStation::factory()->inactive()->create();

        $this->assertSame([$rentable->sno], $this->stationNumbers(new SearchBikeStations, ['availability' => 'rentable']));
        $this->assertSame([$empty->sno], $this->stationNumbers(new SearchBikeStations, ['availability' => 'returnable']));
    }

    public function test_it_limits_the_number_of_stations_returned(): void
    {
        BikeStation::factory()->count(5)->create();

        $this->assertCount(2, $this->runTool(new SearchBikeStations, ['limit' => 2])['stations']);

        $this->expectException(ValidationException::class);

        $this->runTool(new SearchBikeStations, ['limit' => 100]);
    }

    public function test_it_finds_nearby_stations_sorted_by_distance(): void
    {
        $nearest = BikeStation::factory()->create(['latitude' => 22.6310, 'longitude' => 120.3020]);
        $further = BikeStation::factory()->create(['latitude' => 22.6330, 'longitude' => 120.3020]);
        BikeStation::factory()->create(['latitude' => 22.7000, 'longitude' => 120.3020]);

        $stations = $this->runTool(new FindNearbyBikeStations, [
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

        $this->runTool(new FindNearbyBikeStations, ['latitude' => 22.63]);
    }

    public function test_it_summarizes_the_hourly_history_of_a_station(): void
    {
        Carbon::setTestNow('2026-10-31 13:55:00');
        $station = BikeStation::factory()->create();
        BikeStationSnapshot::factory()->for($station)->create(['available_rent_bikes' => 2, 'recorded_at' => '2026-10-31 13:10:00']);
        BikeStationSnapshot::factory()->for($station)->create(['available_rent_bikes' => 6, 'recorded_at' => '2026-10-31 13:40:00']);
        BikeStationSnapshot::factory()->for($station)->create(['available_rent_bikes' => 9, 'recorded_at' => '2026-10-31 12:20:00']);
        BikeStationSnapshot::factory()->for($station)->create(['recorded_at' => '2026-10-31 05:00:00']);

        $result = $this->runTool(new GetBikeStationHistory, ['sno' => $station->sno, 'hours' => 3]);

        $this->assertSame($station->sno, $result['station']['sno']);
        $this->assertCount(2, $result['hourly']);
        $this->assertSame('2026-10-31 20:00', $result['hourly'][0]['hour']);
        $this->assertEquals(9, $result['hourly'][0]['average_rent_bikes']);
        $this->assertSame(1, $result['hourly'][0]['samples']);
        $this->assertSame('2026-10-31 21:00', $result['hourly'][1]['hour']);
        $this->assertEquals(4, $result['hourly'][1]['average_rent_bikes']);
        $this->assertSame(2, $result['hourly'][1]['min_rent_bikes']);
        $this->assertSame(6, $result['hourly'][1]['max_rent_bikes']);
        $this->assertSame(2, $result['hourly'][1]['samples']);
    }

    public function test_it_reports_a_missing_station_when_reading_history(): void
    {
        $this->assertSame(
            ['error' => 'Station [999] does not exist.'],
            $this->runTool(new GetBikeStationHistory, ['sno' => '999']),
        );
    }

    public function test_it_summarizes_availability_per_district(): void
    {
        BikeStation::factory()->create(['district' => '苓雅區', 'capacity' => 20, 'available_rent_bikes' => 5, 'available_electric_bikes' => 2, 'available_return_bikes' => 15]);
        BikeStation::factory()->empty()->create(['district' => '苓雅區', 'capacity' => 10, 'available_return_bikes' => 10]);
        BikeStation::factory()->inactive()->empty()->create(['district' => '苓雅區', 'capacity' => 10, 'available_return_bikes' => 10]);
        BikeStation::factory()->create(['district' => '新興區']);

        $result = $this->runTool(new GetDistrictBikeSummary, ['district' => '苓雅']);

        $this->assertSame([[
            'district' => '苓雅區',
            'station_count' => 3,
            'active_station_count' => 2,
            'total_capacity' => 40,
            'available_rent_bikes' => 5,
            'available_electric_bikes' => 2,
            'available_return_bikes' => 35,
            'empty_station_count' => 1,
        ]], $result['districts']);
        $this->assertNotNull($result['data_updated_at']);
        $this->assertCount(2, $this->runTool(new GetDistrictBikeSummary)['districts']);
    }

    public function test_it_accepts_null_for_optional_arguments_since_some_models_send_them_explicitly(): void
    {
        $station = BikeStation::factory()->create(['latitude' => 22.6300, 'longitude' => 120.3020]);

        $this->assertCount(1, $this->runTool(new SearchBikeStations, ['keyword' => null, 'district' => null, 'availability' => null, 'limit' => null])['stations']);
        $this->assertCount(1, $this->runTool(new FindNearbyBikeStations, ['latitude' => 22.63, 'longitude' => 120.302, 'radius_meters' => null, 'availability' => null, 'limit' => null])['stations']);
        $this->assertArrayHasKey('hourly', $this->runTool(new GetBikeStationHistory, ['sno' => $station->sno, 'hours' => null]));
        $this->assertCount(1, $this->runTool(new GetDistrictBikeSummary, ['district' => null])['districts']);
    }

    /**
     * Run a tool with the given arguments and decode its JSON result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(Tool $tool, array $arguments = []): array
    {
        return json_decode((string) $tool->handle(new Request($arguments)), true);
    }

    /**
     * Run a tool and get the station numbers it returned.
     *
     * @param  array<string, mixed>  $arguments
     * @return list<string>
     */
    private function stationNumbers(Tool $tool, array $arguments = []): array
    {
        return array_column($this->runTool($tool, $arguments)['stations'], 'sno');
    }
}
