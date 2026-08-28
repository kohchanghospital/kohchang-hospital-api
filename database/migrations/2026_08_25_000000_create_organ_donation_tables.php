<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('organ_donation_pages', function (Blueprint $table) {
            $table->id();
            $table->string('eyebrow_text');
            $table->string('page_title');
            $table->string('headline');
            $table->string('subheadline')->nullable();
            $table->string('importance_title');
            $table->text('importance_content');
            $table->string('qualification_title');
            $table->string('contact_title')->nullable();
            $table->text('contact_description')->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->string('external_url_label')->nullable();
            $table->timestamps();
        });

        Schema::create('organ_donation_organs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organ_donation_page_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organ_donation_page_id', 'is_active', 'sort_order'], 'organ_donation_organs_display_index');
        });

        Schema::create('organ_donation_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organ_donation_page_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['organ_donation_page_id', 'is_active', 'sort_order'], 'organ_donation_qualifications_display_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organ_donation_qualifications');
        Schema::dropIfExists('organ_donation_organs');
        Schema::dropIfExists('organ_donation_pages');
    }
};
