<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vehicle_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('schedule_date')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->index(['schedule_date', 'driver_id']);
            $table->index(['schedule_date', 'vehicle_id']);
        });

        Schema::create('vehicle_schedule_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_schedule_id')->constrained()->cascadeOnDelete();
            $table->text('detail_text');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['vehicle_schedule_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_schedule_details');
        Schema::dropIfExists('vehicle_schedules');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('drivers');
    }
};
