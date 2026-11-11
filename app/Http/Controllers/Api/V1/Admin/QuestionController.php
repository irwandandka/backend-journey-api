<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Data\Admin\QuestionDetailData;
use App\Data\Admin\QuestionInputData;
use App\Data\Admin\QuestionSummaryData;
use App\Http\Middleware\SetLocale;
use App\Models\Question;
use App\Services\QuestionWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestionController
{
    public function __construct(private readonly QuestionWriter $writer) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'track' => ['nullable', 'string', 'exists:tracks,slug'],
            'tag' => ['nullable', 'string', 'exists:tags,slug'],
            'difficulty' => ['nullable', 'string', 'in:'.implode(',', Question::DIFFICULTIES)],
            'missingLocale' => ['nullable', 'string', 'in:'.implode(',', SetLocale::supportedLocales())],
            'q' => ['nullable', 'string', 'max:120'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $locales = SetLocale::supportedLocales();

        $query = Question::query()
            ->with([
                'track:id,slug',
                'tags:id,slug',
                'currentVersion.translations:id,question_version_id,locale,stem_md',
                'currentVersion.options:id,question_version_id',
            ])
            ->withCount('quizzes')
            ->orderBy('key');

        $query->when($request->string('track')->toString(), fn ($q, string $slug) => $q->whereRelation('track', 'slug', $slug));

        $query->when(
            $request->string('tag')->toString(),
            fn ($q, string $slug) => $q->whereHas('tags', fn ($inner) => $inner->where('slug', $slug)),
        );

        $query->when($request->string('difficulty')->toString(), fn ($q, string $value) => $q->where('difficulty', $value));

        $query->when(
            $request->string('missingLocale')->toString(),
            fn ($q, string $locale) => $q->whereDoesntHave(
                'currentVersion.translations',
                fn ($inner) => $inner->where('locale', $locale),
            ),
        );

        $query->when(
            $request->string('q')->toString(),
            fn ($q, string $term) => $q->where(fn ($inner) => $inner
                ->where('key', 'ilike', '%'.$term.'%')
                ->orWhereHas(
                    'currentVersion.translations',
                    fn ($t) => $t->where('stem_md', 'ilike', '%'.$term.'%'),
                )),
        );

        $page = $query->paginate(perPage: $request->integer('perPage') ?: 25);

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn (Question $question) => QuestionSummaryData::fromModel($question, $locales))
                ->values(),
            'meta' => [
                'page' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }

    public function show(string $key): JsonResponse
    {
        return response()->json(['data' => QuestionDetailData::fromModel($this->find($key))]);
    }

    public function store(QuestionInputData $data): JsonResponse
    {
        if (Question::where('key', $data->key)->exists()) {
            throw ValidationException::withMessages(['key' => "A question with the key {$data->key} already exists."]);
        }

        $question = $this->writer->save($data->toWriterRow());

        return response()->json(['data' => QuestionDetailData::fromModel($this->find($question->key))], 201);
    }

    public function update(string $key, QuestionInputData $data): JsonResponse
    {
        $existing = $this->find($key);

        if ($data->key !== $key) {
            throw ValidationException::withMessages([
                'key' => 'A question key is the stable authoring id and cannot be changed.',
            ]);
        }

        $before = $existing->current_version_id;
        $question = $this->writer->save($data->toWriterRow());
        $fresh = $this->find($question->key);

        return response()->json([
            'data' => QuestionDetailData::fromModel($fresh),
            'meta' => ['newVersion' => $question->current_version_id !== $before],
        ]);
    }

    private function find(string $key): Question
    {
        return Question::where('key', $key)
            ->with([
                'track:id,slug',
                'tags:id,slug',
                'versions' => fn ($q) => $q->withCount('attemptQuestions'),
                'currentVersion.translations',
                'currentVersion.options.translations',
            ])
            ->withCount('quizzes')
            ->firstOrFail();
    }
}
