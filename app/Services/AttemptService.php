<?php

namespace App\Services;

use App\Data\AnswerResultData;
use App\Data\AttemptResultData;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\QuizAttemptQuestion;
use App\Models\User;
use App\Models\UserJourneyProgress;
use App\Models\UserXpEvent;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttemptService
{
    public function __construct(
        private readonly ReviewScheduler $reviews,
        private readonly StreakTracker $streaks,
    ) {}

    public function startOrResume(User $user, Quiz $quiz, string $locale): QuizAttempt
    {
        $existing = QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->inProgress()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(fn () => $this->create($user, $quiz, $locale));
        } catch (UniqueConstraintViolationException) {
            return QuizAttempt::where('user_id', $user->id)
                ->where('quiz_id', $quiz->id)
                ->inProgress()
                ->sole();
        }
    }

    private function create(User $user, Quiz $quiz, string $locale): QuizAttempt
    {
        $questions = $quiz->questions()
            ->active()
            ->with([
                'currentVersion.translations',
                'currentVersion.options.translations',
            ])
            ->get();

        if ($quiz->shuffle_questions) {
            $questions = $questions->shuffle();
        }

        $questions = $questions->take($quiz->question_count)->values();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'quiz' => 'This quiz has no active questions yet.',
            ]);
        }

        $attempt = QuizAttempt::create([
            'user_id' => $user->id,
            'quiz_id' => $quiz->id,
            'status' => 'in_progress',
            'locale' => $locale,
            'question_count' => $questions->count(),
            'started_at' => now(),
        ]);

        foreach ($questions as $position => $question) {
            QuizAttemptQuestion::create([
                'quiz_attempt_id' => $attempt->id,
                'question_version_id' => $question->current_version_id,
                'position' => $position,
                'snapshot' => $this->snapshot($question, $quiz->shuffle_options, $locale),
            ]);
        }

        UserJourneyProgress::updateOrCreate(
            ['user_id' => $user->id, 'journey_id' => $quiz->journey_id],
            [],
        )->fill(['status' => 'in_progress'])->save();

        return $attempt;
    }

    /** @return array<string, mixed> */
    private function snapshot(Question $question, bool $shuffleOptions, string $locale): array
    {
        $version = $question->currentVersion;
        $options = $version->options;

        if ($shuffleOptions) {
            $options = $options->shuffle();
        }

        return [
            'question_key' => $question->key,
            'difficulty' => $question->difficulty,
            'stem_md' => $version->t('stem_md', $locale),
            'code_snippet' => $version->t('code_snippet', $locale),
            'code_language' => $version->code_language,
            'options' => $options
                ->map(fn (QuestionOption $option) => [
                    'id' => $option->id,
                    'label_md' => $option->t('label_md', $locale),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @param list<int> $optionIds */
    public function answer(QuizAttempt $attempt, int $position, array $optionIds, ?int $timeSpentMs): AnswerResultData
    {
        if ($attempt->status !== 'in_progress') {
            throw ValidationException::withMessages(['attempt' => 'This attempt is already submitted.']);
        }

        $served = $attempt->attemptQuestions()
            ->where('position', $position)
            ->with(['questionVersion.options.translations', 'questionVersion.translations', 'answer'])
            ->first();

        if ($served === null) {
            throw ValidationException::withMessages(['position' => 'That question is not part of this attempt.']);
        }

        if ($served->answer !== null) {
            throw ValidationException::withMessages(['position' => 'That question is already answered.']);
        }

        $options = $served->questionVersion->options;
        $allowed = $options->pluck('id')->map(fn ($id) => (int) $id)->all();
        $chosen = array_values(array_unique(array_map('intval', $optionIds)));

        if (array_diff($chosen, $allowed) !== []) {
            throw ValidationException::withMessages([
                'optionIds' => 'One of those options does not belong to this question.',
            ]);
        }

        $correctIds = $options->where('is_correct', true)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        sort($chosen);
        $expected = $correctIds;
        sort($expected);
        $isCorrect = $chosen === $expected;

        $question = $served->questionVersion->question;
        $xp = $isCorrect ? $question->xpValue() : 0;

        DB::transaction(function () use ($served, $isCorrect, $timeSpentMs, $chosen, $attempt) {
            $answer = QuizAttemptAnswer::create([
                'quiz_attempt_question_id' => $served->id,
                'is_correct' => $isCorrect,
                'time_spent_ms' => $timeSpentMs,
                'answered_at' => now(),
            ]);

            $answer->selectedOptions()->attach($chosen);

            if ($isCorrect) {
                $attempt->increment('correct_count');
            }
        });

        return new AnswerResultData(
            position: $position,
            isCorrect: $isCorrect,
            correctOptionIds: $correctIds,
            explanation: $served->questionVersion->t('explanation_md', $attempt->locale),
            optionExplanations: $options
                ->mapWithKeys(fn (QuestionOption $option) => [
                    $option->id => (string) $option->t('option_explanation_md', $attempt->locale),
                ])
                ->filter(fn (string $text) => $text !== '')
                ->all(),
            xpAwarded: $xp,
            answeredCount: $this->answeredCount($attempt),
            questionCount: $attempt->question_count,
        );
    }

    public function submit(QuizAttempt $attempt): AttemptResultData
    {
        if ($attempt->status !== 'in_progress') {
            throw ValidationException::withMessages(['attempt' => 'This attempt is already submitted.']);
        }

        $served = $attempt->attemptQuestions()
            ->with(['answer', 'questionVersion.question', 'questionVersion.translations'])
            ->get();

        $unanswered = $served->filter(fn (QuizAttemptQuestion $row) => $row->answer === null);

        if ($unanswered->isNotEmpty()) {
            throw ValidationException::withMessages([
                'attempt' => "Answer every question first; {$unanswered->count()} left.",
            ]);
        }

        $user = $attempt->user;
        $journey = $attempt->quiz->journey;
        $correct = $served->filter(fn (QuizAttemptQuestion $row) => $row->answer->is_correct);
        $score = (int) round($correct->count() / max(1, $served->count()) * 100);
        $passed = $score >= $journey->pass_threshold;

        $xpEarned = $correct->sum(fn (QuizAttemptQuestion $row) => $row->questionVersion->question->xpValue());
        $scheduled = [];

        DB::transaction(function () use (
            $attempt, $served, $correct, $score, $passed, $xpEarned, $user, $journey, &$scheduled
        ) {
            $attempt->forceFill([
                'status' => 'submitted',
                'correct_count' => $correct->count(),
                'score' => $score,
                'submitted_at' => now(),
            ])->save();

            foreach ($served as $row) {
                $questionId = $row->questionVersion->question_id;

                if ($row->answer->is_correct) {
                    $this->reviews->recordSuccess($user->id, $questionId);

                    continue;
                }

                $item = $this->reviews->recordLapse($user->id, $questionId);

                $scheduled[] = [
                    'position' => $row->position,
                    'stem' => (string) ($row->snapshot['stem_md'] ?? ''),
                    'dueAt' => $item->due_at->toIso8601String(),
                ];
            }

            if ($xpEarned > 0) {
                UserXpEvent::create([
                    'user_id' => $user->id,
                    'source_type' => 'quiz_attempt',
                    'source_id' => $attempt->id,
                    'amount' => $xpEarned,
                    'created_at' => now(),
                ]);

                $user->increment('xp_total', $xpEarned);
            }

            $progress = UserJourneyProgress::firstOrNew([
                'user_id' => $user->id,
                'journey_id' => $journey->id,
            ]);

            $progress->fill([
                'attempts_count' => $progress->attempts_count + 1,
                'best_score' => max($progress->best_score ?? 0, $score),
                'status' => $passed || $progress->status === 'completed' ? 'completed' : 'in_progress',
                'completed_at' => $passed ? ($progress->completed_at ?? now()) : $progress->completed_at,
            ])->save();

            $this->streaks->record($user, $served->count(), $correct->count(), $xpEarned);
        });

        $user->refresh();
        $journeyStatus = UserJourneyProgress::where('user_id', $user->id)
            ->where('journey_id', $journey->id)
            ->value('status');

        return new AttemptResultData(
            score: $score,
            correctCount: $correct->count(),
            questionCount: $served->count(),
            passed: $passed,
            xpEarned: $xpEarned,
            xpTotal: $user->xp_total,
            currentStreak: $user->current_streak,
            journeyStatus: (string) $journeyStatus,
            scheduledForReview: $scheduled,
        );
    }

    public function answeredCount(QuizAttempt $attempt): int
    {
        return QuizAttemptAnswer::whereIn(
            'quiz_attempt_question_id',
            $attempt->attemptQuestions()->select('id'),
        )->count();
    }
}
