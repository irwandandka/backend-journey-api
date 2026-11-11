<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController
{
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
}
