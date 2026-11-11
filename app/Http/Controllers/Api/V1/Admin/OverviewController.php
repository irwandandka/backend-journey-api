<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Data\Admin\OverviewData;
use App\Http\Middleware\SetLocale;
use App\Models\Journey;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Track;
use App\Services\ContentExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OverviewController
{
    public function export(Request $request, ContentExporter $exporter): JsonResponse
    {
        $request->validate(['roadmap' => ['nullable', 'string', 'exists:roadmaps,slug']]);

        $slug = $request->string('roadmap')->toString() ?: null;
        $content = $exporter->export($slug);
        $name = $content['roadmap']['slug'];

        return response()
            ->json($content, options: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Disposition', "attachment; filename=\"{$name}.json\"");
    }

    public function show(): JsonResponse
    {
        $locales = SetLocale::supportedLocales();

        $journeys = DB::table('journeys')
            ->leftJoin('quizzes', 'quizzes.journey_id', '=', 'journeys.id')
            ->selectRaw('count(distinct journeys.id) as total')
            ->selectRaw('count(distinct journeys.id) filter (where quizzes.id is null) as without_quiz')
            ->selectRaw('count(distinct journeys.id) filter (where journeys.is_published) as published')
            ->first();

        $questions = DB::table('questions')
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where is_active) as active')
            ->selectRaw('count(*) filter (where current_version_id is null) as without_version')
            ->first();

        return response()->json([
            'data' => new OverviewData(
                tracks: [
                    'total' => Track::count(),
                    'published' => Track::where('is_published', true)->count(),
                ],
                journeys: [
                    'total' => (int) $journeys->total,
                    'published' => (int) $journeys->published,
                    'withoutQuiz' => (int) $journeys->without_quiz,
                ],
                quizzes: ['total' => Quiz::count()],
                questions: [
                    'total' => (int) $questions->total,
                    'active' => (int) $questions->active,
                    'withoutVersion' => (int) $questions->without_version,
                ],
                translationCoverage: $this->coverage($locales),
                emptyJourneys: Journey::query()
                    ->whereDoesntHave('quizzes')
                    ->with('track:id,slug')
                    ->orderBy('track_id')
                    ->orderBy('position')
                    ->get()
                    ->map(fn (Journey $journey) => [
                        'slug' => $journey->slug,
                        'trackSlug' => $journey->track->slug,
                        'position' => $journey->position,
                        'tier' => $journey->tier,
                    ])
                    ->values()
                    ->all(),
            ),
        ]);
    }

    /**
     * How many current question versions carry each locale. This is the number
     * that tells the author what is left to translate.
     *
     * @param  list<string>  $locales
     * @return list<array{locale: string, translated: int, total: int}>
     */
    private function coverage(array $locales): array
    {
        $total = Question::whereNotNull('current_version_id')->count();

        $counts = DB::table('question_version_translations as t')
            ->join('questions', 'questions.current_version_id', '=', 't.question_version_id')
            ->groupBy('t.locale')
            ->selectRaw('t.locale as locale, count(*) as translated')
            ->pluck('translated', 'locale');

        return array_map(fn (string $locale) => [
            'locale' => $locale,
            'translated' => (int) ($counts[$locale] ?? 0),
            'total' => $total,
        ], $locales);
    }
}
