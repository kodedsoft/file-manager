<?php

use App\Http\Controllers\Files\FileController;
use App\Http\Controllers\Files\ChunkUploadController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});
Route::get('/files', [FileController::class, 'index'])->name('files.index');
Route::get('/files/{file}/preview', [FileController::class, 'preview'])->name('files.preview');
Route::get('/files/preview-by-name', [FileController::class, 'previewByFilename'])->name('files.previewByName');
Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Chunked upload endpoints
Route::prefix('upload')->group(function () {
    Route::post('/', [ChunkUploadController::class, 'direct']);
    Route::post('/init', [ChunkUploadController::class, 'init']);
    Route::post('/chunk', [ChunkUploadController::class, 'chunk']);
    Route::post('/complete', [ChunkUploadController::class, 'complete']);
});

require __DIR__.'/auth.php';
