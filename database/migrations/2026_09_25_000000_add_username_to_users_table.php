<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Existing accounts stay intact and cannot sign in until an administrator assigns a username.
            $table->string('username', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        // Preserve assigned usernames on rollback; dropping them would lock out existing accounts.
    }
};
