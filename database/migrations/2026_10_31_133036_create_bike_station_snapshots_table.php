<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bike_station_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bike_station_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('available_rent_bikes');
            $table->unsignedSmallInteger('available_electric_bikes')->nullable();
            $table->unsignedSmallInteger('available_return_bikes');
            $table->timestamp('recorded_at')->index();

            $table->index(['bike_station_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bike_station_snapshots');
    }
};
