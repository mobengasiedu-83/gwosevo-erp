<?php

namespace App\Domain\Identity;

use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Invitations
{
    public function send(User $user): void
    {
        abort_unless($user->status === 'invited', 422, 'Only invited accounts can receive an invitation.');
        // A log transport must not write password-setting links into application logs.
        abort_if(in_array(config('mail.default'), ['log', 'array'], true) && ! app()->runningUnitTests(), 422, 'Configure an outbound mail transport before sending invitations.');
        $token = Str::random(64);
        $invitation = DB::transaction(function () use ($user, $token) {
            UserInvitation::where('user_id', $user->id)->whereNull('accepted_at')->update(['revoked_at' => now()]);

            return UserInvitation::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48), 'invited_by' => auth()->id()]);
        });
        try {
            $user->notify(new UserInvitationNotification(route('invitation.show', ['token' => $token])));
        } catch (\Throwable $e) {
            $invitation->update(['revoked_at' => now()]);
            IdentityAudit::change('invitation.delivery_failed', $user->id, null, ['invitation_id' => $invitation->id]);
            throw ValidationException::withMessages(['email' => 'Invitation delivery failed. Check mail configuration and resend.']);
        }
        IdentityAudit::change('invitation.sent', $user->id, null, ['invitation_id' => $invitation->id]);
    }
}
