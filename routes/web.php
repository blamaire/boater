<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\FailedJobsController;
use App\Http\Controllers\Admin\PageConflictController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\PageEditorController;
use App\Http\Controllers\Admin\PageHistoryController;
use App\Http\Controllers\Admin\PagePushController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MediaDownloadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicPageController;
use App\Livewire\Admin\ActiviteitBeheer;
use App\Livewire\Admin\ActivityCategoryBeheer;
use App\Livewire\Admin\EnvironmentBeheer;
use App\Livewire\Admin\GebruikerBeheer;
use App\Livewire\Admin\MenuBeheer;
use App\Livewire\Admin\PersonPermissionBeheer;
use App\Livewire\Admin\SiteInstellingen;
use App\Livewire\Portal\MijnLidmaatschap;
use App\Livewire\Public\LidWorden;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])
    ->prefix('mijn')
    ->name('portal.')
    ->group(function () {
        Route::get('/lidmaatschap', MijnLidmaatschap::class)->name('mijn-lidmaatschap');
    });

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

        Route::get('/{page}/versies/{version}/conflict/{other}', [PageConflictController::class, 'show'])
            ->middleware('can:pages.update')
            ->name('conflict.show');

        Route::post('/{page}/push', PagePushController::class)->middleware('can:pages.push')->name('push');

        Route::get('/{page}/historie', [PageHistoryController::class, 'index'])->middleware('can:pages.view')->name('history');
        Route::get('/{page}/historie/{version}/diff/{other}', [PageHistoryController::class, 'diff'])->middleware('can:pages.view')->name('history.diff');
        Route::post('/{page}/historie/{version}/herstellen', [PageHistoryController::class, 'restore'])->middleware('can:pages.update')->name('history.restore');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('beheer/rollen')
    ->name('admin.roles.')
    ->group(function () {
        Route::get('/', [RoleController::class, 'index'])->middleware('can:roles.view')->name('index');
        Route::get('/nieuw', [RoleController::class, 'create'])->middleware('can:roles.create')->name('create');
        Route::post('/', [RoleController::class, 'store'])->middleware('can:roles.create')->name('store');
        Route::get('/{role}/bewerken', [RoleController::class, 'edit'])->middleware('can:roles.update')->name('edit');
        Route::patch('/{role}', [RoleController::class, 'update'])->middleware('can:roles.update')->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->middleware('can:roles.delete')->name('destroy');
    });

Route::middleware(['auth', 'verified', 'can:users.manage'])
    ->get('/beheer/gebruikers', GebruikerBeheer::class)
    ->name('admin.users.index');

Route::middleware(['auth', 'verified', 'can:users.manage'])
    ->get('/beheer/personen/{person}/rechten', PersonPermissionBeheer::class)
    ->name('admin.person-permissions.index');

Route::middleware(['auth', 'verified', 'can:activities.view'])
    ->get('/beheer/activiteiten', ActiviteitBeheer::class)
    ->name('admin.activities.index');

Route::middleware(['auth', 'verified', 'can:activities.update'])
    ->get('/beheer/activiteiten/categorieen', ActivityCategoryBeheer::class)
    ->name('admin.activity-categories.index');

Route::middleware(['auth', 'verified', 'can:menu.manage'])
    ->get('/beheer/menu', MenuBeheer::class)
    ->name('admin.menu');

Route::middleware(['auth', 'verified', 'can:site_settings.manage'])
    ->get('/beheer/instellingen', SiteInstellingen::class)
    ->name('admin.site-settings');

Route::middleware(['auth', 'verified', 'can:environments.manage'])
    ->get('/beheer/omgevingen', EnvironmentBeheer::class)
    ->name('admin.environments');

Route::view('/beheer/media', 'admin.media')
    ->middleware(['auth', 'verified', 'can:media.view'])
    ->name('admin.media');

Route::middleware(['auth', 'verified', 'can:queue.manage'])
    ->prefix('beheer/failed-jobs')
    ->name('admin.failed-jobs.')
    ->group(function () {
        Route::get('/', [FailedJobsController::class, 'index'])->name('index');
        Route::post('/{uuid}/opnieuw', [FailedJobsController::class, 'retry'])->name('retry');
        Route::delete('/{uuid}', [FailedJobsController::class, 'destroy'])->name('destroy');
    });

Route::get('/media/{asset}/download', MediaDownloadController::class)
    ->middleware('signed')
    ->name('media.download');

require __DIR__.'/auth.php';

Route::get('/', [PublicPageController::class, 'home'])->name('public.home');
Route::get('/lid-worden', LidWorden::class)->name('lid-worden');
Route::get('/activiteit/{activity}', [ActivityController::class, 'show'])->name('activiteit.show');
Route::get('/pagina/{path}', PublicPageController::class)
    ->where('path', '.*')
    ->name('public.page');
