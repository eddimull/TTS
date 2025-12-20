<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MediaLibraryController;
use App\Http\Controllers\MediaShareController;
use App\Http\Controllers\MediaTagController;

// Authenticated media library routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('media')->group(function () {
        // Media library main routes
        Route::get('/', [MediaLibraryController::class, 'index'])
            ->middleware('media.read')
            ->name('media.index');

        Route::post('/upload', [MediaLibraryController::class, 'upload'])
            ->middleware('media.write')
            ->name('media.upload');

        Route::get('/{media}', [MediaLibraryController::class, 'show'])
            ->middleware('media.read')
            ->name('media.show');

        Route::patch('/{media}', [MediaLibraryController::class, 'update'])
            ->middleware('media.write')
            ->name('media.update');

        Route::delete('/{media}', [MediaLibraryController::class, 'destroy'])
            ->middleware('media.write')
            ->name('media.destroy');

        Route::get('/{media}/serve', [MediaLibraryController::class, 'serve'])
            ->middleware('media.read')
            ->name('media.serve');

        Route::get('/{media}/thumbnail', [MediaLibraryController::class, 'thumbnail'])
            ->middleware('media.read')
            ->name('media.thumbnail');

        Route::get('/{id}/download', [MediaLibraryController::class, 'download'])
            ->middleware('media.read')
            ->name('media.download')
            ->where('id', '.*'); // Allow IDs like 'chart_123', 'contract_456', etc.

        // Share link management
        Route::post('/{media}/share', [MediaShareController::class, 'create'])
            ->middleware('media.write')
            ->name('media.share.create');

        Route::delete('/shares/{share}', [MediaShareController::class, 'destroy'])
            ->middleware('media.write')
            ->name('media.share.destroy');

        // Tag management
        Route::post('/tags', [MediaTagController::class, 'store'])
            ->middleware('media.write')
            ->name('media.tags.store');

        Route::patch('/tags/{tag}', [MediaTagController::class, 'update'])
            ->middleware('media.write')
            ->name('media.tags.update');

        Route::delete('/tags/{tag}', [MediaTagController::class, 'destroy'])
            ->middleware('media.write')
            ->name('media.tags.destroy');

        // Folder management
        Route::post('/folders/create', [MediaLibraryController::class, 'createFolder'])
            ->middleware('media.write')
            ->name('media.folders.create');

        Route::post('/folders/rename', [MediaLibraryController::class, 'renameFolder'])
            ->middleware('media.write')
            ->name('media.folders.rename');

        Route::delete('/folders', [MediaLibraryController::class, 'deleteFolder'])
            ->middleware('media.write')
            ->name('media.folders.delete');

        // Bulk operations
        Route::post('/bulk/move', [MediaLibraryController::class, 'bulkMove'])
            ->middleware('media.write')
            ->name('media.bulk.move');
    });
});

// Public share access (no authentication required)
Route::get('/share/{token}', [MediaShareController::class, 'access'])
    ->name('media.share.access');
Route::post('/share/{token}', [MediaShareController::class, 'access'])
    ->name('media.share.access.post');
