<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('website_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_type', 100)->unique();
            $table->string('title_th');
            $table->string('title_en')->nullable();
            $table->longText('content_th')->nullable();
            $table->longText('content_en')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_policies');
    }
};
