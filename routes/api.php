<?php

use App\Http\Controllers\Api\PageImportController;
use Illuminate\Support\Facades\Route;

Route::middleware('import.token')->group(function (): void {
    Route::post('/pages/import', PageImportController::class)->name('api.pages.import');
});
