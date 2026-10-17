<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\IdentityAudit;
use App\Domain\Identity\SessionRegistry;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password as Broker;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function forgot()
    {
        return view('identity.password', ['mode' => 'forgot']);
    }

    public function reset(string $token, Request $request)
    {
        return response()->view('identity.password', ['mode' => 'reset', 'token' => $token, 'email' => $request->query('email')])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function email(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        abort_if(in_array(config('mail.default'), ['log', 'array'], true) && ! app()->runningUnitTests(), 422, 'Outbound email is not configured.');
        $user = User::where('email', mb_strtolower($data['email']))->where('status', 'active')->first();
        if ($user) {
            try {
                Broker::sendResetLink(['email' => $user->email, 'status' => 'active']);
            } catch (\Throwable $e) {
                // Keep the public response identical for existing and unknown
                // accounts, including during a provider outage.
                IdentityAudit::login('password_reset_delivery_failed', $user);
            }
        }
        IdentityAudit::login('password_reset_requested', $user, $data['email']);

        return back()->with('status', 'If an active account matches, a password reset link has been sent.');
    }

    public function update(Request $request)
    {
        $data = $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $status = DB::transaction(function () use ($data, $request) {
            // Serialize token consumption and account changes so concurrent
            // reset submissions cannot both consume the same valid token.
            DB::table(config('auth.passwords.users.table'))->where('email', $data['email'])->lockForUpdate()->first();
            User::where('email', $data['email'])->lockForUpdate()->first();

            return Broker::reset([...$data, 'password_confirmation' => $request->input('password_confirmation'), 'status' => 'active'], function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                app(SessionRegistry::class)->revokeAll($user);
                IdentityAudit::change('password.reset', $user->id, null, ['sessions_revoked' => true]);
                event(new PasswordReset($user));
            });
        });
        if ($status !== Broker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired.']);
        }

        return to_route('login')->with('status', 'Password reset. Sign in using your new password.');
    }

    public function invitation(string $token)
    {
        $invitation = $this->validInvitation($token);

        return response()->view('identity.password', ['mode' => 'invitation', 'token' => $token, 'email' => User::findOrFail($invitation->user_id)->email])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function accept(Request $request, string $token)
    {
        $data = $request->validate(['password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()]]);
        DB::transaction(function () use ($token, $data) {
            $invitation = $this->validInvitation($token, true);
            $user = User::whereKey($invitation->user_id)->lockForUpdate()->firstOrFail();
            abort_unless($user->status === 'invited', 422, 'This invitation is no longer valid.');
            $user->forceFill(['password' => $data['password'], 'status' => 'active', 'email_verified_at' => now()])->save();
            $invitation->update(['accepted_at' => now()]);
            IdentityAudit::change('invitation.accepted', $user->id, ['status' => 'invited'], ['status' => 'active']);
        });

        return to_route('login')->with('status', 'Account activated. You can now sign in.');
    }

    private function validInvitation(string $token, bool $lock = false): UserInvitation
    {
        $query = UserInvitation::where('token_hash', hash('sha256', $token))->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->firstOrFail();
    }
}
