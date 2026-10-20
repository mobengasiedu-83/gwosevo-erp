<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketSlaEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckTicketSla extends Command
{
    protected $signature = 'tickets:check-sla';
    protected $description = 'Record ticket SLA breaches and route overdue tickets to their escalation owner';

    public function handle(): int
    {
        $count = 0;
        Ticket::whereNotIn('status', ['resolved', 'closed'])->where(function ($query) {
            $query->where(fn ($q) => $q->whereNull('first_response_at')->whereNull('response_breached_at')->where('first_response_due_at', '<=', now()))
                ->orWhere(fn ($q) => $q->whereNull('resolution_breached_at')->where('resolution_due_at', '<=', now()));
        })->orderBy('id')->chunkById(100, function ($tickets) use (&$count) {
            foreach ($tickets as $ticket) {
                DB::transaction(function () use ($ticket, &$count) {
                    $ticket = Ticket::lockForUpdate()->find($ticket->id);
                    if (! $ticket || in_array($ticket->status, ['resolved', 'closed'], true)) return;
                    $changed = false;
                    foreach ([
                        'response_breach' => $ticket->first_response_due_at && ! $ticket->first_response_at && ! $ticket->response_breached_at && $ticket->first_response_due_at->isPast(),
                        'resolution_breach' => $ticket->resolution_due_at && ! $ticket->resolution_breached_at && $ticket->resolution_due_at->isPast(),
                    ] as $type => $isBreached) {
                        if (! $isBreached) continue;
                        $column = $type === 'response_breach' ? 'response_breached_at' : 'resolution_breached_at';
                        $ticket->{$column} = now();
                        TicketSlaEvent::firstOrCreate(['ticket_id' => $ticket->id, 'type' => $type], ['occurred_at' => now(), 'notified_user_id' => $ticket->escalation_owner_id]);
                        if ($ticket->escalation_owner_id) $ticket->assigned_to_id = $ticket->escalation_owner_id;
                        $changed = true;
                        $count++;
                    }
                    if ($changed) $ticket->save();
                });
            }
        });
        $this->info("Recorded {$count} ticket SLA breach event(s).");
        return self::SUCCESS;
    }
}
