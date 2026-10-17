<?php

namespace App\Domain\Identity;

use App\Domain\Administration\AdminAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class IdentityAudit
{
    public static function login(string $event, ?User $user = null, ?string $email = null, string $method = 'password'): void
    {
        DB::table('login_events')->insert([
            'user_id' => $user?->id, 'email' => $email ?? $user?->email,
            'event' => $event, 'method' => $method, 'ip_address' => request()->ip(),
            'user_agent' => mb_substr(request()->userAgent() ?? '', 0, 500), 'created_at' => now(),
        ]);
    }

    public static function change(string $event, int $id, ?array $old, ?array $new): void
    {
        AdminAudit::record('identity.'.$event, User::class, $id, $old, $new);
    }
}
