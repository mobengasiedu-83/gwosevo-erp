<?php

namespace App\Domain\Identity;

use App\Models\AccessReview;
use App\Models\AccessReviewItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessReviews
{
    public function create(string $name, User $reviewer, User $creator, string $due): AccessReview
    {
        return DB::transaction(function () use ($name, $reviewer, $creator, $due) {
            $review = AccessReview::create(['name' => $name, 'reviewer_id' => $reviewer->id, 'created_by' => $creator->id, 'due_at' => $due]);
            foreach (User::where('status', '!=', 'inactive')->orderBy('id')->get() as $user) {
                AccessReviewItem::create(['access_review_id' => $review->id, 'user_id' => $user->id, 'snapshot' => app(UserAccess::class)->snapshot($user)]);
            }
            IdentityAudit::change('review.created', $creator->id, null, ['review_id' => $review->id, 'reviewer_id' => $reviewer->id, 'due_at' => $due]);

            return $review;
        });
    }

    public function complete(AccessReview $review, User $actor): void
    {
        DB::transaction(function () use ($review, $actor) {
            $review = AccessReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_if($review->completed_at, 422, 'This review is locked.');
            $items = AccessReviewItem::where('access_review_id', $review->id)->orderBy('user_id')->lockForUpdate()->get();
            if ($items->isEmpty() || $items->contains(fn ($item) => ! $item->decision)) {
                throw ValidationException::withMessages(['review' => 'Decide every review item before completing the review.']);
            }
            foreach ($items as $item) {
                $user = User::whereKey($item->user_id)->lockForUpdate()->firstOrFail();
                if (app(UserAccess::class)->snapshot($user) !== $item->snapshot) {
                    throw ValidationException::withMessages(['review' => 'User access changed during the review. Refresh the affected item and decide again.']);
                }
                if ($item->decision === 'revoke') {
                    app(UserAccess::class)->guardAdministrator($user, 'inactive', []);
                    $user->update(['status' => 'inactive']);
                    $user->syncRoles([]);
                    $user->syncPermissions([]);
                    app(SessionRegistry::class)->revokeAll($user);
                    IdentityAudit::change('review.access_revoked', $user->id, $item->snapshot, ['review_id' => $review->id, 'status' => 'inactive']);
                }
            }
            $review->update(['completed_at' => now()]);
            IdentityAudit::change('review.completed', $actor->id, null, ['review_id' => $review->id]);
        });
    }
}
