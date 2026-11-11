<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\JourneySearchData;
use App\Models\Journey;
use Illuminate\Http\JsonResponse;

class JourneyController
{
    /**
     * Every published journey, flat, for the search palette. Deliberately
     * without unlock status: resolving that per journey is the expensive part
     * of the track page, and a search result only needs to be findable.
     */
    public function index(): JsonResponse
    {
        $journeys = Journey::query()
            ->where('is_published', true)
            ->whereRelation('track', 'is_published', true)
            ->withTranslations()
            ->withCount('quizzes')
            ->with(['track' => fn ($q) => $q->withTranslations()])
            ->orderBy('track_id')
            ->orderBy('position')
            ->get();

        return response()->json([
            'data' => $journeys->map(JourneySearchData::fromModel(...)),
        ]);
    }
}
