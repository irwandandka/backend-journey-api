<?php

use App\Http\Controllers\Api\V1\Admin\OverviewController;
use App\Http\Controllers\Api\V1\Admin\QuestionController;
use App\Http\Controllers\Api\V1\Admin\StructureController;
use App\Http\Controllers\Api\V1\Admin\TagController;
use App\Http\Controllers\Api\V1\Admin\TrackController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:manage-content'])->group(function () {
    Route::get('ping', fn (Request $request) => [
        'data' => [
            'ok' => true,
            'user' => $request->user()->email,
        ],
    ])->name('ping');

    Route::get('overview', [OverviewController::class, 'show'])->name('overview');
    Route::get('export', [OverviewController::class, 'export'])->name('export');

    Route::get('tracks', [TrackController::class, 'index'])->name('tracks.index');
    Route::post('tracks', [StructureController::class, 'storeTrack'])->name('tracks.store');
    Route::put('tracks/{slug}', [StructureController::class, 'updateTrack'])->name('tracks.update');

    Route::post('journeys', [StructureController::class, 'storeJourney'])->name('journeys.store');
    Route::put('journeys/{slug}', [StructureController::class, 'updateJourney'])->name('journeys.update');

    Route::post('quizzes', [StructureController::class, 'storeQuiz'])->name('quizzes.store');
    Route::put('quizzes/{slug}', [StructureController::class, 'updateQuiz'])->name('quizzes.update');
    Route::get('quizzes/{slug}/questions', [StructureController::class, 'showQuizQuestions'])->name('quizzes.questions.show');
    Route::put('quizzes/{slug}/questions', [StructureController::class, 'setQuizQuestions'])->name('quizzes.questions.set');

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');

    Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::post('questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::get('questions/{key}', [QuestionController::class, 'show'])->name('questions.show');
    Route::put('questions/{key}', [QuestionController::class, 'update'])->name('questions.update');
});
