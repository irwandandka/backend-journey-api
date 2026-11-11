<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Tag;
use App\Services\ContentGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController
{
    public function __construct(private readonly ContentGuard $guard) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Tag::query()
                ->withCount('questions')
                ->orderBy('slug')
                ->get()
                ->map(fn (Tag $tag) => [
                    'slug' => $tag->slug,
                    'name' => $tag->name,
                    'questionCount' => (int) $tag->questions_count,
                ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $input = $request->validate([
            'slug' => ['required', 'string', 'min:2', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]*$/', 'unique:tags,slug'],
            'name' => ['required', 'string', 'min:2', 'max:60'],
        ]);

        $tag = Tag::create($input);

        return response()->json([
            'data' => ['slug' => $tag->slug, 'name' => $tag->name, 'questionCount' => 0],
        ], 201);
    }

    public function destroy(string $slug): JsonResponse
    {
        $tag = Tag::where('slug', $slug)->withCount('questions')->firstOrFail();

        $this->guard->refuseIf(
            $tag->questions_count > 0,
            'tag',
            "{$slug} is on {$tag->questions_count} questions. Remove it from them first.",
        );

        $tag->delete();

        return response()->json(null, 204);
    }
}
