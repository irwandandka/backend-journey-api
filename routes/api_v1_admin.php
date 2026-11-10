<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'permission:manage-content'])->group(function () {
    Route::get('ping', fn (Request $request) => [
        'data' => [
            'ok' => true,
            'user' => $request->user()->email,
        ],
    ])->name('ping');
});
