<?php

use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PageEditorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaDownloadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth', 'verified'])
    ->prefix('beheer/paginas')
    ->name('admin.pages.')
    ->group(function () {
        Route::get('/', [AdminPageController::class, 'index'])->middleware('can:pages.view')->name('index');
        Route::get('/nieuw', [AdminPageController::class, 'create'])->middleware('can:pages.create')->name('create');
        Route::post('/', [AdminPageController::class, 'store'])->middleware('can:pages.create')->name('store');
        Route::get('/{page}/instellingen', [AdminPageController::class, 'edit'])->middleware('can:pages.update')->name('edit');
        Route::patch('/{page}/instellingen', [AdminPageController::class, 'update'])->middleware('can:pages.update')->name('update');
        Route::delete('/{page}', [AdminPageController::class, 'destroy'])->middleware('can:pages.delete')->name('destroy');
        Route::get('/{page}/bewerker', [PageEditorController::class, 'show'])->middleware('can:pages.update')->name('editor');
        Route::post('/{page}/versies', [PageEditorController::class, 'startDraft'])->middleware('can:pages.update')->name('versions.store');
        Route::post('/{page}/versies/{version}/indienen', [PageEditorController::class, 'submit'])->middleware('can:pages.update')->name('versions.submit');
    });

Route::get('/media/{asset}/download', MediaDownloadController::class)
    ->middleware('signed')
    ->name('media.download');

require __DIR__.'/auth.php';

Route::get('/{path?}', PublicPageController::class)
    ->where('path', '.*')
    ->name('public.page');
