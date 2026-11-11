<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Data\Admin\RoadmapData;
use App\Data\Admin\RoadmapInputData;
use App\Models\Roadmap;
use App\Services\ContentGuard;
use App\Services\TranslationWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoadmapController
{
    public function __construct(
        private readonly TranslationWriter $translations,
        private readonly ContentGuard $guard,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Roadmap::withTranslations()
                ->withCount('tracks')
                ->orderBy('position')
                ->get()
                ->map(RoadmapData::fromModel(...)),
        ]);
    }

    public function store(RoadmapInputData $data): JsonResponse
    {
        $this->guard->assertFallbackLocale($data->translations, 'translations');

        if (Roadmap::where('slug', $data->slug)->exists()) {
            throw ValidationException::withMessages(['slug' => "A roadmap with the slug {$data->slug} already exists."]);
        }

        $roadmap = DB::transaction(function () use ($data) {
            $roadmap = Roadmap::create([
                'slug' => $data->slug,
                'position' => $data->position,
                'is_published' => $data->isPublished,
            ]);

            $this->translations->replace($roadmap, $data->translationRows());

            return $roadmap;
        });

        return response()->json(['data' => RoadmapData::fromModel($roadmap->loadCount('tracks'))], 201);
    }

    public function update(string $slug, RoadmapInputData $data): JsonResponse
    {
        $roadmap = Roadmap::where('slug', $slug)->firstOrFail();

        $this->guard->assertFallbackLocale($data->translations, 'translations');
        $this->guard->assertSameSlug($data->slug, $slug, 'roadmap');

        DB::transaction(function () use ($data, $roadmap) {
            $roadmap->update(['position' => $data->position, 'is_published' => $data->isPublished]);

            $this->translations->replace($roadmap, $data->translationRows());
        });

        return response()->json(['data' => RoadmapData::fromModel($roadmap->fresh()->loadCount('tracks'))]);
    }

    public function destroy(string $slug): JsonResponse
    {
        $roadmap = Roadmap::where('slug', $slug)->withCount('tracks')->firstOrFail();

        $this->guard->refuseIf(
            $roadmap->tracks_count > 0,
            'roadmap',
            "{$slug} still has {$roadmap->tracks_count} tracks. Delete or move them first.",
        );

        $roadmap->delete();

        return response()->json(null, 204);
    }
}
