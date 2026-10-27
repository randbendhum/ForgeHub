<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CatalogueController;
use App\Http\Controllers\DeveloperController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ModController;
use App\Http\Controllers\ModVersionController;
use App\Http\Controllers\UploadController;
use Illuminate\Support\Facades\Route;

// Public Catalogue & Creator Profiles
Route::get('/', [CatalogueController::class, 'index'])->name('catalogue.index');
Route::get('/mods/{mod:slug}', [ModController::class, 'show'])->name('mods.show');
Route::get('/mods/{mod:slug}/versions/{version}/download', [ModVersionController::class, 'download'])
    ->name('versions.download');
Route::get('/versions/{version}/report', [ModVersionController::class, 'report'])
    ->name('versions.report');
Route::get('/developers/{developer:username}', [DeveloperController::class, 'show'])
    ->name('developers.show');

// Authentication (Guests)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
});

// Authenticated Creator Terminal
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::redirect('/inventario', '/inventory');

    Route::get('/upload', [UploadController::class, 'create'])->name('upload.create');
    Route::post('/upload/validate', [UploadController::class, 'validateFile'])
        ->middleware('throttle:30,1')
        ->name('upload.validate');
    Route::post('/upload', [UploadController::class, 'store'])
        ->middleware('throttle:15,1')
        ->name('upload.store');

    Route::delete('/versions/{version}', [ModVersionController::class, 'destroy'])
        ->name('versions.destroy');
});
