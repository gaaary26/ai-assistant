<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\SearchBikeStations;
use App\Models\BikeStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class SearchBikeStationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_searches_stations_by_keyword_and_district(): void
    {
        $formosa = BikeStation::factory()
            ->state([
                'name' => 'YouBike2.0_捷運美麗島站',
                'district' => '新興區',
            ])
            ->create();

        BikeStation::factory()
            ->state([
                'name' => 'YouBike2.0_駁二大義',
                'district' => '鹽埕區',
                'address' => '大義街2號',
            ])
            ->create();

        $this->assertSame([$formosa->sno], $this->stationNumbers(['keyword' => '美麗島']));
        $this->assertCount(1, $this->runTool(['keyword' => '大義街'])['stations']);
        $this->assertSame([$formosa->sno], $this->stationNumbers(['district' => '新興']));
    }

    public function test_it_filters_stations_by_availability(): void
    {
        $rentable = BikeStation::factory()
            ->state(['available_return_bikes' => 0])
            ->create();
        $empty = BikeStation::factory()
            ->empty()
            ->create();
        BikeStation::factory()
            ->inactive()
            ->create();

        $this->assertSame([$rentable->sno], $this->stationNumbers(['availability' => 'rentable']));
        $this->assertSame([$empty->sno], $this->stationNumbers(['availability' => 'returnable']));
    }

    public function test_it_limits_the_number_of_stations_returned(): void
    {
        BikeStation::factory()
            ->count(5)
            ->create();

        $this->assertCount(2, $this->runTool(['limit' => 2])['stations']);

        $this->expectException(ValidationException::class);

        $this->runTool(['limit' => 100]);
    }

    public function test_it_accepts_null_for_optional_arguments_since_some_models_send_them_explicitly(): void
    {
        BikeStation::factory()->create();

        $this->assertCount(1, $this->runTool(['keyword' => null, 'district' => null, 'availability' => null, 'limit' => null])['stations']);
    }

    /**
     * Run the tool with the given arguments and decode its JSON result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(array $arguments = []): array
    {
        return json_decode((string) (new SearchBikeStations)->handle(new Request($arguments)), true);
    }

    /**
     * Run the tool and get the station numbers it returned.
     *
     * @param  array<string, mixed>  $arguments
     * @return list<string>
     */
    private function stationNumbers(array $arguments = []): array
    {
        return array_column($this->runTool($arguments)['stations'], 'sno');
    }
}
