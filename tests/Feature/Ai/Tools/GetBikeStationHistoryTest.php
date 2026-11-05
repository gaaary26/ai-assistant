<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\GetBikeStationHistory;
use App\Models\BikeStation;
use App\Models\BikeStationSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetBikeStationHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_the_hourly_history_of_a_station(): void
    {
        $this->travelTo('2026-10-31 13:55:00');
        $station = BikeStation::factory()->create();
        BikeStationSnapshot::factory()
            ->for($station)
            ->state([
                'available_rent_bikes' => 2,
                'recorded_at' => '2026-10-31 13:10:00',
            ])
            ->create();
        BikeStationSnapshot::factory()
            ->for($station)
            ->state([
                'available_rent_bikes' => 6,
                'recorded_at' => '2026-10-31 13:40:00',
            ])
            ->create();
        BikeStationSnapshot::factory()
            ->for($station)
            ->state([
                'available_rent_bikes' => 9,
                'recorded_at' => '2026-10-31 12:20:00',
            ])
            ->create();
        BikeStationSnapshot::factory()
            ->for($station)
            ->state(['recorded_at' => '2026-10-31 05:00:00'])
            ->create();

        $result = $this->runTool(['sno' => $station->sno, 'hours' => 3]);

        $this->assertSame($station->sno, $result['station']['sno']);
        $this->assertCount(2, $result['hourly']);
        $this->assertSame('2026-10-31 12:00', $result['hourly'][0]['hour']);
        $this->assertEquals(9, $result['hourly'][0]['average_rent_bikes']);
        $this->assertSame(1, $result['hourly'][0]['samples']);
        $this->assertSame('2026-10-31 13:00', $result['hourly'][1]['hour']);
        $this->assertEquals(4, $result['hourly'][1]['average_rent_bikes']);
        $this->assertSame(2, $result['hourly'][1]['min_rent_bikes']);
        $this->assertSame(6, $result['hourly'][1]['max_rent_bikes']);
        $this->assertSame(2, $result['hourly'][1]['samples']);
    }

    public function test_it_reports_a_missing_station_when_reading_history(): void
    {
        $this->assertSame(
            ['error' => 'Station [999] does not exist.'],
            $this->runTool(['sno' => '999']),
        );
    }

    public function test_it_accepts_null_for_optional_arguments_since_some_models_send_them_explicitly(): void
    {
        $station = BikeStation::factory()->create();

        $this->assertArrayHasKey('hourly', $this->runTool(['sno' => $station->sno, 'hours' => null]));
    }

    /**
     * Run the tool with the given arguments and decode its JSON result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(array $arguments = []): array
    {
        return json_decode((string) (new GetBikeStationHistory)->handle(new Request($arguments)), true);
    }
}
