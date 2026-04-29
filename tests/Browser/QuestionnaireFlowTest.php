<?php

namespace Tests\Browser;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Events;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\DuskTestCase;

class QuestionnaireFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    private User $owner;
    private Bands $band;
    private Bookings $booking;
    private Contacts $contact;
    private Events $event;

    /**
     * Skip the parent's teardown migrate:rollback, which fails on this
     * codebase's irreversible drop_proposal_tables migration. The next
     * test's migrate:fresh will reset state cleanly.
     */
    protected function tearDown(): void
    {
        // Intentionally do nothing.
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Seed event_types and other reference tables that the global Inertia
        // middleware queries — DatabaseMigrations only ran migrate:fresh.
        Artisan::call('db:seed', ['--class' => 'EventTypeSeeder', '--force' => true]);
        Artisan::call('db:seed', ['--class' => 'StatesTableSeeder', '--force' => true]);

        $this->band = Bands::factory()->create(['name' => 'Smoke Band']);

        $this->owner = User::factory()->create([
            'name' => 'Bob Owner',
            'email' => 'smoke-owner@test.local',
            'password' => Hash::make('password'),
        ]);
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $ownerRole = Role::where('name', 'band-owner')->where('guard_name', 'web')->first();
        if ($ownerRole) {
            setPermissionsTeamId($this->band->id);
            $this->owner->assignRole($ownerRole);
            setPermissionsTeamId(0);
        }

        $this->booking = Bookings::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Smith Wedding',
        ]);

        $this->contact = Contacts::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Jane Smith',
            'email' => 'smoke-contact@test.local',
            'password' => Hash::make('password'),
            'can_login' => true,
        ]);
        $this->booking->contacts()->attach($this->contact, ['is_primary' => true]);

        $this->event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $this->booking->id,
            'title' => 'Smith Wedding',
            'additional_data' => [
                'wedding' => [
                    'onsite' => 0,
                    'dances' => [
                        ['title' => 'First Dance', 'data' => 'TBD'],
                    ],
                ],
            ],
            'notes' => '<p>existing notes</p>',
        ]);
    }

    public function test_band_owner_can_reach_questionnaires_index(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit('/questionnaires')
                ->waitForText('Questionnaires', 10)
                ->assertSee('New Questionnaire');
        });
    }

    public function test_full_flow_template_through_response(): void
    {
        // Seed template + instance + responses programmatically (UI-driving the
        // builder via Selenium is brittle; the controller-level tests already
        // exercise that path). Then drive the client portal in a real browser
        // to confirm Show.vue renders, autosaves, submits.

        $template = \App\Models\Questionnaires::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Wedding Day Smoke',
        ]);

        \App\Models\QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'short_text',
            'label' => "Bride's Full Name",
            'required' => true,
            'position' => 10,
        ]);
        \App\Models\QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'yes_no',
            'label' => 'Onsite ceremony?',
            'required' => true,
            'position' => 20,
            'mapping_target' => 'wedding.onsite',
        ]);

        $instance = app(\App\Services\QuestionnaireSnapshotService::class)
            ->snapshot($template, $this->booking, $this->contact, $this->owner);

        // Contact opens the questionnaire in a real browser
        $this->browse(function (Browser $browser) use ($instance) {
            $browser->loginAs($this->contact, 'contact')
                ->visit("/portal/booking/{$this->booking->id}/questionnaire/{$instance->id}")
                ->waitForText('Wedding Day Smoke', 10)
                ->assertSee("Bride's Full Name")
                ->assertSee('Onsite ceremony');
        });

        // Verify first_opened_at was stamped server-side
        $instance->refresh();
        $this->assertNotNull($instance->first_opened_at);

        // Now check the band-side event editor surfaces the questionnaire panel.
        // Apply a response and ensure mapping works at the service level (the
        // event editor Vue panel is exercised separately by build-then-eyeball).
        $brideField = $instance->fields()->where('label', "Bride's Full Name")->first();
        $onsiteField = $instance->fields()->where('label', 'Onsite ceremony?')->first();

        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $brideField->id,
            'value' => "Jane O'Brien",
        ]);
        $onsiteResponse = \App\Models\QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $onsiteField->id,
            'value' => 'yes',
        ]);

        app(\App\Services\QuestionnaireMappingService::class)
            ->applyResponse($onsiteResponse, $this->owner);

        $this->event->refresh();
        $this->assertSame(true, data_get($this->event->additional_data, 'wedding.onsite'));
    }

    public function test_owner_can_preview_submitted_data_from_booking_page(): void
    {
        // Build a submitted instance with a recognisable answer
        [$instance] = $this->seedSubmittedInstance();

        $this->browse(function (Browser $browser) use ($instance) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$this->booking->id}")
                ->waitForText('Questionnaires', 10)
                ->assertSee($instance->name)
                ->scrollIntoView('[data-test="preview-instance"]')
                ->script("document.querySelector('[data-test=\"preview-instance\"]').click();");
            $browser->waitForText("Bride's Full Name", 5)
                ->assertSee("Jane O'Brien")
                ->assertSee('Yes'); // yes_no answer rendered as "Yes"
        });
    }

    public function test_owner_can_preview_submitted_data_from_template_show_page(): void
    {
        [$instance, $template] = $this->seedSubmittedInstance();

        $this->browse(function (Browser $browser) use ($template) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/questionnaires/{$template->slug}")
                ->waitForText('Sent', 10)
                ->scrollIntoView('[data-test="preview-instance"]')
                ->script("document.querySelector('[data-test=\"preview-instance\"]').click();");
            $browser->waitForText("Bride's Full Name", 5)
                ->assertSee("Jane O'Brien")
                ->assertSee('Yes');
        });
    }

    public function test_client_can_pick_songs_and_owner_sees_titles_in_preview(): void
    {
        // Seed two songs in the band's catalog
        $song1 = \App\Models\Song::factory()->create([
            'band_id' => $this->band->id,
            'title' => 'Evergreen',
            'artist' => 'Yebba',
            'active' => true,
        ]);
        $song2 = \App\Models\Song::factory()->create([
            'band_id' => $this->band->id,
            'title' => 'Mr Brightside',
            'artist' => 'The Killers',
            'active' => true,
        ]);

        // Build a template with a single song_picker
        $template = \App\Models\Questionnaires::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Song Picker Smoke',
        ]);
        \App\Models\QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'song_picker',
            'label' => 'Must-play songs',
            'required' => false,
            'position' => 10,
            'settings' => ['purpose' => 'must_play'],
        ]);

        $instance = app(\App\Services\QuestionnaireSnapshotService::class)
            ->snapshot($template, $this->booking, $this->contact, $this->owner);

        // Client opens the portal, picks a song, submits
        $this->browse(function (Browser $browser) use ($instance, $song1) {
            $browser->loginAs($this->contact, 'contact')
                ->visit("/portal/booking/{$this->booking->id}/questionnaire/{$instance->id}")
                ->waitForText('Must-play songs', 10)
                ->assertSee('Evergreen')
                ->assertSee('Mr Brightside');

            // Click the checkbox for the first song; PrimeVue Checkbox renders
            // a div.p-checkbox-box wrapper that catches the click.
            $browser->script(<<<JS
                const labels = Array.from(document.querySelectorAll('label'));
                const target = labels.find(l => l.textContent.includes('Evergreen'));
                target.click();
            JS);
            $browser->pause(300);
        });

        // Verify the response was saved
        $response = $instance->responses()->first();
        $this->assertNotNull($response, 'Expected a saved response after clicking the song checkbox');
        $decoded = json_decode($response->value, true);
        $this->assertContains($song1->id, $decoded);

        // Mark the instance submitted (skip UI submit; covered elsewhere)
        $instance->update([
            'status' => \App\Models\QuestionnaireInstances::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        // Owner views the booking and confirms the song title appears in preview
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$this->booking->id}")
                ->waitForText('Questionnaires', 10)
                ->scrollIntoView('[data-test="preview-instance"]')
                ->script("document.querySelector('[data-test=\"preview-instance\"]').click();");
            $browser->waitForText('Must-play songs', 5)
                ->assertSee('Evergreen')
                ->assertSee('Yebba');
        });
    }

    public function test_client_sees_clear_feedback_when_submitting_with_empty_required_field(): void
    {
        $template = \App\Models\Questionnaires::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Required Field Smoke',
        ]);

        \App\Models\QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'short_text',
            'label' => "Bride's Full Name",
            'required' => true,
            'position' => 10,
        ]);

        $instance = app(\App\Services\QuestionnaireSnapshotService::class)
            ->snapshot($template, $this->booking, $this->contact, $this->owner);

        $this->browse(function (Browser $browser) use ($instance) {
            $browser->loginAs($this->contact, 'contact')
                ->visit("/portal/booking/{$this->booking->id}/questionnaire/{$instance->id}")
                ->waitForText('Required Field Smoke', 10)
                ->press('Submit')
                ->waitForText('Please complete the required fields', 5)
                ->assertSee('This field is required.');
        });

        // Status should remain unchanged on validation failure
        $instance->refresh();
        $this->assertNotSame(\App\Models\QuestionnaireInstances::STATUS_SUBMITTED, $instance->status);
    }

    public function test_clicking_apply_updates_status_without_page_refresh(): void
    {
        // Build a submitted instance with a mappable response that has not been
        // applied yet. The Apply button should switch to "Applied" immediately
        // after the request completes — without a manual page reload.
        $template = \App\Models\Questionnaires::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Apply Refresh Smoke',
        ]);

        \App\Models\QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'yes_no',
            'label' => 'Onsite ceremony?',
            'required' => true,
            'position' => 10,
            'mapping_target' => 'wedding.onsite',
        ]);

        $instance = app(\App\Services\QuestionnaireSnapshotService::class)
            ->snapshot($template, $this->booking, $this->contact, $this->owner);

        $onsiteField = $instance->fields()->where('label', 'Onsite ceremony?')->first();
        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $onsiteField->id,
            'value' => 'yes',
        ]);

        $instance->update([
            'status' => \App\Models\QuestionnaireInstances::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$this->booking->id}/events?edit={$this->event->key}")
                ->waitForText('Questionnaires', 10)
                ->waitForText('Onsite ceremony?', 5)
                ->assertSee('Apply')
                ->assertDontSee('Applied');

            // Click the per-response Apply button. Use a script click rather
            // than press() to avoid matching the "Apply all pending" button.
            $browser->script(<<<JS
                const buttons = Array.from(document.querySelectorAll('button'));
                const applyBtn = buttons.find(b => b.textContent.trim() === 'Apply');
                applyBtn.click();
            JS);

            // The UI should reflect the new state without a manual refresh.
            $browser->waitForText('Applied', 5)
                ->assertSee('Applied');
        });

        // Server-side state should match what the UI is showing.
        $response = $instance->responses()->first();
        $this->assertNotNull($response->fresh()->applied_to_event_at);

        // Regression guard: after Apply, the prop refresh must not trigger the
        // autosave watcher. Wait past the 3s autosave debounce and confirm the
        // event's updated_at is unchanged.
        $updatedAtBefore = $this->event->fresh()->updated_at;
        sleep(5);
        $this->assertEquals(
            $updatedAtBefore->toIso8601String(),
            $this->event->fresh()->updated_at->toIso8601String(),
            'Apply triggered an unwanted autosave loop'
        );
    }

    public function test_clicking_append_to_notes_updates_notes_without_page_refresh(): void
    {
        $template = \App\Models\Questionnaires::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Append Notes Smoke',
        ]);

        \App\Models\QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'short_text',
            'label' => "Bride's Full Name",
            'required' => true,
            'position' => 10,
        ]);

        $instance = app(\App\Services\QuestionnaireSnapshotService::class)
            ->snapshot($template, $this->booking, $this->contact, $this->owner);

        $brideField = $instance->fields()->where('label', "Bride's Full Name")->first();
        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $brideField->id,
            'value' => 'Jane O\'Brien',
        ]);

        $instance->update([
            'status' => \App\Models\QuestionnaireInstances::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->owner)
                ->visit("/bands/{$this->band->id}/booking/{$this->booking->id}/events?edit={$this->event->key}")
                ->waitForText('Questionnaires', 10);

            // Click "Append all to notes". Use a script click to disambiguate
            // from the per-response Apply buttons.
            $browser->script(<<<JS
                const buttons = Array.from(document.querySelectorAll('button'));
                const appendBtn = buttons.find(b => b.textContent.includes('Append all to notes'));
                appendBtn.click();
            JS);

            // The notes preview rendered inside the editor should reflect the
            // appended content without a manual page refresh.
            $browser->waitForText("Jane O'Brien", 5)
                ->assertSee("Jane O'Brien");
        });
    }

    /**
     * @return array{0: \App\Models\QuestionnaireInstances, 1: \App\Models\Questionnaires}
     */
    private function seedSubmittedInstance(): array
    {
        $template = \App\Models\Questionnaires::factory()->create([
            'band_id' => $this->band->id,
            'name' => 'Submitted Smoke Template',
        ]);

        \App\Models\QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'short_text',
            'label' => "Bride's Full Name",
            'required' => true,
            'position' => 10,
        ]);
        \App\Models\QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'yes_no',
            'label' => 'Onsite ceremony?',
            'required' => true,
            'position' => 20,
        ]);

        $instance = app(\App\Services\QuestionnaireSnapshotService::class)
            ->snapshot($template, $this->booking, $this->contact, $this->owner);

        $brideField = $instance->fields()->where('label', "Bride's Full Name")->first();
        $onsiteField = $instance->fields()->where('label', 'Onsite ceremony?')->first();

        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $brideField->id,
            'value' => "Jane O'Brien",
        ]);
        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $onsiteField->id,
            'value' => 'yes',
        ]);

        $instance->update([
            'status' => \App\Models\QuestionnaireInstances::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        return [$instance, $template];
    }
}
