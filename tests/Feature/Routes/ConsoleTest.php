<?php

namespace Tests\Feature\Routes;

use App\Jobs\SyncKaohsiungBikeStations;
use App\Models\BikeStation;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sync_command_fetches_the_stations_immediately(): void
    {
        Http::fake(['api.kcg.gov.tw/*' => Http::response([
            'success' => true,
            'data' => [
                'data' => [
                    'retCode' => 1,
                    'updated_at' => '2026-10-31 21:27:39',
                    'retVal' => [[
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
                    ]],
                ],
            ],
        ])]);

        $this
            ->artisan('youbike:sync')
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
}
