<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RosterController;
use App\Http\Controllers\RosterMemberController;
use App\Http\Controllers\EventMemberController;
use App\Http\Controllers\SubstituteCallListController;
use App\Http\Controllers\BandRoleController;

Route::middleware(['auth', 'verified'])->group(function () {
    // Roster management
    Route::get('/bands/{band}/rosters', [RosterController::class, 'index'])->name('bands.rosters.index');
    Route::post('/bands/{band}/rosters', [RosterController::class, 'store'])->name('bands.rosters.store');
    Route::post('/bands/{band}/rosters/initialize', [RosterController::class, 'initializeFromBand'])->name('bands.rosters.initialize');
    Route::get('/rosters/{roster}', [RosterController::class, 'show'])->name('rosters.show');
    Route::patch('/rosters/{roster}', [RosterController::class, 'update'])->name('rosters.update');
    Route::delete('/rosters/{roster}', [RosterController::class, 'destroy'])->name('rosters.destroy');
    Route::post('/bands/{band}/rosters/{roster}/set-default', [RosterController::class, 'setDefault'])->name('rosters.setDefault');

    // Band role management
    Route::get('/bands/{band}/roles/manage', [BandRoleController::class, 'page'])->name('bands.roles.page');
    Route::get('/bands/{band}/roles', [BandRoleController::class, 'index'])->name('bands.roles.index');
    Route::post('/bands/{band}/roles', [BandRoleController::class, 'store'])->name('bands.roles.store');
    Route::patch('/bands/{band}/roles/{role}', [BandRoleController::class, 'update'])->name('bands.roles.update');
    Route::delete('/bands/{band}/roles/{role}', [BandRoleController::class, 'destroy'])->name('bands.roles.destroy');
    Route::post('/bands/{band}/roles/reorder', [BandRoleController::class, 'reorder'])->name('bands.roles.reorder');

    // Roster member management
    Route::post('/rosters/{roster}/members', [RosterMemberController::class, 'store'])->name('rosters.members.store');
    Route::patch('/roster-members/{rosterMember}', [RosterMemberController::class, 'update'])->name('rosters.members.update');
    Route::delete('/roster-members/{rosterMember}', [RosterMemberController::class, 'destroy'])->name('rosters.members.destroy');
    Route::post('/roster-members/{rosterMember}/toggle-active', [RosterMemberController::class, 'toggleActive'])->name('rosters.members.toggleActive');

    // Event member management
    Route::get('/events/{event}/members', [EventMemberController::class, 'index'])->name('events.members.index');
    Route::post('/events/{event}/members', [EventMemberController::class, 'store'])->name('events.members.store');
    Route::patch('/event-members/{eventMember}', [EventMemberController::class, 'update'])->name('events.members.update');
    Route::delete('/event-members/{eventMember}', [EventMemberController::class, 'destroy'])->name('events.members.destroy');

    // Event roster assignment (apply template)
    Route::patch('/events/{event}/roster', [EventMemberController::class, 'updateRoster'])->name('events.roster.update');

    // Substitute call lists
    Route::get('/bands/{band}/substitute-call-lists', [SubstituteCallListController::class, 'index'])->name('bands.substitute-call-lists.index');
    Route::post('/bands/{band}/substitute-call-lists', [SubstituteCallListController::class, 'store'])->name('bands.substitute-call-lists.store');
    Route::patch('/substitute-call-lists/{substituteCallList}', [SubstituteCallListController::class, 'update'])->name('substitute-call-lists.update');
    Route::delete('/substitute-call-lists/{substituteCallList}', [SubstituteCallListController::class, 'destroy'])->name('substitute-call-lists.destroy');
    Route::post('/bands/{band}/substitute-call-lists/reorder', [SubstituteCallListController::class, 'reorder'])->name('bands.substitute-call-lists.reorder');
});
