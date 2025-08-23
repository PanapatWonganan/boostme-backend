<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Delete video records with invalid localhost paths
        DB::table('videos')
            ->where('original_path', 'like', '%/Users/panapat/%')
            ->orWhere('original_path', 'like', '%localhost%')
            ->orWhere('original_path', 'like', '%fitness-lms-admin%')
            ->delete();
            
        echo "Deleted old video records with localhost paths\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Cannot reverse this migration as we're deleting data
    }
};