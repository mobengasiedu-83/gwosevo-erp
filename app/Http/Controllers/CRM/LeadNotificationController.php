<?php

namespace App\Http\Controllers\CRM;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class LeadNotificationController extends Controller
{
    public function read(DatabaseNotification $notification)
    {
        abort_unless((int) $notification->notifiable_id === (int) auth()->id() && $notification->notifiable_type === User::class, 404);
        abort_unless($notification->type === \App\Notifications\LeadFollowUpNotification::class, 404);
        $notification->markAsRead();
        $lead = Lead::find($notification->data['lead_id'] ?? 0);
        if ($lead && auth()->user()->can('view', $lead)) return redirect()->route('leads.show', $lead);

        return redirect()->route('dashboard')->with('status', 'The reminder was marked as read.');
    }
}
