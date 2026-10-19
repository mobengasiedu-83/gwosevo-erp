<?php

namespace App\Domain\CRM;

use App\Models\AuditEvent;
use App\Models\Lead;
use App\Models\User;
use App\Domain\Identity\LeadAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class LeadDuplicates
{
    public function candidates(Lead $lead, int $limit = 5, ?User $viewer = null): Collection
    {
        $email = Str::lower(trim((string) $lead->email));
        $phone = preg_replace('/\D+/', '', (string) $lead->phone);
        $company = Str::lower(trim((string) $lead->company_name));
        if ($email === '' && $phone === '' && $company === '') return new Collection;

        return Lead::query()->where('organization_id', $lead->organization_id)->whereKeyNot($lead->id)->whereNull('archived_at')
            ->where(function ($query) use ($email, $phone, $company) {
                if ($email !== '') $query->orWhereRaw('LOWER(email) = ?', [$email]);
                if ($phone !== '') $query->orWhere('phone_normalized', $phone);
                if (mb_strlen($company) >= 3) $query->orWhereRaw('LOWER(company_name) = ?', [$company]);
            })->latest()->limit($limit)->get();
    }

    public function markReviewed(Lead $lead, bool $isDuplicate, ?Lead $canonical, int $reviewerId): void
    {
        $old = $lead->duplicate_of_id;
        $lead->forceFill(['duplicate_of_id' => $isDuplicate ? $canonical?->id : null, 'duplicate_reviewed_by' => $reviewerId, 'duplicate_reviewed_at' => now()])->save();
        AuditEvent::create(['organization_id' => $lead->organization_id, 'actor_id' => $reviewerId, 'event' => 'lead.duplicate.reviewed', 'auditable_type' => Lead::class, 'auditable_id' => $lead->id, 'old_values' => ['duplicate_of_id' => $old], 'new_values' => ['duplicate_of_id' => $lead->duplicate_of_id], 'ip_address' => request()->ip(), 'user_agent' => request()->userAgent()]);
    }
}
