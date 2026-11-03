<?php

namespace App\Jobs;

use App\Models\BikeStation;
use App\Models\BikeStationSnapshot;
use App\Services\KaohsiungYouBikeClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SyncKaohsiungBikeStations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var list<int>
     */
    public array $backoff = [30, 60];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 80;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 600;

    /**
     * How many rows to write per insert statement.
     */
    private const CHUNK_SIZE = 500;

    /**
     * Fetch the latest station availability, update the stations and record a snapshot of each one.
     */
    public function handle(KaohsiungYouBikeClient $client): void
    {
        $stations = $client->fetchStations();
        $recordedAt = now();

        DB::transaction(function () use ($stations, $recordedAt) {
            foreach (collect($stations)->chunk(self::CHUNK_SIZE) as $chunk) {
                BikeStation::upsert($chunk->values()->all(), ['sno'], [
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
                ]);
            }

            $stationIds = BikeStation::pluck('id', 'sno');

            $snapshots = collect($stations)->map(fn (array $station) => [
                'bike_station_id' => $stationIds[$station['sno']],
                'available_rent_bikes' => $station['available_rent_bikes'],
                'available_electric_bikes' => $station['available_electric_bikes'],
                'available_return_bikes' => $station['available_return_bikes'],
                'recorded_at' => $recordedAt,
            ]);

            foreach ($snapshots->chunk(self::CHUNK_SIZE) as $chunk) {
                BikeStationSnapshot::insert($chunk->values()->all());
            }
        });
    }
}
