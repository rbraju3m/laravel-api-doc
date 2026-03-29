<?php

use Rbr\LaravelApiDocs\Http\Controllers\ApiDocController;
use Rbr\LaravelApiDocs\Http\Controllers\ExternalEndpointController;
use Rbr\LaravelApiDocs\Http\Controllers\ExternalProjectController;
use Rbr\LaravelApiDocs\Http\Middleware\SetInertiaRootView;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return \Inertia\Inertia::render('Home', [
        'title' => config('api-docs.title'),
    ]);
})->middleware(['web', SetInertiaRootView::class]);

Route::prefix('docs/api')->name('api-docs.')->middleware(['web', SetInertiaRootView::class])->group(function () {
    Route::get('/', [ApiDocController::class, 'index'])->name('index');
    Route::get('/endpoints/{endpoint}', [ApiDocController::class, 'show'])->name('show');
    Route::delete('/endpoints/{endpoint}', [ApiDocController::class, 'destroy'])->name('destroy');
    Route::post('/generate', [ApiDocController::class, 'generate'])->name('generate');

    Route::resource('projects', ExternalProjectController::class)->names('projects');

    Route::prefix('projects/{project}')->name('projects.')->group(function () {
        Route::post('generate', [ExternalProjectController::class, 'generate'])->name('generate');
        Route::get('endpoints/create', [ExternalEndpointController::class, 'create'])->name('endpoints.create');
        Route::post('endpoints', [ExternalEndpointController::class, 'store'])->name('endpoints.store');
        Route::get('endpoints/{endpoint}/edit', [ExternalEndpointController::class, 'edit'])->name('endpoints.edit');
        Route::put('endpoints/{endpoint}', [ExternalEndpointController::class, 'update'])->name('endpoints.update');
        Route::delete('endpoints/{endpoint}', [ExternalEndpointController::class, 'destroy'])->name('endpoints.destroy');
    });
});
