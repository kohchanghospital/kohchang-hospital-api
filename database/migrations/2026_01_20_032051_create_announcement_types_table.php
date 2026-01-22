<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('announcement_types', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // เช่น ข่าวสาร/ประชาสัมพันธ์, จัดซื้อจัดจ้าง
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_types');
    }
};
