<?php

use App\Http\Controllers\Api\V1\AchievementController;
use App\Http\Controllers\Api\V1\AttemptController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\JourneyController;
use App\Http\Controllers\Api\V1\ReviewController;
use App\Http\Controllers\Api\V1\RoadmapController;
use App\Http\Controllers\Api\V1\TrackController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:10,1')
        ->name('register');

    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('roadmap', [RoadmapController::class, 'show'])->name('roadmap.show');
    Route::get('tracks/{slug}', [TrackController::class, 'show'])->name('tracks.show');
    Route::get('journeys', [JourneyController::class, 'index'])->name('journeys.index');

    Route::get('achievements', [AchievementController::class, 'index'])->name('achievements.index');

    Route::get('review', [ReviewController::class, 'summary'])->name('review.summary');
    Route::post('review/attempts', [ReviewController::class, 'store'])->name('review.store');

    Route::post('quizzes/{quizSlug}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
    Route::get('attempts/{attemptId}/questions', [AttemptController::class, 'questions'])->name('attempts.questions');
    Route::post('attempts/{attemptId}/answers', [AttemptController::class, 'answer'])->name('attempts.answer');
    Route::post('attempts/{attemptId}/submit', [AttemptController::class, 'submit'])->name('attempts.submit');
});
