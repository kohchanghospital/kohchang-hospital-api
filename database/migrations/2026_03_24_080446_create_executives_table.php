<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('executives', function (Blueprint $table) {
            $table->id();

            $table->string('name_th');
            $table->string('name_en')->nullable();

            $table->string('position_th');
            $table->string('position_en')->nullable();

            $table->foreignId('department_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('image_path')->nullable();
            $table->integer('order_no')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executives');
    }
};
