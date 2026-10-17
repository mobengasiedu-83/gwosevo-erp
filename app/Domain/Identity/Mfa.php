<?php

namespace App\Domain\Identity;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class Mfa
{
    public function verify(User $user, string $code): bool
    {
        return DB::transaction(function () use ($user, $code) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (! $locked->mfa_confirmed_at) {
                return false;
            }
            if (preg_match('/^[0-9]{6}$/', $code)) {
                $timestamp = app(Google2FA::class)->verifyKeyNewer($locked->mfa_secret, $code, $locked->mfa_last_used ?? 0, 1);
                if ($timestamp === false) {
                    return false;
                }
                $locked->forceFill(['mfa_last_used' => $timestamp])->save();

                return true;
            }
            $hash = hash('sha256', $code);
            $codes = $locked->mfa_recovery_codes ?? [];
            foreach ($codes as $i => $stored) {
                if (hash_equals($stored, $hash)) {
                    unset($codes[$i]);
                    $locked->forceFill(['mfa_recovery_codes' => array_values($codes)])->save();

                    return true;
                }
            }

            return false;
        });
    }

    public function recoveryCodes(): array
    {
        return array_map(fn () => Str::lower(Str::random(10)).'-'.Str::lower(Str::random(10)), range(1, 8));
    }
}
