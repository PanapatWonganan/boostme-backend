<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin already exists
        $adminExists = User::where('role', 'admin')->exists();
        
        if (!$adminExists) {
            User::create([
                'id' => (string) Str::uuid(),
                'email' => 'admin@boostme.com',
                'password_hash' => bcrypt('admin123456'),
                'full_name' => 'BoostMe Administrator',
                'role' => 'admin',
                'email_verified' => true,
                'phone' => '0812345678',
            ]);

            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: admin@boostme.com');
            $this->command->info('Password: admin123456');
        } else {
            $this->command->info('Admin user already exists!');
        }
    }
}
