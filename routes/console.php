<?php

use App\Jobs\SyncKaohsiungBikeStations;
use App\Models\BikeStation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('youbike:sync', function () {
    SyncKaohsiungBikeStations::dispatchSync();

    $this->info('Synced '.BikeStation::count().' Kaohsiung YouBike stations.');
})->purpose('Fetch Kaohsiung YouBike availability now and store it in the database');

Schedule::job(new SyncKaohsiungBikeStations)->everyTenMinutes();
Schedule::command('model:prune')->daily();
