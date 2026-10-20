<?php

namespace App\Http\Controllers\Identity;

use App\Domain\Identity\AccessReviews;
use App\Domain\Identity\IdentityAudit;
use App\Domain\Identity\UserAccess;
use App\Http\Controllers\Controller;
use App\Models\AccessReview;
use App\Models\AccessReviewItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = AccessReview::query();
        if (! $request->user()->isSuperAdmin()) {
            $query->where('reviewer_id', $request->user()->id);
        }

        return view('identity.reviews', ['reviews' => $query->latest()->paginate(25), 'reviewers' => User::where('status', 'active')->get()]);
    }

    public function store(Request $request, AccessReviews $reviews)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'reviewer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', 'active')], 'due_at' => ['required', 'date', 'after:now']]);
        $review = $reviews->create($data['name'], User::findOrFail($data['reviewer_id']), $request->user(), $data['due_at']);

        return to_route('identity.reviews.show', $review)->with('success', 'Access review created.');
    }

    public function show(Request $request, AccessReview $review)
    {
        $this->authorizeReview($request, $review);

        return view('identity.review', ['review' => $review, 'items' => AccessReviewItem::where('access_review_id', $review->id)->get(), 'users' => User::all()->keyBy('id')]);
    }

    public function decide(Request $request, AccessReview $review, AccessReviewItem $item)
    {
        $this->authorizeReview($request, $review);
        abort_unless($item->access_review_id === $review->id && ! $review->completed_at, 422);
        $data = $request->validate(['decision' => ['required', Rule::in(['retain', 'revoke'])], 'notes' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($request, $review, $item, $data) {
            $review = AccessReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_if($review->completed_at, 422);
            $item->update([...$data, 'decided_by' => $request->user()->id, 'decided_at' => now()]);
            IdentityAudit::change('review.decision', $item->user_id, null, ['review_id' => $review->id, ...$data]);
        });

        return back()->with('success', 'Review decision recorded.');
    }

    public function refresh(Request $request, AccessReview $review, AccessReviewItem $item)
    {
        $this->authorizeReview($request, $review);
        abort_unless($item->access_review_id === $review->id && ! $review->completed_at, 422);
        DB::transaction(function () use ($review, $item) {
            $review = AccessReview::whereKey($review->id)->lockForUpdate()->firstOrFail();
            abort_if($review->completed_at, 422);
            $snapshot = app(UserAccess::class)->snapshot(User::findOrFail($item->user_id));
            $item->update(['snapshot' => $snapshot, 'decision' => null, 'notes' => null, 'decided_at' => null, 'decided_by' => null]);
            IdentityAudit::change('review.refreshed', $item->user_id, null, ['review_id' => $review->id, 'snapshot' => $snapshot]);
        });

        return back()->with('success', 'Snapshot refreshed. A new decision is required.');
    }

    public function complete(Request $request, AccessReview $review, AccessReviews $reviews)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $reviews->complete($review, $request->user());

        return back()->with('success', 'Review completed and locked. Revocation decisions were applied.');
    }

    private function authorizeReview(Request $request, AccessReview $review): void
    {
        abort_unless($request->user()->isSuperAdmin() || $review->reviewer_id === $request->user()->id, 403);
    }
}
