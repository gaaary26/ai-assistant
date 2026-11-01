<?php

namespace Tests\Feature;

use App\Jobs\SyncKaohsiungBikeStations;
use App\Models\BikeStation;
use App\Models\BikeStationSnapshot;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SyncKaohsiungBikeStationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    public function test_it_stores_the_stations_and_a_snapshot_of_each_one(): void
    {
        Carbon::setTestNow('2026-10-31 13:30:00');
        Http::fake(['api.kcg.gov.tw/*' => Http::response($this->payload([
            $this->station(),
            $this->station(['sno' => '501201001', 'act' => '0', 'sbi' => 0, 'bemp' => 0, 'sbi_detail' => null]),
        ]))]);

        SyncKaohsiungBikeStations::dispatchSync();

        $this->assertSame(2, BikeStation::count());

        $station = BikeStation::firstWhere('sno', '501201002');

        $this->assertSame('YouBike2.0_捷運美麗島站(2號出口)', $station->name);
        $this->assertSame('新興區', $station->district);
        $this->assertSame(22.63035, $station->latitude);
        $this->assertSame(40, $station->capacity);
        $this->assertTrue($station->is_active);
        $this->assertSame(4, $station->available_rent_bikes);
        $this->assertSame(1, $station->available_electric_bikes);
        $this->assertSame(36, $station->available_return_bikes);
        $this->assertSame('2026-10-31 13:27:03', $station->source_updated_at->toDateTimeString());

        $inactiveStation = BikeStation::firstWhere('sno', '501201001');

        $this->assertFalse($inactiveStation->is_active);
        $this->assertNull($inactiveStation->available_electric_bikes);

        $snapshot = $station->snapshots()->sole();

        $this->assertSame(4, $snapshot->available_rent_bikes);
        $this->assertSame('2026-10-31 13:30:00', $snapshot->recorded_at->toDateTimeString());
    }

    public function test_it_updates_existing_stations_instead_of_duplicating_them(): void
    {
        Http::fake(['api.kcg.gov.tw/*' => Http::sequence()
            ->push($this->payload([$this->station(['sbi' => 4])]))
            ->push($this->payload([$this->station(['sbi' => 9])]))]);

        SyncKaohsiungBikeStations::dispatchSync();
        SyncKaohsiungBikeStations::dispatchSync();

        $this->assertSame(9, BikeStation::sole()->available_rent_bikes);
        $this->assertSame([4, 9], BikeStationSnapshot::orderBy('id')->pluck('available_rent_bikes')->all());
    }

    public function test_it_writes_nothing_when_the_api_fails(): void
    {
        Http::fake(['api.kcg.gov.tw/*' => Http::response('Service Unavailable', 503)]);

        try {
            SyncKaohsiungBikeStations::dispatchSync();
            $this->fail('Expected the sync to throw a RequestException.');
        } catch (RequestException) {
            //
        }

        $this->assertSame(0, BikeStation::count());
        $this->assertSame(0, BikeStationSnapshot::count());
    }

    public function test_it_writes_nothing_when_the_api_reports_an_error(): void
    {
        Http::fake(['api.kcg.gov.tw/*' => Http::response($this->payload([], retCode: 0))]);

        try {
            SyncKaohsiungBikeStations::dispatchSync();
            $this->fail('Expected the sync to throw a RuntimeException.');
        } catch (RuntimeException) {
            //
        }

        $this->assertSame(0, BikeStation::count());
    }

    public function test_the_sync_command_fetches_the_stations_immediately(): void
    {
        Http::fake(['api.kcg.gov.tw/*' => Http::response($this->payload([$this->station()]))]);

        $this->artisan('youbike:sync')
            ->expectsOutput('Synced 1 Kaohsiung YouBike stations.')
            ->assertSuccessful();

        $this->assertSame(1, BikeStation::count());
    }

    public function test_it_schedules_the_sync_every_ten_minutes_and_prunes_old_snapshots_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertSame(
            '*/10 * * * *',
            $events->first(fn (Event $event) => $event->description === SyncKaohsiungBikeStations::class)?->expression,
        );
        $this->assertSame(
            '0 0 * * *',
            $events->first(fn (Event $event) => str_contains((string) $event->command, 'model:prune'))?->expression,
        );
    }

    public function test_it_prunes_snapshots_older_than_the_retention_window(): void
    {
        Carbon::setTestNow('2026-10-31 12:00:00');
        $station = BikeStation::factory()->create();
        $old = BikeStationSnapshot::factory()->for($station)->create(['recorded_at' => now()->subDays(8)]);
        $recent = BikeStationSnapshot::factory()->for($station)->create(['recorded_at' => now()->subDays(6)]);

        $this->artisan('model:prune', ['--model' => [BikeStationSnapshot::class]])->assertSuccessful();

        $this->assertSame([$recent->id], BikeStationSnapshot::pluck('id')->all());
        $this->assertNull($old->fresh());
    }

    /**
     * Build a fake Kaohsiung YouBike API payload.
     *
     * @param  list<array<string, mixed>>  $stations
     * @return array<string, mixed>
     */
    private function payload(array $stations, int $retCode = 1): array
    {
        return [
            'success' => true,
            'data' => [
                'data' => [
                    'retCode' => $retCode,
                    'updated_at' => '2026-10-31 21:27:39',
                    'retVal' => $stations,
                ],
            ],
        ];
    }

    /**
     * Build a single station record in the Kaohsiung API format.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function station(array $overrides = []): array
    {
        return [
            'scity' => '高雄市',
            'sna' => 'YouBike2.0_捷運美麗島站(2號出口)',
            'sarea' => '新興區',
            'ar' => '中山一路83號旁停車場前方',
            'sno' => '501201002',
            'tot' => 40,
            'sbi' => 4,
            'mday' => '20261031212703',
            'lat' => 22.63035,
            'lng' => 120.30175,
            'bemp' => 36,
            'act' => '1',
            'sbi_detail' => ['yb2' => 3, 'eyb' => 1],
            ...$overrides,
        ];
    }
}
