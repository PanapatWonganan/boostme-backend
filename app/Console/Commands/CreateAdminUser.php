<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create {email?} {password?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create or update admin user for Filament';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email') ?? $this->ask('Enter admin email', 'admin@boostme.com');
        $password = $this->argument('password') ?? $this->secret('Enter admin password');

        if (!$password) {
            $password = 'admin123456';
            $this->info('Using default password: admin123456');
        }

        // Check if user already exists
        $user = User::where('email', $email)->first();

        if ($user) {
            // Update existing user
            $user->update([
                'password_hash' => Hash::make($password),
                'role' => 'admin',
                'email_verified' => true,
                'full_name' => $user->full_name ?? 'BoostMe Administrator',
            ]);
            $this->info("Admin user updated: {$email}");
        } else {
            // Create new user
            User::create([
                'id' => (string) Str::uuid(),
                'email' => $email,
                'password_hash' => Hash::make($password),
                'full_name' => 'BoostMe Administrator',
                'role' => 'admin',
                'email_verified' => true,
            ]);
            $this->info("Admin user created: {$email}");
        }

        $this->info('Admin user is ready for Filament login!');
        $this->table(['Email', 'Password'], [[$email, $password]]);

        return Command::SUCCESS;
    }
}
