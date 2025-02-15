<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuestionnaireController;

Route::middleware(['auth', 'verified'])->prefix('questionnaire')->group(function () {
    Route::get('/', [QuestionnaireController::class, 'index'])->name('questionnaire');
    Route::post('/new', [QuestionnaireController::class, 'store'])->name('questionnaire.new');
    Route::get('/{questionnaire:slug}', [QuestionnaireController::class, 'edit'])->name('questionnaire.edit');
    Route::post('/{questionnaire:slug}/add', [QuestionnaireController::class, 'addQuestion'])->name('questionnaire.addQuestion');
});
