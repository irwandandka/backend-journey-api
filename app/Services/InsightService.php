<?php

namespace App\Services;

use App\Models\Question;
use App\Models\Track;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Where a learner keeps going wrong. Everything is read from answers actually
 * given, so an untouched tag is absent rather than scored zero.
 */
class InsightService
{
    public const HARDEST_LIMIT = 5;

    /** @return array<string, mixed> */
    public function forUser(User $user, ?string $locale = null): array
    {
        return [
            'overall' => $this->overall($user),
            'byTag' => $this->byTag($user),
            'byTrack' => $this->byTrack($user, $locale),
            'hardestQuestions' => $this->hardest($user, $locale),
        ];
    }

    /** @return array{answered: int, correct: int, accuracy: int|null, tracked: int} */
    private function overall(User $user): array
    {
        $row = $this->answers($user)
            ->selectRaw('count(*) as answered')
            ->selectRaw('count(*) filter (where qaa.is_correct) as correct')
            ->first();

        $answered = (int) $row->answered;

        return [
            'answered' => $answered,
            'correct' => (int) $row->correct,
            'accuracy' => $answered === 0 ? null : (int) round(((int) $row->correct) / $answered * 100),
            'tracked' => DB::table('review_items')->where('user_id', $user->id)->count(),
        ];
    }

    /** @return list<array{slug: string, name: string, answered: int, correct: int, accuracy: int}> */
    private function byTag(User $user): array
    {
        $rows = $this->answers($user)
            ->join('question_tag as qt', 'qt.question_id', '=', 'qv.question_id')
            ->join('tags', 'tags.id', '=', 'qt.tag_id')
            ->groupBy('tags.slug', 'tags.name')
            ->selectRaw('tags.slug as slug, tags.name as name')
            ->selectRaw('count(*) as answered')
            ->selectRaw('count(*) filter (where qaa.is_correct) as correct')
            ->get();

        return $this->ranked($rows, fn (object $row) => [
            'slug' => (string) $row->slug,
            'name' => (string) $row->name,
        ]);
    }

    /** @return list<array{slug: string, name: string, answered: int, correct: int, accuracy: int}> */
    private function byTrack(User $user, ?string $locale): array
    {
        $rows = $this->answers($user)
            ->join('questions as q', 'q.id', '=', 'qv.question_id')
            ->groupBy('q.track_id')
            ->selectRaw('q.track_id as track_id')
            ->selectRaw('count(*) as answered')
            ->selectRaw('count(*) filter (where qaa.is_correct) as correct')
            ->get();

        $names = Track::whereIn('id', $rows->pluck('track_id'))
            ->withTranslations($locale)
            ->get()
            ->mapWithKeys(fn (Track $track) => [$track->id => ['slug' => $track->slug, 'name' => (string) $track->t('name', $locale)]]);

        return $this->ranked(
            $rows->filter(fn (object $row) => $names->has($row->track_id)),
            fn (object $row) => $names[$row->track_id],
        );
    }

    /** @return list<array{key: string, stem: string, lapses: int, dueAt: string, trackSlug: string}> */
    private function hardest(User $user, ?string $locale): array
    {
        $items = DB::table('review_items')
            ->where('user_id', $user->id)
            ->where('lapses', '>', 0)
            ->orderByDesc('lapses')
            ->orderBy('due_at')
            ->limit(self::HARDEST_LIMIT)
            ->get(['question_id', 'lapses', 'due_at']);

        if ($items->isEmpty()) {
            return [];
        }

        $questions = Question::whereIn('id', $items->pluck('question_id'))
            ->with(['track:id,slug', 'currentVersion' => fn ($q) => $q->withTranslations($locale)])
            ->get()
            ->keyBy('id');

        return $items
            ->filter(fn (object $item) => $questions->has($item->question_id))
            ->map(function (object $item) use ($questions, $locale) {
                $question = $questions[$item->question_id];

                return [
                    'key' => $question->key,
                    'stem' => (string) ($question->currentVersion?->t('stem_md', $locale) ?? ''),
                    'lapses' => (int) $item->lapses,
                    'dueAt' => now()->parse($item->due_at)->toIso8601String(),
                    'trackSlug' => $question->track->slug,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Every answer this user has given, joined up to the question version that
     * was served. Review answers count too: missing a question in review is
     * exactly the signal this report exists to surface.
     */
    private function answers(User $user): Builder
    {
        return DB::table('quiz_attempt_answers as qaa')
            ->join('quiz_attempt_questions as qaq', 'qaq.id', '=', 'qaa.quiz_attempt_question_id')
            ->join('quiz_attempts as qa', 'qa.id', '=', 'qaq.quiz_attempt_id')
            ->join('question_versions as qv', 'qv.id', '=', 'qaq.question_version_id')
            ->where('qa.user_id', $user->id);
    }

    /**
     * Worst accuracy first, and within equal accuracy the one answered more
     * often, because that is the more certain weakness.
     *
     * @param  Collection<int, object>  $rows
     * @param  callable(object): array{slug: string, name: string}  $label
     * @return list<array{slug: string, name: string, answered: int, correct: int, accuracy: int}>
     */
    private function ranked(Collection $rows, callable $label): array
    {
        return $rows
            ->map(function (object $row) use ($label) {
                $answered = (int) $row->answered;
                $correct = (int) $row->correct;

                return [
                    ...$label($row),
                    'answered' => $answered,
                    'correct' => $correct,
                    'accuracy' => $answered === 0 ? 0 : (int) round($correct / $answered * 100),
                ];
            })
            ->sortBy([['accuracy', 'asc'], ['answered', 'desc']])
            ->values()
            ->all();
    }
}
