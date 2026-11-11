<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\AchievementData;
use App\Models\Achievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AchievementController
{
    public function index(Request $request): JsonResponse
    {
        $unlocked = DB::table('user_achievements')
            ->where('user_id', $request->user()->id)
            ->pluck('unlocked_at', 'achievement_id');

        $achievements = Achievement::query()
            ->withTranslations()
            ->orderBy('position')
            ->get();

        return response()->json([
            'data' => $achievements->map(fn (Achievement $achievement) => AchievementData::fromModel(
                $achievement,
                $unlocked->has($achievement->id)
                    ? now()->parse($unlocked[$achievement->id])->toIso8601String()
                    : null,
            )),
        ]);
    }
}
