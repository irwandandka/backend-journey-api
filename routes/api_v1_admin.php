<?php

use App\Http\Controllers\Api\V1\Admin\OverviewController;
use App\Http\Controllers\Api\V1\Admin\QuestionController;
use App\Http\Controllers\Api\V1\Admin\TagController;
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

    Route::get('tags', [TagController::class, 'index'])->name('tags.index');
    Route::post('tags', [TagController::class, 'store'])->name('tags.store');

    Route::get('questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::post('questions', [QuestionController::class, 'store'])->name('questions.store');
    Route::get('questions/{key}', [QuestionController::class, 'show'])->name('questions.show');
    Route::put('questions/{key}', [QuestionController::class, 'update'])->name('questions.update');
});
