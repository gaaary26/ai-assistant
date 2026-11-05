<?php

namespace Tests\Feature\Models;

use App\Models\BikeStation;
use App\Models\BikeStationSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BikeStationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_snapshots_older_than_the_retention_window(): void
    {
        $this->travelTo('2026-10-31 12:00:00');
        $station = BikeStation::factory()->create();
        $old = BikeStationSnapshot::factory()
            ->for($station)
            ->state(['recorded_at' => now()->subDays(8)])
            ->create();
        $recent = BikeStationSnapshot::factory()
            ->for($station)
            ->state(['recorded_at' => now()->subDays(6)])
            ->create();

        $this->artisan('model:prune', ['--model' => [BikeStationSnapshot::class]])->assertSuccessful();

        $this->assertSame([$recent->id], BikeStationSnapshot::pluck('id')->all());
        $this->assertNull($old->fresh());
    }
}
