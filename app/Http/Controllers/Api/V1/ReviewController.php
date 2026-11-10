<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\AttemptData;
use App\Data\ReviewSummaryData;
use App\Models\ReviewItem;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController
{
    public function __construct(private readonly AttemptService $attempts) {}

    public function summary(Request $request): JsonResponse
    {
        $now = now();

        $counts = ReviewItem::query()
            ->where('user_id', $request->user()->id)
            ->selectRaw('count(*) as tracked_count')
            ->selectRaw('count(*) filter (where due_at <= ?) as due_count', [$now])
            ->selectRaw('min(due_at) filter (where due_at > ?) as next_due_at', [$now])
            ->first();

        return response()->json([
            'data' => new ReviewSummaryData(
                dueCount: (int) $counts->due_count,
                trackedCount: (int) $counts->tracked_count,
                nextDueAt: $counts->next_due_at === null ? null : $now->parse($counts->next_due_at)->toIso8601String(),
                sessionSize: AttemptService::REVIEW_SESSION_SIZE,
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $attempt = $this->attempts->startOrResumeReview($request->user(), app()->getLocale());

        return response()->json([
            'data' => AttemptData::fromModel($attempt, $this->attempts->answeredCount($attempt)),
        ], $attempt->wasRecentlyCreated ? 201 : 200);
    }
}
