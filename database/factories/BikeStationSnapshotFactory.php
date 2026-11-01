<?php

namespace Database\Factories;

use App\Models\BikeStation;
use App\Models\BikeStationSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BikeStationSnapshot>
 */
class BikeStationSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rentBikes = fake()->numberBetween(0, 20);

        return [
            'bike_station_id' => BikeStation::factory(),
            'available_rent_bikes' => $rentBikes,
            'available_electric_bikes' => fake()->numberBetween(0, $rentBikes),
            'available_return_bikes' => fake()->numberBetween(0, 20),
            'recorded_at' => now(),
        ];
    }
}
