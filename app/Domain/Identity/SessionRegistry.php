<?php

namespace App\Domain\Identity;

use App\Models\IdentitySession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SessionRegistry
{
    public function record(User $user, string $method): void
    {
        IdentitySession::updateOrCreate(['session_hash' => hash('sha256', request()->session()->getId())], [
            'user_id' => $user->id, 'method' => $method, 'ip_address' => request()->ip(),
            'user_agent' => mb_substr(request()->userAgent() ?? '', 0, 500), 'last_seen_at' => now(),
        ]);
    }

    public function revokeAll(User $user): void
    {
        DB::transaction(function () use ($user) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->forceFill(['auth_version' => $locked->auth_version + 1, 'remember_token' => Str::random(60)])->save();
            IdentitySession::where('user_id', $user->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $user->tokens()->delete();
        });
    }
}
