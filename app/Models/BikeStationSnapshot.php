<?php

namespace App\Models;

use Database\Factories\BikeStationSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bike_station_id
 * @property int $available_rent_bikes
 * @property int|null $available_electric_bikes
 * @property int $available_return_bikes
 * @property Carbon $recorded_at
 */
#[Fillable(['bike_station_id', 'available_rent_bikes', 'available_electric_bikes', 'available_return_bikes', 'recorded_at'])]
class BikeStationSnapshot extends Model
{
    /** @use HasFactory<BikeStationSnapshotFactory> */
    use HasFactory, MassPrunable;

    /**
     * How many days of snapshots to keep.
     */
    public const RETENTION_DAYS = 7;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_rent_bikes' => 'integer',
            'available_electric_bikes' => 'integer',
            'available_return_bikes' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /**
     * Get the station the snapshot belongs to.
     *
     * @return BelongsTo<BikeStation, $this>
     */
    public function bikeStation(): BelongsTo
    {
        return $this->belongsTo(BikeStation::class);
    }

    /**
     * Get the query for snapshots older than the retention window.
     *
     * @return Builder<BikeStationSnapshot>
     */
    public function prunable(): Builder
    {
        return static::where('recorded_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}
