<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_visitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('visitor_uuid')->unique();
            $table->dateTime('first_seen_at')->index();
            $table->dateTime('last_seen_at')->index();
            $table->timestamps();
        });

        Schema::create('website_visitor_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('website_visitors')->cascadeOnDelete();
            $table->date('visit_date')->index();
            $table->timestamps();
            $table->unique(['visitor_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_visitor_days');
        Schema::dropIfExists('website_visitors');
    }
};
