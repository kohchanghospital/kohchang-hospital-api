<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_id')->constrained()->cascadeOnDelete();
            $table->string('lang', 5);          // th, en
            $table->string('title');
            $table->longText('body');
            $table->timestamps();

            $table->unique(['content_id', 'lang']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_translations');
    }
};
