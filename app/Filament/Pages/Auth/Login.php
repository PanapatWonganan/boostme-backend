<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        
        // Find user by email
        $user = User::where('email', $data['email'])->first();
        
        if (!$user || !Hash::check($data['password'], $user->password_hash)) {
            $this->throwFailureValidationException();
        }
        
        // Check if user can access panel
        if (!$user->canAccessPanel(\Filament\Facades\Filament::getCurrentPanel())) {
            $this->throwFailureValidationException();
        }
        
        // Login the user
        Auth::login($user, $data['remember'] ?? false);
        
        session()->regenerate();
        
        return app(LoginResponse::class);
    }
}