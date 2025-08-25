<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // SQLite doesn't support MODIFY COLUMN with ENUM
        // Skip this migration in development (SQLite) environment
        if (config('database.default') === 'mysql') {
            Schema::table('videos', function (Blueprint $table) {
                \DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('pending', 'processing', 'ready', 'failed', 'replaced') DEFAULT 'pending'");
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // SQLite doesn't support MODIFY COLUMN with ENUM
        // Skip this migration in development (SQLite) environment
        if (config('database.default') === 'mysql') {
            Schema::table('videos', function (Blueprint $table) {
                \DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('pending', 'processing', 'ready', 'failed') DEFAULT 'pending'");
            });
        }
    }
};
