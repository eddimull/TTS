<?php

use App\Http\Controllers\QuestionnairesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('bands/{band}/questionnaires')->group(function () {
    Route::get('/', [QuestionnairesController::class, 'index'])->name('questionnaires.index');
    Route::post('/', [QuestionnairesController::class, 'store'])->name('questionnaires.store');
    Route::get('/{questionnaire:slug}/edit', [QuestionnairesController::class, 'edit'])->name('questionnaires.edit');
    Route::put('/{questionnaire:slug}', [QuestionnairesController::class, 'update'])->name('questionnaires.update');
    Route::get('/{questionnaire:slug}/preview', [QuestionnairesController::class, 'preview'])->name('questionnaires.preview');
    Route::post('/{questionnaire:slug}/archive', [QuestionnairesController::class, 'archive'])->name('questionnaires.archive');
    Route::post('/{questionnaire:slug}/restore', [QuestionnairesController::class, 'restore'])->name('questionnaires.restore');
    Route::delete('/{questionnaire:slug}', [QuestionnairesController::class, 'destroy'])->name('questionnaires.destroy');
});
