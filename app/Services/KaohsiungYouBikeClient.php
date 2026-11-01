<?php

namespace App\Services;

use App\Models\BikeStation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class KaohsiungYouBikeClient
{
    /**
     * Fetch the real-time availability of every Kaohsiung YouBike station.
     *
     * @return list<array{sno: string, name: string, district: string, address: string, latitude: float, longitude: float, capacity: int, is_active: bool, available_rent_bikes: int, available_electric_bikes: int|null, available_return_bikes: int, source_updated_at: Carbon|null}>
     *
     * @throws ConnectionException
     * @throws RequestException
     * @throws RuntimeException
     */
    public function fetchStations(): array
    {
        $payload = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->retry([1000, 3000], 0, fn (Throwable $exception) => $exception instanceof ConnectionException
                || ($exception instanceof RequestException && $exception->response->serverError()))
            ->get(config('services.kaohsiung_youbike.url'))
            ->throw()
            ->json('data.data');

        if (! is_array($payload) || ($payload['retCode'] ?? null) !== 1 || ! is_array($payload['retVal'] ?? null)) {
            throw new RuntimeException('Unexpected Kaohsiung YouBike API response.');
        }

        return collect($payload['retVal'])
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
                'source_updated_at' => $this->parseSourceTime($station['mday'] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * Parse the source's "YmdHis" local timestamp into the application timezone.
     */
    private function parseSourceTime(mixed $value): ?Carbon
    {
        if ((! is_string($value) && ! is_int($value)) || preg_match('/^\d{14}$/', (string) $value) !== 1) {
            return null;
        }

        return Carbon::createFromFormat('YmdHis', (string) $value, BikeStation::LOCAL_TIMEZONE)
            ->setTimezone(config('app.timezone'));
    }
}
