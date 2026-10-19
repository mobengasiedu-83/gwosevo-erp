<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Sales\SalesAccess;
use App\Http\Controllers\Controller;
use App\Models\Opportunity;
use App\Models\User;
use App\Notifications\OpportunityActionNotification;
use Illuminate\Notifications\DatabaseNotification;

class SalesNotificationController extends Controller
{
    public function read(DatabaseNotification $notification)
    {
        abort_unless((int) $notification->notifiable_id === (int) auth()->id() && $notification->notifiable_type === User::class, 404);
        abort_unless($notification->type === OpportunityActionNotification::class, 404);
        $notification->markAsRead();
        $opportunity = Opportunity::find($notification->data['opportunity_id'] ?? 0);
        if ($opportunity && app(SalesAccess::class)->allows(auth()->user(), $opportunity)) return to_route('opportunities.show', $opportunity);
        return to_route('dashboard')->with('status', 'The reminder was marked as read.');
    }
}
