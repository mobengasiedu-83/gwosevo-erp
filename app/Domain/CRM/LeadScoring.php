<?php

namespace App\Domain\CRM;

use App\Models\Lead;

class LeadScoring
{
    public function refresh(Lead $lead): void
    {
        $fit = $this->fitScore($lead);
        $activityCount = $lead->activities()->count();
        $completedCount = $lead->activities()->whereNotNull('completed_at')->count();
        $engagement = min(100, ($activityCount * 12) + ($completedCount * 8) + ($lead->next_follow_up_at && $lead->next_follow_up_at->isFuture() ? 8 : 0));
        $fitWeight = max(0, min(100, (int) config('crm.fit_weight', 60)));
        $engagementWeight = max(0, min(100, (int) config('crm.engagement_weight', 40)));
        $totalWeight = max(1, $fitWeight + $engagementWeight);
        $score = (int) round((($fit * $fitWeight) + ($engagement * $engagementWeight)) / $totalWeight);

        $lead->forceFill(['fit_score' => $fit, 'engagement_score' => $engagement, 'score' => $score])->saveQuietly();
    }

    private function fitScore(Lead $lead): int
    {
        $fields = [
            filled($lead->email) || filled($lead->phone),
            filled($lead->company_name),
            filled($lead->industry),
            filled($lead->interests),
            $lead->budget !== null,
            filled($lead->authority),
            filled($lead->need),
            filled($lead->timeline),
        ];

        return (int) round((count(array_filter($fields)) / count($fields)) * 100);
    }
}
