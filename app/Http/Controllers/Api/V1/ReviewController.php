<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\AttemptData;
use App\Models\ReviewItem;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController
{
    public function __construct(private readonly AttemptService $attempts) {}

    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $next = ReviewItem::where('user_id', $userId)
            ->where('due_at', '>', now())
            ->min('due_at');

        return response()->json([
            'data' => [
                'dueCount' => $this->attempts->dueReviewCount($request->user()),
                'trackedCount' => ReviewItem::where('user_id', $userId)->count(),
                'nextDueAt' => $next === null ? null : now()->parse($next)->toIso8601String(),
                'sessionSize' => AttemptService::REVIEW_SESSION_SIZE,
            ],
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
