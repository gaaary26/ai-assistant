<?php

namespace Database\Factories;

use App\Models\BikeStation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BikeStation>
 */
class BikeStationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $capacity = fake()->numberBetween(10, 40);
        $rentBikes = fake()->numberBetween(1, $capacity - 1);

        return [
            'sno' => (string) fake()->unique()->numberBetween(501200000, 501299999),
            'name' => 'YouBike2.0_'.fake()->streetName(),
            'district' => fake()->randomElement(['新興區', '前金區', '苓雅區', '鼓山區', '左營區']),
            'address' => fake()->streetAddress(),
            'latitude' => fake()->latitude(22.55, 22.75),
            'longitude' => fake()->longitude(120.25, 120.40),
            'capacity' => $capacity,
            'is_active' => true,
            'available_rent_bikes' => $rentBikes,
            'available_electric_bikes' => fake()->numberBetween(0, $rentBikes),
            'available_return_bikes' => $capacity - $rentBikes,
            'source_updated_at' => now(),
        ];
    }

    /**
     * Indicate that the station has no bikes to rent.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_rent_bikes' => 0,
            'available_electric_bikes' => 0,
            'available_return_bikes' => $attributes['capacity'],
        ]);
    }

    /**
     * Indicate that the station is out of service.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
