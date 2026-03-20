<?php

namespace App\Services\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(private AuditLogService $auditLog)
    {
    }

    public function register(RegisterRequest $request): User
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        $user->assignRole('client');

        $this->auditLog->log('user_created', $user);

        return $user;
    }

    public function login(LoginRequest $request): bool
    {
        try {
            $request->authenticate();
        } catch (ValidationException $exception) {
            $this->auditLog->log('login_failed', null, ['email' => $request->email]);

            throw $exception;
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $this->auditLog->log('login_failed', $user, ['reason' => 'account_deactivated']);

            throw ValidationException::withMessages([
                'email' => __('auth.account_deactivated'),
            ]);
        }

        $this->auditLog->log('login_success', $user);

        User::where('id', $user->id)->update(['last_login_at' => now()]);

        return true;
    }

    public function logout(Request $request): void
    {
        $this->auditLog->log('logout', Auth::user());

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
