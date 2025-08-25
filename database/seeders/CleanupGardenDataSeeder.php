<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\UserGarden;

class CleanupGardenDataSeeder extends Seeder
{
    /**
     * Clean up duplicate garden data and ensure user isolation
     */
    public function run(): void
    {
        $this->command->info('🧹 Starting garden data cleanup...');
        
        // 1. ลบ garden ที่ซ้ำกัน (เก็บแค่อันล่าสุด)
        $this->removeDuplicateGardens();
        
        // 2. ตรวจสอบและแก้ไข user_id ใน plants ที่ไม่ตรงกับ garden
        $this->fixPlantUserIds();
        
        // 3. ตรวจสอบและแก้ไข activities ที่ไม่ตรงกับ user
        $this->fixActivityUserIds();
        
        $this->command->info('✅ Garden data cleanup completed!');
    }
    
    private function removeDuplicateGardens(): void
    {
        $this->command->info('Removing duplicate gardens...');
        
        // หา user ที่มี garden มากกว่า 1 อัน
        $usersWithDuplicates = DB::table('user_gardens')
            ->select('user_id', DB::raw('COUNT(*) as count'))
            ->groupBy('user_id')
            ->having('count', '>', 1)
            ->get();
        
        foreach ($usersWithDuplicates as $userData) {
            // เก็บ garden ล่าสุด
            $latestGarden = UserGarden::where('user_id', $userData->user_id)
                ->orderBy('created_at', 'desc')
                ->first();
            
            // ลบ garden อื่นๆ
            UserGarden::where('user_id', $userData->user_id)
                ->where('id', '!=', $latestGarden->id)
                ->delete();
            
            $this->command->info("  Cleaned duplicates for user: {$userData->user_id}");
        }
    }
    
    private function fixPlantUserIds(): void
    {
        $this->command->info('Fixing plant user IDs...');
        
        // ตรวจสอบพืชทั้งหมดให้ user_id ตรงกับ garden owner
        $plants = DB::table('user_plants')
            ->join('user_gardens', 'user_plants.garden_id', '=', 'user_gardens.id')
            ->whereRaw('user_plants.user_id != user_gardens.user_id')
            ->select('user_plants.id', 'user_gardens.user_id as correct_user_id')
            ->get();
        
        foreach ($plants as $plant) {
            DB::table('user_plants')
                ->where('id', $plant->id)
                ->update(['user_id' => $plant->correct_user_id]);
        }
        
        $this->command->info("  Fixed {$plants->count()} plant records");
    }
    
    private function fixActivityUserIds(): void
    {
        $this->command->info('Fixing activity user IDs...');
        
        // ตรวจสอบ activities ให้ user_id ตรงกับ garden owner
        $activities = DB::table('garden_activities')
            ->join('user_gardens', 'garden_activities.garden_id', '=', 'user_gardens.id')
            ->whereRaw('garden_activities.user_id != user_gardens.user_id')
            ->select('garden_activities.id', 'user_gardens.user_id as correct_user_id')
            ->get();
        
        foreach ($activities as $activity) {
            DB::table('garden_activities')
                ->where('id', $activity->id)
                ->update(['user_id' => $activity->correct_user_id]);
        }
        
        $this->command->info("  Fixed {$activities->count()} activity records");
    }
}