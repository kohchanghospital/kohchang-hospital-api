<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donation_settings', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number');
            $table->string('qr_code_image')->nullable();
            $table->string('email');
            $table->string('phone');
            $table->string('fax');
            $table->string('facebook');
            $table->string('organization_name');
            $table->text('description');
            $table->text('address');
            $table->text('google_map_embed_url');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donation_settings');
    }
};
