<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\JourneyData;
use App\Data\TrackDetailData;
use App\Models\Track;
use App\Models\UserJourneyProgress;
use App\Services\JourneyUnlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackController
{
    public function show(Request $request, string $slug, JourneyUnlock $unlock): JsonResponse
    {
        $track = Track::query()
            ->withTranslations()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with([
                'journeys' => fn ($query) => $query
                    ->where('is_published', true)
                    ->withTranslations()
                    ->with(['quizzes' => fn ($inner) => $inner->withTranslations()]),
            ])
            ->firstOrFail();

        $progress = UserJourneyProgress::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('journey_id', $track->journeys->pluck('id'))
            ->get()
            ->keyBy('journey_id');

        return response()->json([
            'data' => new TrackDetailData(
                slug: $track->slug,
                name: (string) $track->t('name'),
                description: $track->t('description'),
                abbreviation: $track->abbreviation,
                colorDark: $track->color_dark,
                colorLight: $track->color_light,
                journeys: $track->journeys
                    ->map(fn ($journey) => JourneyData::fromModel(
                        $journey,
                        $unlock->statusFor($journey, $track->journeys, $progress),
                        $progress->get($journey->id)?->best_score,
                        (int) ($progress->get($journey->id)?->attempts_count ?? 0),
                    ))
                    ->values()
                    ->all(),
            ),
        ]);
    }
}
