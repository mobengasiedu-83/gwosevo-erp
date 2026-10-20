<?php

namespace App\Domain\Support;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use App\Models\TicketSlaPolicy;
use App\Models\User;
use App\Notifications\TicketRequesterUpdate;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TicketWorkflow
{
    public function create(array $data, ?User $actor, array $files = []): array
    {
        [$ticket, $token] = DB::transaction(function () use ($data, $actor, $files) {
            $sequence = DB::table('number_sequences')->where('key', 'ticket')->lockForUpdate()->first();
            $reference = ($sequence->prefix ?? 'TKT-').str_pad((string) ($sequence->next_number ?? 1), $sequence->padding ?? 5, '0', STR_PAD_LEFT);
            if ($sequence) {
                DB::table('number_sequences')->where('key', 'ticket')->update(['next_number' => $sequence->next_number + 1, 'updated_at' => now()]);
            }

            $priority = $data['priority'];
            $sla = TicketSlaPolicy::where('organization_id', $data['organization_id'])->where('priority', $priority)->first();
            [$responseMinutes, $resolutionMinutes] = $sla
                ? [$sla->response_minutes, $sla->resolution_minutes]
                : (['critical' => [15, 240], 'high' => [60, 1440], 'normal' => [240, 4320], 'low' => [480, 10080]][$priority] ?? [240, 4320]);
            $token = Str::random(64);

            $ticketData = $data;
            unset($ticketData['initial_message'], $ticketData['attachments']);
            $ticket = Ticket::create([
                ...$ticketData,
                'reference' => $reference,
                'status' => ($data['assigned_to_id'] ?? null) || ($data['team_id'] ?? null) ? 'assigned' : 'open',
                'portal_token_hash' => hash('sha256', $token),
                'portal_token_encrypted' => $token,
                'first_response_due_at' => now()->addMinutes($responseMinutes),
                'resolution_due_at' => now()->addMinutes($resolutionMinutes),
                'escalation_owner_id' => $sla?->escalation_owner_id,
                'created_by' => $actor?->id,
            ]);

            $message = $ticket->messages()->create([
                'author_id' => $ticket->channel === 'email' || $ticket->channel === 'portal' ? null : $actor?->id,
                'author_name' => in_array($ticket->channel, ['email', 'portal'], true) ? $ticket->requester_name : ($actor?->name ?: ($data['author_name'] ?? $ticket->requester_name)),
                'author_email' => in_array($ticket->channel, ['email', 'portal'], true) ? $ticket->requester_email : ($actor?->email ?: ($data['author_email'] ?? $ticket->requester_email)),
                'visibility' => 'public',
                'channel' => $ticket->channel,
                'body' => $data['initial_message'],
            ]);
            $this->storeAttachments($message, $files, $actor);

            return [$ticket, $token];
        });

        if ($actor && $ticket->requester_email) {
            $this->notifyRequester($ticket, 'A support ticket has been created for you.', $ticket->messages()->first()?->body);
        }

        return [$ticket, $token];
    }

    public function reply(Ticket $ticket, array $data, ?User $actor, array $files = [], string $channel = 'manual'): TicketMessage
    {
        $message = DB::transaction(function () use ($ticket, $data, $actor, $files, $channel) {
            $visibility = $data['visibility'] ?? 'public';
            $message = $ticket->messages()->create([
                'author_id' => $actor?->id,
                'author_name' => $actor?->name ?: ($data['author_name'] ?? $ticket->requester_name),
                'author_email' => $actor?->email ?: ($data['author_email'] ?? $ticket->requester_email),
                'visibility' => $visibility,
                'channel' => $channel,
                'body' => $data['body'],
            ]);
            $this->storeAttachments($message, $files, $actor);

            if ($visibility === 'public' && $actor) {
                $ticket->first_response_at ??= now();
            }
            if (! $actor && in_array($ticket->status, ['resolved', 'closed'], true)) {
                $ticket->reopen_count++;
                $ticket->status = 'open';
                $ticket->resolved_at = null;
                $ticket->closed_at = null;
            }
            $ticket->save();

            return $message;
        });

        if ($actor && $message->visibility === 'public' && $ticket->requester_email) {
            $this->notifyRequester($ticket, 'There is a new update on your support ticket.', $message->body);
        }

        return $message;
    }

    public function transition(Ticket $ticket, string $status): void
    {
        $previousStatus = $ticket->status;
        if ($status === 'resolved') {
            $ticket->resolved_at = now();
            $ticket->closed_at = null;
        } elseif ($status === 'closed') {
            $ticket->resolved_at ??= now();
            $ticket->closed_at = now();
        } elseif (in_array($ticket->status, ['resolved', 'closed'], true) && in_array($status, ['open', 'assigned', 'in_progress', 'waiting'], true)) {
            $ticket->reopen_count++;
            $ticket->resolved_at = null;
            $ticket->closed_at = null;
        }
        $ticket->status = $status;
        $ticket->save();

        if ($previousStatus !== $status && $ticket->requester_email) {
            $this->notifyRequester($ticket, 'Your ticket status is now '.str_replace('_', ' ', $status).'.');
        }
    }

    private function notifyRequester(Ticket $ticket, string $update, ?string $publicReply = null): void
    {
        try {
            Notification::route('mail', $ticket->requester_email)
                ->notify(new TicketRequesterUpdate($ticket, $update, $publicReply));
        } catch (\Throwable $exception) {
            Log::channel('email')->warning('Ticket requester email could not be sent.', [
                'ticket_id' => $ticket->id,
                'exception' => $exception::class,
            ]);
        }
    }

    private function storeAttachments(TicketMessage $message, array $files, ?User $actor): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = $file->store('tickets/'.$message->ticket_id, 'local');
            TicketAttachment::create([
                'message_id' => $message->id,
                'disk' => 'local',
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => $actor?->id,
            ]);
        }
    }
}
