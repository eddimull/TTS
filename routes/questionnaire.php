<?php

use App\Http\Controllers\QuestionnairesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/questionnaires', [QuestionnairesController::class, 'index'])->name('questionnaires.index');
    Route::post('/questionnaires', [QuestionnairesController::class, 'store'])->name('questionnaires.store');

    Route::prefix('bands/{band}/questionnaires')->group(function () {
        Route::get('/{questionnaire:slug}/edit', [QuestionnairesController::class, 'edit'])->name('questionnaires.edit');
        Route::get('/{questionnaire:slug}/preview', [QuestionnairesController::class, 'preview'])->name('questionnaires.preview');
        Route::get('/{questionnaire:slug}', [QuestionnairesController::class, 'show'])->name('questionnaires.show');
        Route::put('/{questionnaire:slug}', [QuestionnairesController::class, 'update'])->name('questionnaires.update');
        Route::post('/{questionnaire:slug}/archive', [QuestionnairesController::class, 'archive'])->name('questionnaires.archive');
        Route::post('/{questionnaire:slug}/restore', [QuestionnairesController::class, 'restore'])->name('questionnaires.restore');
        Route::delete('/{questionnaire:slug}', [QuestionnairesController::class, 'destroy'])->name('questionnaires.destroy');
    });
});
