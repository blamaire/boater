<?php

use App\Http\Controllers\Api\MediaProbeController;
use App\Http\Controllers\Api\MediaUploadController;
use App\Http\Controllers\Api\PageImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('import.token')->group(function (): void {
    Route::post('/pages/import', PageImportController::class)->name('api.pages.import');
    Route::post('/media/probe', MediaProbeController::class)->name('api.media.probe');
    Route::post('/media/upload', MediaUploadController::class)->name('api.media.upload');
});
