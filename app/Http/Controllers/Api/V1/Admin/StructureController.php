<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Data\Admin\JourneyInputData;
use App\Data\Admin\QuizInputData;
use App\Data\Admin\TrackInputData;
use App\Data\Admin\TrackTreeData;
use App\Http\Middleware\SetLocale;
use App\Models\Journey;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Roadmap;
use App\Models\Track;
use App\Services\TranslationWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StructureController
{
    public function __construct(private readonly TranslationWriter $translations) {}

    public function storeTrack(TrackInputData $data): JsonResponse
    {
        $this->assertFallbackLocale($data->translations, 'translations');
        $this->assertFree(Track::class, $data->slug, 'track');

        $roadmap = Roadmap::orderBy('position')->first()
            ?? throw ValidationException::withMessages(['slug' => 'There is no roadmap to attach a track to.']);

        $track = DB::transaction(function () use ($data, $roadmap) {
            $track = Track::create([
                'roadmap_id' => $roadmap->id,
                'slug' => $data->slug,
                'abbreviation' => strtoupper($data->abbreviation),
                'color_dark' => strtolower($data->colorDark),
                'color_light' => strtolower($data->colorLight),
                'position' => $data->position,
                'is_published' => $data->isPublished,
            ]);

            $this->translations->replace($track, $data->translationRows());

            return $track;
        });

        return response()->json(['data' => $this->tree($track->slug)], 201);
    }

    public function updateTrack(string $slug, TrackInputData $data): JsonResponse
    {
        $track = Track::where('slug', $slug)->firstOrFail();

        $this->assertFallbackLocale($data->translations, 'translations');
        $this->assertSameSlug($data->slug, $slug, 'track');

        DB::transaction(function () use ($data, $track) {
            $track->update([
                'abbreviation' => strtoupper($data->abbreviation),
                'color_dark' => strtolower($data->colorDark),
                'color_light' => strtolower($data->colorLight),
                'position' => $data->position,
                'is_published' => $data->isPublished,
            ]);

            $this->translations->replace($track, $data->translationRows());
        });

        return response()->json(['data' => $this->tree($track->slug)]);
    }

    public function storeJourney(JourneyInputData $data): JsonResponse
    {
        $this->assertFallbackLocale($data->translations, 'translations');
        $this->assertFree(Journey::class, $data->slug, 'journey');

        $track = Track::where('slug', $data->trackSlug)->sole();

        $journey = DB::transaction(function () use ($data, $track) {
            $journey = Journey::create([
                'track_id' => $track->id,
                'slug' => $data->slug,
                'tier' => $data->tier,
                'unlock_rule' => $data->unlockRule,
                'position' => $data->position,
                'pass_threshold' => $data->passThreshold,
                'is_published' => $data->isPublished,
            ]);

            $this->translations->replace($journey, $data->translationRows());

            return $journey;
        });

        return response()->json(['data' => $this->tree($journey->track->slug)], 201);
    }

    public function updateJourney(string $slug, JourneyInputData $data): JsonResponse
    {
        $journey = Journey::where('slug', $slug)->firstOrFail();

        $this->assertFallbackLocale($data->translations, 'translations');
        $this->assertSameSlug($data->slug, $slug, 'journey');

        $track = Track::where('slug', $data->trackSlug)->sole();

        DB::transaction(function () use ($data, $journey, $track) {
            $journey->update([
                'track_id' => $track->id,
                'tier' => $data->tier,
                'unlock_rule' => $data->unlockRule,
                'position' => $data->position,
                'pass_threshold' => $data->passThreshold,
                'is_published' => $data->isPublished,
            ]);

            $this->translations->replace($journey, $data->translationRows());
        });

        return response()->json(['data' => $this->tree($track->slug)]);
    }

    public function storeQuiz(QuizInputData $data): JsonResponse
    {
        $this->assertFallbackLocale($data->translations, 'translations');
        $this->assertFree(Quiz::class, $data->slug, 'quiz');

        $journey = Journey::where('slug', $data->journeySlug)->sole();

        $quiz = DB::transaction(function () use ($data, $journey) {
            $quiz = Quiz::create([
                'journey_id' => $journey->id,
                'slug' => $data->slug,
                'question_count' => $data->questionCount,
                'time_limit_s' => $data->timeLimitS,
                'shuffle_questions' => $data->shuffleQuestions,
                'shuffle_options' => $data->shuffleOptions,
                'position' => $data->position,
            ]);

            $this->translations->replace($quiz, $data->translationRows());

            return $quiz;
        });

        return response()->json(['data' => $this->tree($quiz->journey->track->slug)], 201);
    }

    public function updateQuiz(string $slug, QuizInputData $data): JsonResponse
    {
        $quiz = Quiz::where('slug', $slug)->firstOrFail();

        $this->assertFallbackLocale($data->translations, 'translations');
        $this->assertSameSlug($data->slug, $slug, 'quiz');

        $attached = $quiz->questions()->count();

        if ($attached > 0 && $data->questionCount > $attached) {
            throw ValidationException::withMessages([
                'questionCount' => "This quiz has {$attached} questions attached, so it cannot serve {$data->questionCount}.",
            ]);
        }

        $journey = Journey::where('slug', $data->journeySlug)->sole();

        DB::transaction(function () use ($data, $quiz, $journey) {
            $quiz->update([
                'journey_id' => $journey->id,
                'question_count' => $data->questionCount,
                'time_limit_s' => $data->timeLimitS,
                'shuffle_questions' => $data->shuffleQuestions,
                'shuffle_options' => $data->shuffleOptions,
                'position' => $data->position,
            ]);

            $this->translations->replace($quiz, $data->translationRows());
        });

        return response()->json(['data' => $this->tree($journey->track->slug)]);
    }

    public function showQuizQuestions(string $slug): JsonResponse
    {
        $quiz = Quiz::where('slug', $slug)->with('questions:id,key')->firstOrFail();

        return response()->json([
            'data' => [
                'slug' => $quiz->slug,
                'questionCount' => (int) $quiz->question_count,
                'keys' => $quiz->questions->pluck('key')->values(),
            ],
        ]);
    }

    public function setQuizQuestions(string $slug, Request $request): JsonResponse
    {
        $quiz = Quiz::where('slug', $slug)->firstOrFail();

        $input = $request->validate([
            'keys' => ['present', 'array', 'max:50'],
            'keys.*' => ['required', 'string', 'distinct', 'exists:questions,key'],
        ]);

        $keys = array_values($input['keys']);

        if ($keys !== [] && count($keys) < $quiz->question_count) {
            throw ValidationException::withMessages([
                'keys' => "This quiz serves {$quiz->question_count} questions, so it needs at least that many attached.",
            ]);
        }

        $ids = Question::whereIn('key', $keys)->pluck('id', 'key');

        $quiz->questions()->sync(
            collect($keys)
                ->mapWithKeys(fn (string $key, int $position) => [$ids[$key] => ['position' => $position]])
                ->all(),
        );

        return $this->showQuizQuestions($slug);
    }

    private function tree(string $trackSlug): TrackTreeData
    {
        $track = Track::where('slug', $trackSlug)
            ->withTranslations()
            ->withCount('questions')
            ->with([
                'journeys' => fn ($q) => $q
                    ->orderBy('position')
                    ->withTranslations()
                    ->with('quizzes:id,journey_id,slug,question_count,position'),
            ])
            ->sole();

        return TrackTreeData::fromModel($track);
    }

    /** @param array<string, mixed> $translations */
    private function assertFallbackLocale(array $translations, string $field): void
    {
        $supported = SetLocale::supportedLocales();
        $fallback = (string) (config('app.fallback_locale') ?: 'en');

        foreach (array_keys($translations) as $locale) {
            if (! in_array($locale, $supported, true)) {
                throw ValidationException::withMessages([$field => "Locale {$locale} is not supported."]);
            }
        }

        if (! array_key_exists($fallback, $translations)) {
            throw ValidationException::withMessages([
                $field => "The {$fallback} translation is required, because every other locale falls back to it.",
            ]);
        }
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function assertFree(string $model, string $slug, string $label): void
    {
        if ($model::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => "A {$label} with the slug {$slug} already exists."]);
        }
    }

    private function assertSameSlug(string $sent, string $current, string $label): void
    {
        if ($sent !== $current) {
            throw ValidationException::withMessages([
                'slug' => "A {$label} slug is a stable id and cannot be changed.",
            ]);
        }
    }
}
