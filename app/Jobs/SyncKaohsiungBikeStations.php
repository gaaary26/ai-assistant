<?php

namespace App\Jobs;

use App\Models\BikeStation;
use App\Models\BikeStationSnapshot;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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
     * Fetch the latest station availability, update the stations and record a snapshot of each one.
     */
    public function handle(): void
    {
        $stations = $this->fetchStations();
        $recordedAt = now();

        DB::transaction(function () use ($stations, $recordedAt) {
            foreach ($stations->chunk(500) as $chunk) {
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

            $snapshots = $stations->map(fn (array $station) => [
                'bike_station_id' => $stationIds[$station['sno']],
                'available_rent_bikes' => $station['available_rent_bikes'],
                'available_electric_bikes' => $station['available_electric_bikes'],
                'available_return_bikes' => $station['available_return_bikes'],
                'recorded_at' => $recordedAt,
            ]);

            foreach ($snapshots->chunk(500) as $chunk) {
                BikeStationSnapshot::insert($chunk->values()->all());
            }
        });
    }

    /**
     * Fetch the real-time availability of every Kaohsiung YouBike station.
     *
     * @return Collection<int, array{sno: string, name: string, district: string, address: string, latitude: float, longitude: float, capacity: int, is_active: bool, available_rent_bikes: int, available_electric_bikes: int|null, available_return_bikes: int, source_updated_at: Carbon|null}>
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException
     */
    private function fetchStations(): Collection
    {
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->retry(3, 1000)
            ->get(config('services.kaohsiung_youbike.url'))
            ->throw();

        if ($response->json('data.data.retCode') !== 1 || ! is_array($response->json('data.data.retVal'))) {
            throw new RuntimeException('Unexpected Kaohsiung YouBike API response.');
        }

        return $response
            ->collect('data.data.retVal')
            ->filter(fn (mixed $station) => is_array($station) && filled($station['sno'] ?? null))
            ->unique('sno')
            ->map(fn (array $station) => [
                'sno' => (string) $station['sno'],
                'name' => (string) ($station['sna'] ?? ''),
                'district' => (string) ($station['sarea'] ?? ''),
                'address' => (string) ($station['ar'] ?? ''),
                'latitude' => (float) ($station['lat'] ?? 0),
                'longitude' => (float) ($station['lng'] ?? 0),
                'capacity' => (int) ($station['tot'] ?? 0),
                'is_active' => (string) ($station['act'] ?? '0') === '1',
                'available_rent_bikes' => (int) ($station['sbi'] ?? 0),
                'available_electric_bikes' => isset($station['sbi_detail']['eyb']) ? (int) $station['sbi_detail']['eyb'] : null,
                'available_return_bikes' => (int) ($station['bemp'] ?? 0),
                'source_updated_at' => filled($station['mday'] ?? null) ? Carbon::parse((string) $station['mday']) : null,
            ])
            ->values();
    }
}
