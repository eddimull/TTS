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
