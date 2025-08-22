<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ResetAdminPasswordSeeder extends Seeder
{
    public function run()
    {
        DB::table('users')->where('email', 'admin@example.com')->update([
            'password_hash' => Hash::make('Admin123!'),
        ]);
        
        echo "Admin password reset to: Admin123!\n";
    }
}