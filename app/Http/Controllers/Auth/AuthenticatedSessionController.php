<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\IdentityAudit;
use App\Domain\Identity\LoginFlow;
use App\Http\Controllers\Controller;
use App\Models\IdentitySession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request, LoginFlow $flow)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:1000']]);
        $email = mb_strtolower(trim($data['email']));
        $key = 'login:'.hash('sha256', $email.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 6)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Try again later.']);
        }
        $user = User::where('email', $email)->first();
        $valid = $user && Hash::check($data['password'], $user->password);
        if (! $user) {
            Hash::check($data['password'], '$2y$12$Dhh65K5H29mIkhRxckg5zumgy0r9tSZBfh4gR0CWeH3m5vqUKtr2a');
        }
        if (! $valid || ! $user->isActive()) {
            RateLimiter::hit($key, 60);
            IdentityAudit::login('failed', $user, $email);
            throw ValidationException::withMessages(['email' => 'Invalid credentials or inactive account.']);
        }
        RateLimiter::clear($key);

        return $flow->begin($user, remember: $request->boolean('remember'));
    }

    public function destroy(Request $request)
    {
        if ($request->user()) {
            IdentityAudit::login('logout', $request->user());
        }
        IdentitySession::where('session_hash', hash('sha256', $request->session()->getId()))->update(['revoked_at' => now()]);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login');
    }
}
