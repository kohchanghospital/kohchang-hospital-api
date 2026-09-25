<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hero_sliders', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('desktop_image');
            $table->string('mobile_image')->nullable();
            $table->string('button_text', 100)->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->string('text_alignment', 10)->default('left');
            $table->boolean('overlay_enabled')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['is_active', 'display_order']);
        });
    }

    public function down(): void { Schema::dropIfExists('hero_sliders'); }
};
