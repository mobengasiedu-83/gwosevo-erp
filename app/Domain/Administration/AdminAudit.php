<?php

namespace App\Domain\Administration;

use App\Models\AuditEvent;

class AdminAudit
{
    public static function record(string $event, string $type, int $id, ?array $old, ?array $new, ?int $organizationId = null): void
    {
        AuditEvent::create([
            'organization_id' => $organizationId ?? auth()->user()?->active_organization_id,
            'actor_id' => auth()->id(), 'event' => $event, 'auditable_type' => $type,
            'auditable_id' => $id, 'old_values' => $old, 'new_values' => $new,
            'ip_address' => request()->ip(), 'user_agent' => request()->userAgent(),
        ]);
    }
}
