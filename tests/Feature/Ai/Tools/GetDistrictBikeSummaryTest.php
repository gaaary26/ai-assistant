<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\GetDistrictBikeSummary;
use App\Models\BikeStation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GetDistrictBikeSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_summarizes_availability_per_district(): void
    {
        BikeStation::factory()
            ->state([
                'district' => '苓雅區',
                'capacity' => 20,
                'available_rent_bikes' => 5,
                'available_electric_bikes' => 2,
                'available_return_bikes' => 15,
            ])
            ->create();
        BikeStation::factory()
            ->empty()
            ->state([
                'district' => '苓雅區',
                'capacity' => 10,
                'available_return_bikes' => 10,
            ])
            ->create();
        BikeStation::factory()
            ->inactive()
            ->empty()
            ->state([
                'district' => '苓雅區',
                'capacity' => 10,
                'available_return_bikes' => 10,
            ])
            ->create();
        BikeStation::factory()
            ->state(['district' => '新興區'])
            ->create();

        $result = $this->runTool(['district' => '苓雅']);

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
        $this->assertCount(2, $this->runTool()['districts']);
    }

    public function test_it_accepts_null_for_optional_arguments_since_some_models_send_them_explicitly(): void
    {
        BikeStation::factory()->create();

        $this->assertCount(1, $this->runTool(['district' => null])['districts']);
    }

    /**
     * Run the tool with the given arguments and decode its JSON result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function runTool(array $arguments = []): array
    {
        return json_decode((string) (new GetDistrictBikeSummary)->handle(new Request($arguments)), true);
    }
}
