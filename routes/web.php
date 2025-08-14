<?php

use App\Http\Controllers\OrganisationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::group(['middleware' => ['policy:viewInFrontend,Organisation']], function () {
    Route::get('/', [OrganisationController::class, 'home'])->name('home');
    Route::get('/overview/{year?}', [OrganisationController::class, 'overview'])->name('overview')->middleware('organisation.set');
    Route::get('/priorities', [OrganisationController::class, 'priorities'])->name('priorities')->middleware('organisation.set');
    Route::get('/calendar', [OrganisationController::class, 'calendar'])->name('calendar')->middleware('organisation.set');
    Route::get('/indicators', [OrganisationController::class, 'indicators'])
        ->name('indicators')
        ->middleware(['organisation.set', 'throttle:10,1']);
    Route::get('/inactive', [OrganisationController::class, 'inactive'])->name('inactive');
    Route::get('/set', [OrganisationController::class, 'set'])->name('set');
});