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
        Schema::create('bike_stations', function (Blueprint $table) {
            $table->id();
            $table->string('sno')->unique();
            $table->string('name');
            $table->string('district')->index();
            $table->string('address');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedSmallInteger('capacity');
            $table->boolean('is_active');
            $table->unsignedSmallInteger('available_rent_bikes');
            $table->unsignedSmallInteger('available_electric_bikes')->nullable();
            $table->unsignedSmallInteger('available_return_bikes');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bike_stations');
    }
};
