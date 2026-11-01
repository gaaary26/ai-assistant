<?php

namespace App\Models;

use Database\Factories\BikeStationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sno
 * @property string $name
 * @property string $district
 * @property string $address
 * @property float $latitude
 * @property float $longitude
 * @property int $capacity
 * @property bool $is_active
 * @property int $available_rent_bikes
 * @property int|null $available_electric_bikes
 * @property int $available_return_bikes
 * @property Carbon|null $source_updated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'sno',
    'name',
    'district',
    'address',
    'latitude',
    'longitude',
    'capacity',
    'is_active',
    'available_rent_bikes',
    'available_electric_bikes',
    'available_return_bikes',
    'source_updated_at',
])]
class BikeStation extends Model
{
    /** @use HasFactory<BikeStationFactory> */
    use HasFactory;

    /**
     * The timezone used by the data source and when presenting times to the assistant.
     */
    public const LOCAL_TIMEZONE = 'Asia/Taipei';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'capacity' => 'integer',
            'is_active' => 'boolean',
            'available_rent_bikes' => 'integer',
            'available_electric_bikes' => 'integer',
            'available_return_bikes' => 'integer',
            'source_updated_at' => 'datetime',
        ];
    }

    /**
     * Get the availability snapshots recorded for the station.
     *
     * @return HasMany<BikeStationSnapshot, $this>
     */
    public function snapshots(): HasMany
    {
        return $this->hasMany(BikeStationSnapshot::class);
    }

    /**
     * Scope the query to active stations that have at least one bike to rent.
     *
     * @param  Builder<BikeStation>  $query
     */
    #[Scope]
    protected function rentable(Builder $query): void
    {
        $query->where('is_active', true)->where('available_rent_bikes', '>', 0);
    }

    /**
     * Scope the query to active stations that have at least one free dock.
     *
     * @param  Builder<BikeStation>  $query
     */
    #[Scope]
    protected function returnable(Builder $query): void
    {
        $query->where('is_active', true)->where('available_return_bikes', '>', 0);
    }

    /**
     * Scope the query by the availability filter used by the AI tools.
     *
     * @param  Builder<BikeStation>  $query
     */
    #[Scope]
    protected function withAvailability(Builder $query, ?string $availability): void
    {
        $query
            ->when($availability === 'rentable', fn (Builder $query) => $query->rentable())
            ->when($availability === 'returnable', fn (Builder $query) => $query->returnable());
    }

    /**
     * Get the most recent time the data source reported for any station.
     */
    public static function latestSourceUpdate(): ?Carbon
    {
        $latest = static::max('source_updated_at');

        return $latest === null ? null : Carbon::parse($latest);
    }

    /**
     * Get a compact representation of the station for AI tools.
     *
     * @return array{sno: string, name: string, district: string, address: string, latitude: float, longitude: float, capacity: int, is_active: bool, available_rent_bikes: int, available_electric_bikes: int|null, available_return_bikes: int, source_updated_at: string|null}
     */
    public function toSummary(): array
    {
        return [
            'sno' => $this->sno,
            'name' => $this->name,
            'district' => $this->district,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'capacity' => $this->capacity,
            'is_active' => $this->is_active,
            'available_rent_bikes' => $this->available_rent_bikes,
            'available_electric_bikes' => $this->available_electric_bikes,
            'available_return_bikes' => $this->available_return_bikes,
            'source_updated_at' => $this->source_updated_at?->setTimezone(self::LOCAL_TIMEZONE)->toIso8601String(),
        ];
    }
}
