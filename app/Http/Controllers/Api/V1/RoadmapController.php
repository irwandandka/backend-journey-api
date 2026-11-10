<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\RoadmapData;
use App\Data\TrackSummaryData;
use App\Models\Roadmap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoadmapController
{
    public function show(Request $request): JsonResponse
    {
        $roadmap = Roadmap::query()
            ->withTranslations()
            ->where('is_published', true)
            ->with([
                'tracks' => fn ($query) => $query
                    ->where('is_published', true)
                    ->withTranslations()
                    ->withCount(['journeys' => fn ($inner) => $inner->where('is_published', true)]),
            ])
            ->orderBy('position')
            ->firstOrFail();

        $userId = $request->user()->id;

        $completed = DB::table('user_journey_progress as p')
            ->join('journeys as j', 'j.id', '=', 'p.journey_id')
            ->where('p.user_id', $userId)
            ->where('p.status', 'completed')
            ->groupBy('j.track_id')
            ->selectRaw('j.track_id, count(*) as total')
            ->pluck('total', 'track_id');

        $due = DB::table('review_items as r')
            ->join('questions as q', 'q.id', '=', 'r.question_id')
            ->where('r.user_id', $userId)
            ->where('r.due_at', '<=', now())
            ->groupBy('q.track_id')
            ->selectRaw('q.track_id, count(*) as total')
            ->pluck('total', 'track_id');

        return response()->json([
            'data' => new RoadmapData(
                slug: $roadmap->slug,
                title: (string) $roadmap->t('title'),
                description: $roadmap->t('description'),
                tracks: $roadmap->tracks
                    ->map(fn ($track) => TrackSummaryData::fromModel(
                        $track,
                        (int) ($completed[$track->id] ?? 0),
                        (int) ($due[$track->id] ?? 0),
                    ))
                    ->values()
                    ->all(),
            ),
        ]);
    }
}
