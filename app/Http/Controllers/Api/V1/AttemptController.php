<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\AttemptData;
use App\Data\ServedQuestionData;
use App\Data\SubmitAnswerData;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController
{
    public function __construct(private readonly AttemptService $attempts) {}

    public function store(Request $request, string $quizSlug): JsonResponse
    {
        $quiz = Quiz::where('slug', $quizSlug)
            ->whereRelation('journey', 'is_published', true)
            ->firstOrFail();

        $attempt = $this->attempts->startOrResume($request->user(), $quiz, app()->getLocale());

        return response()->json([
            'data' => AttemptData::fromModel(
                $attempt->load('quiz'),
                $this->attempts->answeredCount($attempt),
            ),
        ], $attempt->wasRecentlyCreated ? 201 : 200);
    }

    public function questions(Request $request, int $attemptId): JsonResponse
    {
        $attempt = $this->ownedAttempt($request, $attemptId);

        $served = $attempt->attemptQuestions()->with('answer')->get();

        return response()->json([
            'data' => $served
                ->map(fn ($row) => ServedQuestionData::fromAttemptQuestion($row))
                ->values()
                ->all(),
        ]);
    }

    public function answer(Request $request, int $attemptId, SubmitAnswerData $data): JsonResponse
    {
        $attempt = $this->ownedAttempt($request, $attemptId);

        return response()->json([
            'data' => $this->attempts->answer($attempt, $data->position, $data->optionIds, $data->timeSpentMs),
        ]);
    }

    public function submit(Request $request, int $attemptId): JsonResponse
    {
        $attempt = $this->ownedAttempt($request, $attemptId);

        return response()->json([
            'data' => $this->attempts->submit($attempt),
        ]);
    }

    private function ownedAttempt(Request $request, int $attemptId): QuizAttempt
    {
        return QuizAttempt::where('id', $attemptId)
            ->where('user_id', $request->user()->id)
            ->with('quiz.journey')
            ->firstOrFail();
    }
}
