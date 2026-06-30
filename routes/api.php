<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BandsController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\EventTypeController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\BookedDatesController;
use App\Http\Controllers\Api\BookingsController;
use App\Http\Controllers\Api\EventsController;
use App\Http\Controllers\ChartsController;
use App\Http\Controllers\RehearsalController;
use App\Http\Controllers\ChunkedUploadController;
use App\Http\Controllers\Api\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\BandSettingsController;
use App\Http\Controllers\Api\Mobile\RehearsalPlannerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:api')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::any('/stripe', [StripeWebhookController::class, 'index'])->name('webhook.stripe');


Route::get('/getAllEventTypes', [EventTypeController::class, 'getAllEventTypes'])->name('getAllEventTypes');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/bands/{band}/contacts', [BandsController::class, 'contacts']);
    Route::get('/bands/{band}/members', [BandsController::class, 'members'])->name('api.bands.members');
    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/charts', [ChartsController::class, 'getChartsForUser']);
    Route::get('/rehearsal/{rehearsal_id}', [RehearsalController::class, 'getRehearsalData'])->name('api.rehearsal.get');
    Route::get('/rehearsal-schedule/{rehearsal_schedule_id}/band/{band_id}', [RehearsalController::class, 'getRehearsalScheduleData'])->name('api.rehearsal-schedule.get');

    // Chunked upload routes
    Route::post('/chunked-uploads/initiate', [ChunkedUploadController::class, 'initiate'])->name('chunked-uploads.initiate');
    Route::get('/chunked-uploads/{uploadId}', [ChunkedUploadController::class, 'getStatus'])->name('chunked-uploads.status');
    Route::post('/chunked-uploads/{uploadId}/chunk', [ChunkedUploadController::class, 'uploadChunk'])->name('chunked-uploads.chunk');
    Route::post('/chunked-uploads/{uploadId}/complete', [ChunkedUploadController::class, 'complete'])->name('chunked-uploads.complete');
});

Route::post('/searchLocations', [LocationController::class, 'searchLocations'])->name('searchLocations');
Route::post('/getLocationDetails', [LocationController::class, 'getLocationDetails'])->name('getLocationDetails');
Route::post('/geocodeAddress', [LocationController::class, 'geocodeAddress'])->name('geocodeAddress');
Route::get('/contracts/{contract:envelope_id}/history', [ContractsController::class, 'getHistory'])->name('getContractHistory');

// Mobile API routes (Sanctum user token authenticated)
Route::prefix('mobile')->group(function () {
    // Public: login
    Route::post('/auth/token', [MobileAuthController::class, 'token'])->name('mobile.auth.token');

    // Registration
    Route::post('/auth/register', [App\Http\Controllers\Api\Mobile\OnboardingController::class, 'register'])->name('mobile.auth.register');

    // Public: signed-URL contract view (auth is the signature itself)
    Route::get('/bands/{band}/bookings/{booking}/contract/view-signed', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'viewContractSigned'])->name('mobile.bookings.contract.view.signed');

    // Account-deletion confirmation lives at a neutral, shared path defined in
    // routes/account.php (account.confirm-deletion / account.perform-deletion).
    // The mobile DELETE /account flow below emails a link to those routes.

    // Authenticated
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/auth/me', [MobileAuthController::class, 'me'])->name('mobile.auth.me');
        Route::delete('/auth/token', [MobileAuthController::class, 'logout'])->name('mobile.auth.logout');
        Route::post('/token/refresh', [MobileAuthController::class, 'refresh'])->name('mobile.auth.refresh');

        // Account management
        Route::get('/account', [MobileAuthController::class, 'showAccount'])->name('mobile.account.show');
        Route::patch('/account', [MobileAuthController::class, 'updateAccount'])->name('mobile.account.update');
        Route::delete('/account', [MobileAuthController::class, 'requestDeletion'])->name('mobile.account.request-deletion');

        // Event types
        Route::get('/event-types', fn () => response()->json([
            'event_types' => \App\Models\EventTypes::orderBy('id')->get(['id', 'name']),
        ]))->name('mobile.event-types');

        // Dashboard
        Route::get('/dashboard', [App\Http\Controllers\Api\Mobile\DashboardController::class, 'index'])->name('mobile.dashboard');
        Route::get('/dashboard/load-older', [App\Http\Controllers\Api\Mobile\DashboardController::class, 'loadOlder'])->name('mobile.dashboard.load-older');

        // Aggregating charts across all of the user's bands (band-agnostic).
        Route::get('/charts', [App\Http\Controllers\Api\Mobile\MusicController::class, 'chartsForUser'])->name('mobile.charts.for-user');

        // Search (band-agnostic — user's bands are derived from the authenticated user)
        Route::get('/search', [App\Http\Controllers\Api\Mobile\SearchController::class, 'search'])->name('mobile.search');

        // Aggregating bookings across all of the user's bands (band-agnostic).
        Route::get('/me/bookings', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'indexForUser'])->name('mobile.me.bookings');

        // Personal calendar subscription URLs (ICS feed for Google/Apple Calendar).
        Route::get('/me/calendar-feed', [App\Http\Controllers\Api\Mobile\CalendarFeedController::class, 'show'])->name('mobile.me.calendar-feed');
        Route::post('/me/calendar-feed/reset', [App\Http\Controllers\Api\Mobile\CalendarFeedController::class, 'reset'])->name('mobile.me.calendar-feed.reset');

        // Personal stats (earnings, travel, performance locations) across all bands.
        Route::get('/me/stats', [App\Http\Controllers\Api\Mobile\StatsController::class, 'index'])->name('mobile.me.stats');

        // Contract audit trail (band-agnostic — keyed by PandaDoc envelope id).
        Route::get('/contracts/{contract:envelope_id}/history', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'contractHistory'])->name('mobile.contracts.history');

        // Device registration (push notifications)
        Route::post('/devices', [App\Http\Controllers\Api\Mobile\DevicesController::class, 'store'])->name('mobile.devices.store');
        Route::delete('/devices', [App\Http\Controllers\Api\Mobile\DevicesController::class, 'destroy'])->name('mobile.devices.destroy');

        // Events
        Route::get('/events/{event}', [App\Http\Controllers\Api\Mobile\EventsController::class, 'show'])->name('mobile.events.show');
        Route::patch('/events/{event}', [App\Http\Controllers\Api\Mobile\EventsController::class, 'update'])->name('mobile.events.update');
        Route::get('/events/{event}/subs', [App\Http\Controllers\Api\Mobile\EventsController::class, 'subs'])->name('mobile.events.subs');
        Route::post('/events/{event}/members/{memberId}/sub', [App\Http\Controllers\Api\Mobile\EventsController::class, 'assignSub'])->name('mobile.events.members.sub');
        Route::post('/events/{event}/attachments', [App\Http\Controllers\Api\Mobile\EventsController::class, 'uploadAttachment'])->name('mobile.events.attachments.store');
        Route::delete('/events/{event}/attachments/{attachment}', [App\Http\Controllers\Api\Mobile\EventsController::class, 'deleteAttachment'])->name('mobile.events.attachments.destroy');

        // Band onboarding
        Route::post('/bands', [App\Http\Controllers\Api\Mobile\OnboardingController::class, 'createBand'])->name('mobile.bands.create');
        Route::post('/bands/join', [App\Http\Controllers\Api\Mobile\OnboardingController::class, 'joinBand'])->name('mobile.bands.join');
        Route::post('/bands/solo', [App\Http\Controllers\Api\Mobile\OnboardingController::class, 'goSolo'])->name('mobile.bands.solo');
        Route::post('/bands/{band}/invite', [App\Http\Controllers\Api\Mobile\OnboardingController::class, 'inviteMembers'])->name('mobile.bands.invite');
        Route::get('/bands/{band}/invite-qr', [App\Http\Controllers\Api\Mobile\OnboardingController::class, 'inviteQr'])->name('mobile.bands.invite-qr');

        // ── Band settings (owner-only) ─────────────────────────────────
        Route::middleware('owner')->group(function () {
            Route::get('/bands/{band}', [BandSettingsController::class, 'show'])->name('mobile.bands.show');
            Route::patch('/bands/{band}', [BandSettingsController::class, 'update'])->name('mobile.bands.update');
            Route::post('/bands/{band}/logo', [BandSettingsController::class, 'uploadLogo'])->name('mobile.bands.logo');
            Route::get('/bands/{band}/members', [BandSettingsController::class, 'members'])->name('mobile.bands.members');
            Route::delete('/bands/{band}/members/{userId}', [BandSettingsController::class, 'removeMember'])->name('mobile.bands.members.remove');
            Route::patch('/bands/{band}/members/{userId}/permissions', [BandSettingsController::class, 'setPermission'])->name('mobile.bands.members.permissions');
            Route::get('/bands/{band}/invitations', [BandSettingsController::class, 'invitations'])->name('mobile.bands.invitations');
            Route::delete('/bands/{band}/invitations/{invitation}', [BandSettingsController::class, 'revokeInvitation'])->name('mobile.bands.invitations.revoke');
        });

        // ── Roster system (owner-only) ─────────────────────────────────
        // Band ownership is gated by the `owner` middleware; controllers
        // additionally verify each bound roster/slot/member/role belongs to
        // the {band} (404 if not). Param names (roster/rosterSlot/rosterMember)
        // match the reused Form Requests' route() lookups.
        Route::middleware('owner')->group(function () {
            // Band roles
            Route::get('/bands/{band}/roles', [App\Http\Controllers\Api\Mobile\BandRolesController::class, 'index'])->name('mobile.bands.roles.index');
            Route::post('/bands/{band}/roles', [App\Http\Controllers\Api\Mobile\BandRolesController::class, 'store'])->name('mobile.bands.roles.store');
            Route::post('/bands/{band}/roles/reorder', [App\Http\Controllers\Api\Mobile\BandRolesController::class, 'reorder'])->name('mobile.bands.roles.reorder');
            Route::patch('/bands/{band}/roles/{role}', [App\Http\Controllers\Api\Mobile\BandRolesController::class, 'update'])->name('mobile.bands.roles.update');
            Route::delete('/bands/{band}/roles/{role}', [App\Http\Controllers\Api\Mobile\BandRolesController::class, 'destroy'])->name('mobile.bands.roles.destroy');

            // Rosters
            Route::get('/bands/{band}/rosters', [App\Http\Controllers\Api\Mobile\RostersController::class, 'index'])->name('mobile.bands.rosters.index');
            Route::post('/bands/{band}/rosters', [App\Http\Controllers\Api\Mobile\RostersController::class, 'store'])->name('mobile.bands.rosters.store');
            Route::post('/bands/{band}/rosters/initialize', [App\Http\Controllers\Api\Mobile\RostersController::class, 'initializeFromBand'])->name('mobile.bands.rosters.initialize');
            Route::get('/bands/{band}/rosters/{roster}', [App\Http\Controllers\Api\Mobile\RostersController::class, 'show'])->name('mobile.bands.rosters.show');
            Route::patch('/bands/{band}/rosters/{roster}', [App\Http\Controllers\Api\Mobile\RostersController::class, 'update'])->name('mobile.bands.rosters.update');
            Route::delete('/bands/{band}/rosters/{roster}', [App\Http\Controllers\Api\Mobile\RostersController::class, 'destroy'])->name('mobile.bands.rosters.destroy');
            Route::post('/bands/{band}/rosters/{roster}/set-default', [App\Http\Controllers\Api\Mobile\RostersController::class, 'setDefault'])->name('mobile.bands.rosters.set-default');
            Route::get('/bands/{band}/rosters/{roster}/future-events-diff', [App\Http\Controllers\Api\Mobile\RostersController::class, 'futureEventsDiff'])->name('mobile.bands.rosters.future-events-diff');
            Route::post('/bands/{band}/rosters/{roster}/reconcile-future-events', [App\Http\Controllers\Api\Mobile\RostersController::class, 'reconcileFutureEvents'])->name('mobile.bands.rosters.reconcile-future-events');

            // Roster slots
            Route::post('/bands/{band}/rosters/{roster}/slots', [App\Http\Controllers\Api\Mobile\RosterSlotsController::class, 'store'])->name('mobile.bands.roster-slots.store');
            Route::patch('/bands/{band}/roster-slots/{rosterSlot}', [App\Http\Controllers\Api\Mobile\RosterSlotsController::class, 'update'])->name('mobile.bands.roster-slots.update');
            Route::delete('/bands/{band}/roster-slots/{rosterSlot}', [App\Http\Controllers\Api\Mobile\RosterSlotsController::class, 'destroy'])->name('mobile.bands.roster-slots.destroy');

            // Roster members
            Route::post('/bands/{band}/rosters/{roster}/members', [App\Http\Controllers\Api\Mobile\RosterMembersController::class, 'store'])->name('mobile.bands.roster-members.store');
            Route::patch('/bands/{band}/roster-members/{rosterMember}', [App\Http\Controllers\Api\Mobile\RosterMembersController::class, 'update'])->name('mobile.bands.roster-members.update');
            Route::delete('/bands/{band}/roster-members/{rosterMember}', [App\Http\Controllers\Api\Mobile\RosterMembersController::class, 'destroy'])->name('mobile.bands.roster-members.destroy');
            Route::post('/bands/{band}/roster-members/{rosterMember}/toggle-active', [App\Http\Controllers\Api\Mobile\RosterMembersController::class, 'toggleActive'])->name('mobile.bands.roster-members.toggle-active');
        });

        // ── Substitute call lists + band subs (owner-only) ─────────────
        // Band ownership is gated by the `owner` middleware; controllers
        // additionally verify each bound call-list entry / invitation /
        // band-sub link belongs to the {band} (404 if not).
        Route::middleware('owner')->group(function () {
            // Substitute call lists
            // Static `reorder` is registered before the {callList} wildcard.
            Route::get('/bands/{band}/call-lists', [App\Http\Controllers\Api\Mobile\SubstituteCallListsController::class, 'index'])->name('mobile.bands.call-lists.index');
            Route::post('/bands/{band}/call-lists', [App\Http\Controllers\Api\Mobile\SubstituteCallListsController::class, 'store'])->name('mobile.bands.call-lists.store');
            Route::post('/bands/{band}/call-lists/reorder', [App\Http\Controllers\Api\Mobile\SubstituteCallListsController::class, 'reorder'])->name('mobile.bands.call-lists.reorder');
            Route::patch('/bands/{band}/call-lists/{callList}', [App\Http\Controllers\Api\Mobile\SubstituteCallListsController::class, 'update'])->name('mobile.bands.call-lists.update');
            Route::delete('/bands/{band}/call-lists/{callList}', [App\Http\Controllers\Api\Mobile\SubstituteCallListsController::class, 'destroy'])->name('mobile.bands.call-lists.destroy');

            // Band subs
            Route::get('/bands/{band}/subs', [App\Http\Controllers\Api\Mobile\BandSubsController::class, 'index'])->name('mobile.bands.subs.index');
            Route::post('/bands/{band}/subs/invite', [App\Http\Controllers\Api\Mobile\BandSubsController::class, 'invite'])->name('mobile.bands.subs.invite');
            Route::delete('/bands/{band}/subs/invitations/{invitation}', [App\Http\Controllers\Api\Mobile\BandSubsController::class, 'destroyInvitation'])->name('mobile.bands.subs.invitations.destroy');
            Route::delete('/bands/{band}/subs/{user}', [App\Http\Controllers\Api\Mobile\BandSubsController::class, 'destroy'])->name('mobile.bands.subs.destroy');
        });

        // ── Events (read) ──────────────────────────────────────────────
        Route::middleware('mobile.band:read:events')->group(function () {
            Route::get('/bands/{band}/events', [App\Http\Controllers\Api\Mobile\EventsController::class, 'index'])->name('mobile.events.index');
            Route::get('/bands/{band}/attire-chips', [App\Http\Controllers\Api\Mobile\AttireChipsController::class, 'index'])->name('mobile.attire-chips.index');
        });

        // ── Attire chips (write) — ride on the events:write permission ─
        Route::middleware('mobile.band:write:events')->group(function () {
            Route::post('/bands/{band}/attire-chips', [App\Http\Controllers\Api\Mobile\AttireChipsController::class, 'store'])->name('mobile.attire-chips.store');
            Route::delete('/bands/{band}/attire-chips/{chip}', [App\Http\Controllers\Api\Mobile\AttireChipsController::class, 'destroy'])->name('mobile.attire-chips.destroy');
        });

        // ── Bookings (read) ────────────────────────────────────────────
        Route::middleware('mobile.band:read:bookings')->scopeBindings()->group(function () {
            Route::get('/bands/{band}/bookings', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'index'])->name('mobile.bookings.index');
            Route::get('/bands/{band}/bookings/{booking}', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'show'])->name('mobile.bookings.show');
            Route::get('/bands/{band}/contacts', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'contactLibrary'])->name('mobile.contacts.index');
            Route::get('/bands/{band}/bookings/{booking}/contract', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'showContract'])->name('mobile.bookings.contract.show');
            Route::get('/bands/{band}/bookings/{booking}/contract/view', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'viewContract'])->name('mobile.bookings.contract.view');
            Route::get('/bands/{band}/bookings/{booking}/contract/view-url', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'viewContractUrl'])->name('mobile.bookings.contract.view.url');
            Route::get('/bands/{band}/bookings/{booking}/contract/download', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'downloadContract'])->name('mobile.bookings.contract.download');
            Route::get('/bands/{band}/bookings/{booking}/history', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'showHistory'])->name('mobile.bookings.history');
            Route::get('/bands/{band}/bookings/{booking}/payout', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'payout'])->name('mobile.bookings.payout.show');
        });

        // ── Bookings (write) ───────────────────────────────────────────
        Route::middleware('mobile.band:write:bookings')->scopeBindings()->group(function () {
            Route::post('/bands/{band}/bookings', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'store'])->name('mobile.bookings.store');
            Route::patch('/bands/{band}/bookings/{booking}', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'update'])->name('mobile.bookings.update');
            Route::delete('/bands/{band}/bookings/{booking}', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'destroy'])->name('mobile.bookings.destroy');
            Route::post('/bands/{band}/bookings/{booking}/cancel', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'cancel'])->name('mobile.bookings.cancel');

            // Booking contacts (write)
            Route::post('/bands/{band}/bookings/{booking}/contacts', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'storeContact'])->name('mobile.bookings.contacts.store');
            Route::patch('/bands/{band}/bookings/{booking}/contacts/{booking_contact}', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'updateContact'])->name('mobile.bookings.contacts.update');
            Route::delete('/bands/{band}/bookings/{booking}/contacts/{booking_contact}', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'destroyContact'])->name('mobile.bookings.contacts.destroy');

            // Booking payments
            Route::post('/bands/{band}/bookings/{booking}/payments', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'storePayment'])->name('mobile.bookings.payments.store');
            Route::delete('/bands/{band}/bookings/{booking}/payments/{payment}', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'destroyPayment'])->name('mobile.bookings.payments.destroy');

            // Booking events (subresource)
            Route::post('/bands/{band}/bookings/{booking}/events',
                [App\Http\Controllers\Api\Mobile\BookingsController::class, 'storeEvent'])
                ->name('mobile.bookings.events.store');
            Route::patch('/bands/{band}/bookings/{booking}/events/{event}',
                [App\Http\Controllers\Api\Mobile\BookingsController::class, 'updateEvent'])
                ->name('mobile.bookings.events.update');
            Route::delete('/bands/{band}/bookings/{booking}/events/{event}',
                [App\Http\Controllers\Api\Mobile\BookingsController::class, 'destroyEvent'])
                ->name('mobile.bookings.events.destroy');

            // Booking contract (write)
            Route::post('/bands/{band}/bookings/{booking}/contract/upload', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'uploadContract'])->name('mobile.bookings.contract.upload');
            Route::post('/bands/{band}/bookings/{booking}/contract/send', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'sendContract'])->name('mobile.bookings.contract.send');
            Route::post('/bands/{band}/bookings/{booking}/contract/terms', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'saveContractTerms'])->name('mobile.bookings.contract.terms');

        });

        // Booking payout adjustments (write) — separate group to avoid scopeBindings
        // scoping {adjustment} through Bookings (PayoutAdjustment belongs to Payout, not Booking directly)
        Route::middleware('mobile.band:write:bookings')->group(function () {
            Route::post('/bands/{band}/bookings/{booking}/payout/adjustments', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'storePayoutAdjustment'])->name('mobile.bookings.payout.adjustments.store');
            Route::delete('/bands/{band}/bookings/{booking}/payout/adjustments/{adjustment}', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'destroyPayoutAdjustment'])->name('mobile.bookings.payout.adjustments.destroy');
            Route::put('/bands/{band}/bookings/{booking}/payout/configuration', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'updatePayoutConfiguration'])->name('mobile.bookings.payout.configuration.update');
            Route::patch('/bands/{band}/bookings/{booking}/events/{event}/members/{member}/attendance', [App\Http\Controllers\Api\Mobile\BookingsController::class, 'updateMemberAttendance'])->name('mobile.bookings.events.members.attendance');
        });

        // ── Finances (uses read:bookings permission) ───────────────────
        Route::middleware('mobile.band:read:bookings')->group(function () {
            Route::get('/bands/{band}/finances', [App\Http\Controllers\Api\Mobile\FinancesController::class, 'index'])->name('mobile.finances.index');
            Route::get('/bands/{band}/finances/unpaid', [App\Http\Controllers\Api\Mobile\FinancesController::class, 'unpaid'])->name('mobile.finances.unpaid');
            Route::get('/bands/{band}/finances/paid', [App\Http\Controllers\Api\Mobile\FinancesController::class, 'paid'])->name('mobile.finances.paid');
            Route::get('/bands/{band}/finances/revenue', [App\Http\Controllers\Api\Mobile\FinancesController::class, 'revenue'])->name('mobile.finances.revenue');
            Route::get('/bands/{band}/finances/trends', [App\Http\Controllers\Api\Mobile\FinancesController::class, 'trends'])->name('mobile.finances.trends');
        });

        // ── Payout flow editor: reads + preview (band member) ──────────
        Route::middleware('mobile.band:read:bookings')->group(function () {
            Route::get('/bands/{band}/payout-flow/templates', [App\Http\Controllers\Api\Mobile\PayoutFlowController::class, 'templates'])->name('mobile.payout-flow.templates');
            Route::get('/bands/{band}/payout-flow/configs', [App\Http\Controllers\Api\Mobile\PayoutFlowController::class, 'listConfigs'])->name('mobile.payout-flow.configs.list');
            Route::get('/bands/{band}/payout-flow/configs/{configId}', [App\Http\Controllers\Api\Mobile\PayoutFlowController::class, 'showConfig'])->name('mobile.payout-flow.configs.show');
            Route::post('/bands/{band}/payout-flow/preview', [App\Http\Controllers\Api\Mobile\PayoutFlowController::class, 'preview'])->name('mobile.payout-flow.preview');
        });

        // ── Payout flow editor: writes (owner-only) ────────────────────
        Route::middleware('owner')->group(function () {
            Route::post('/bands/{band}/payout-flow/configs', [App\Http\Controllers\Api\Mobile\PayoutFlowController::class, 'createConfig'])->name('mobile.payout-flow.configs.create');
            Route::patch('/bands/{band}/payout-flow/configs/{configId}', [App\Http\Controllers\Api\Mobile\PayoutFlowController::class, 'updateConfig'])->name('mobile.payout-flow.configs.update');
            Route::delete('/bands/{band}/payout-flow/configs/{configId}', [App\Http\Controllers\Api\Mobile\PayoutFlowController::class, 'destroyConfig'])->name('mobile.payout-flow.configs.destroy');
        });

        // ── Rehearsals (read) ──────────────────────────────────────────
        Route::middleware('mobile.band:read:rehearsals')->group(function () {
            Route::get('/bands/{band}/rehearsal-schedules', [App\Http\Controllers\Api\Mobile\RehearsalsController::class, 'schedules'])->name('mobile.rehearsals.schedules');
        });

        // ── Rehearsal planner (AI) ─────────────────────────────────────
        // No scopeBindings(): there is no Bands->sessions() relationship to scope
        // {session} against. Band-scoping is enforced explicitly in the controller
        // via abort_unless($session->band_id === $band->id, 404).
        Route::middleware('mobile.band:read:rehearsals')->group(function () {
            Route::post('/bands/{band}/rehearsal-planner/sessions', [RehearsalPlannerController::class, 'start'])
                ->name('mobile.rehearsal-planner.start');
            Route::post('/bands/{band}/rehearsal-planner/sessions/{session}/messages', [RehearsalPlannerController::class, 'message'])
                ->name('mobile.rehearsal-planner.message');
            Route::get('/bands/{band}/rehearsal-planner/sessions/{session}', [RehearsalPlannerController::class, 'show'])
                ->name('mobile.rehearsal-planner.show');
        });

        // ── Music / Charts (read) ──────────────────────────────────────
        Route::middleware('mobile.band:read:charts')->group(function () {
            Route::get('/bands/{band}/songs', [App\Http\Controllers\Api\Mobile\MusicController::class, 'songs'])->name('mobile.songs.index');
            Route::get('/bands/{band}/charts', [App\Http\Controllers\Api\Mobile\MusicController::class, 'charts'])->name('mobile.charts.index');
            Route::get('/bands/{band}/charts/{chart}', [App\Http\Controllers\Api\Mobile\MusicController::class, 'chartDetail'])->name('mobile.charts.show');
            Route::get('/bands/{band}/charts/{chart}/uploads/{upload}/download', [App\Http\Controllers\Api\Mobile\MusicController::class, 'downloadChartUpload'])->name('mobile.charts.uploads.download');
        });

        // ── Music / Charts (write) ─────────────────────────────────────
        Route::middleware('mobile.band:write:charts')->group(function () {
            Route::post('/bands/{band}/charts', [App\Http\Controllers\Api\Mobile\MusicController::class, 'storeChart'])->name('mobile.charts.store');
            Route::delete('/bands/{band}/charts/{chart}', [App\Http\Controllers\Api\Mobile\MusicController::class, 'destroyChart'])->name('mobile.charts.destroy');
            Route::post('/bands/{band}/charts/{chart}/uploads', [App\Http\Controllers\Api\Mobile\MusicController::class, 'storeChartUpload'])->name('mobile.charts.uploads.store');
            Route::delete('/bands/{band}/charts/{chart}/uploads/{upload}', [App\Http\Controllers\Api\Mobile\MusicController::class, 'destroyChartUpload'])->name('mobile.charts.uploads.destroy');
        });

        // Rehearsal detail (band derived from rehearsal)
        // IMPORTANT: the static segment "by-key" must be registered before {rehearsal}
        // so it is not swallowed by the integer wildcard route.
        Route::get('/rehearsals/by-key/{key}', [App\Http\Controllers\Api\Mobile\RehearsalsController::class, 'showByKey'])->name('mobile.rehearsals.show.by-key');
        Route::patch('/rehearsals/{rehearsal}/notes', [App\Http\Controllers\Api\Mobile\RehearsalsController::class, 'updateNotes'])->name('mobile.rehearsals.update-notes');
        Route::get('/rehearsals/{rehearsal}', [App\Http\Controllers\Api\Mobile\RehearsalsController::class, 'show'])->name('mobile.rehearsals.show');

        // ── Media (read) ───────────────────────────────────────────────
        Route::prefix('bands/{band}')->middleware('mobile.band:read:media')->group(function () {
            Route::get('/media', [App\Http\Controllers\Api\Mobile\MediaController::class, 'index'])->name('mobile.media.index');
            Route::get('/media/{media}', [App\Http\Controllers\Api\Mobile\MediaController::class, 'show'])->name('mobile.media.show');
            Route::get('/media/{media}/serve', [App\Http\Controllers\Api\Mobile\MediaController::class, 'serve'])->name('mobile.media.serve');
        });

        // ── Media (write) ──────────────────────────────────────────────
        Route::prefix('bands/{band}')->middleware('mobile.band:write:media')->group(function () {
            Route::delete('/media/{media}', [App\Http\Controllers\Api\Mobile\MediaController::class, 'destroy'])->name('mobile.media.destroy');
            Route::get('/media/upload/{uploadId}', [App\Http\Controllers\Api\Mobile\MediaController::class, 'uploadStatus'])->name('mobile.media.upload.status');
            Route::post('/media/upload/initiate', [App\Http\Controllers\Api\Mobile\MediaController::class, 'uploadInitiate'])->name('mobile.media.upload.initiate');
            Route::post('/media/upload/{uploadId}/chunk', [App\Http\Controllers\Api\Mobile\MediaController::class, 'uploadChunk'])->name('mobile.media.upload.chunk');
            Route::post('/media/upload/{uploadId}/complete', [App\Http\Controllers\Api\Mobile\MediaController::class, 'uploadComplete'])->name('mobile.media.upload.complete');
            Route::post('/media/folders', [App\Http\Controllers\Api\Mobile\MediaController::class, 'createFolder'])->name('mobile.media.folders.create');
        });

        // Setlist editor (pre-gig) — auth checks done in controller per event
        Route::get('/events/{event}/setlist', [App\Http\Controllers\Api\Mobile\SetlistEditorController::class, 'show'])->name('mobile.setlist.editor.show');
        Route::put('/events/{event}/setlist', [App\Http\Controllers\Api\Mobile\SetlistEditorController::class, 'update'])->name('mobile.setlist.editor.update');
        Route::post('/events/{event}/setlist/generate', [App\Http\Controllers\Api\Mobile\SetlistEditorController::class, 'generate'])->name('mobile.setlist.editor.generate');
        Route::post('/events/{event}/setlist/refine', [App\Http\Controllers\Api\Mobile\SetlistEditorController::class, 'refine'])->name('mobile.setlist.editor.refine');

        // Setlist prompt templates (band-scoped) — auth at route layer, matching attire chips
        Route::middleware('mobile.band:read:events')->group(function () {
            Route::get('/bands/{band}/setlist-prompt-templates', [App\Http\Controllers\Api\Mobile\SetlistPromptTemplateController::class, 'index'])->name('mobile.setlist.prompt-templates.index');
        });
        // No scopeBindings(): the {template} param name doesn't match the
        // band relation (setlistPromptTemplates), so cross-band scoping is
        // enforced in-controller via abort_if — matching the attire-chips group.
        Route::middleware('mobile.band:write:events')->group(function () {
            Route::post('/bands/{band}/setlist-prompt-templates', [App\Http\Controllers\Api\Mobile\SetlistPromptTemplateController::class, 'store'])->name('mobile.setlist.prompt-templates.store');
            Route::patch('/bands/{band}/setlist-prompt-templates/{template}', [App\Http\Controllers\Api\Mobile\SetlistPromptTemplateController::class, 'update'])->name('mobile.setlist.prompt-templates.update');
            Route::delete('/bands/{band}/setlist-prompt-templates/{template}', [App\Http\Controllers\Api\Mobile\SetlistPromptTemplateController::class, 'destroy'])->name('mobile.setlist.prompt-templates.destroy');
        });

        // Setlist / live session
        Route::prefix('setlist')->name('mobile.setlist.')->group(function () {
            Route::get('/events/{event}/session', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'show'])->name('show');
            Route::post('/events/{event}/session', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'start'])->name('start');
            Route::delete('/events/{event}/session', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'end'])->name('end');

            // Captain actions — keyed by session id
            Route::post('/sessions/{id}/next', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'next'])->name('next');
            Route::post('/sessions/{id}/skip', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'skip'])->name('skip');
            Route::post('/sessions/{id}/skip-remove', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'skipRemove'])->name('skipRemove');
            Route::post('/sessions/{id}/reaction', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'reaction'])->name('reaction');
            Route::post('/sessions/{id}/off-setlist', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'offSetlist'])->name('offSetlist');
            Route::post('/sessions/{id}/promote', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'promote'])->name('promote');
            Route::post('/sessions/{id}/demote', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'demote'])->name('demote');

            // Break management
            Route::post('/sessions/{id}/break', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'breakStart'])->name('break.start');
            Route::post('/sessions/{id}/break/resume', [App\Http\Controllers\Api\Mobile\SetlistController::class, 'breakResume'])->name('break.resume');
        });
    });
});

// Band API routes (token-authenticated)
Route::middleware(['band.api'])->group(function () {
    // Booked Dates - Read Bookings
    Route::get('/booked-dates', [BookedDatesController::class, 'index'])
        ->middleware('api.permission:api:read-bookings')
        ->name('api.booked-dates');

    // Events - Read
    Route::get('/events', [EventsController::class, 'index'])
        ->middleware('api.permission:api:read-events')
        ->name('api.events.index');

    // Bookings - Read
    Route::get('/bookings', [BookingsController::class, 'index'])
        ->middleware('api.permission:api:read-bookings')
        ->name('api.bookings.index');

    Route::get('/bookings/{id}', [BookingsController::class, 'show'])
        ->middleware('api.permission:api:read-bookings')
        ->name('api.bookings.show');

    // Bookings - Write
    Route::post('/bookings', [BookingsController::class, 'store'])
        ->middleware('api.permission:api:write-bookings')
        ->name('api.bookings.store');

    Route::put('/bookings/{id}', [BookingsController::class, 'update'])
        ->middleware('api.permission:api:write-bookings')
        ->name('api.bookings.update');

    Route::patch('/bookings/{id}', [BookingsController::class, 'update'])
        ->middleware('api.permission:api:write-bookings')
        ->name('api.bookings.patch');

    Route::delete('/bookings/{id}', [BookingsController::class, 'destroy'])
        ->middleware('api.permission:api:write-bookings')
        ->name('api.bookings.destroy');
});
// });
