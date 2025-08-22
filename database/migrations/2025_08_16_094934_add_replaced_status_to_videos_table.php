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
        Schema::table('videos', function (Blueprint $table) {
            \DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('pending', 'processing', 'ready', 'failed', 'replaced') DEFAULT 'pending'");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            \DB::statement("ALTER TABLE videos MODIFY COLUMN status ENUM('pending', 'processing', 'ready', 'failed') DEFAULT 'pending'");
        });
    }
};
