<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\InsightData;
use App\Services\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightController
{
    public function show(Request $request, InsightService $insights): JsonResponse
    {
        return response()->json([
            'data' => new InsightData(...$insights->forUser($request->user(), app()->getLocale())),
        ]);
    }
}
