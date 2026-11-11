<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Data\Admin\TrackTreeData;
use App\Models\Track;
use Illuminate\Http\JsonResponse;

class TrackController
{
    public function index(): JsonResponse
    {
        $tracks = Track::query()
            ->withTranslations()
            ->withCount('questions')
            ->with([
                'journeys' => fn ($q) => $q
                    ->orderBy('position')
                    ->withTranslations()
                    ->with('quizzes:id,journey_id,slug,question_count,position'),
            ])
            ->orderBy('position')
            ->get();

        return response()->json([
            'data' => $tracks->map(TrackTreeData::fromModel(...)),
        ]);
    }
}
