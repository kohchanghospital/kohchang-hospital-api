<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();

            $table->string('title'); // ชื่อประกาศ
            $table->foreignId('type_id')
                ->constrained('announcement_types')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->date('announce_date'); // วันที่ประกาศ

            $table->string('pdf_name')->nullable();
            $table->string('file_path')->nullable();

            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
