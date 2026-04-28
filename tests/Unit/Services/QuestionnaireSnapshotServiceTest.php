<?php

namespace Tests\Unit\Services;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireFields;
use App\Models\Questionnaires;
use App\Models\User;
use App\Services\QuestionnaireSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuestionnaireSnapshotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuestionnaireSnapshotService();
    }

    public function test_snapshot_copies_template_fields_to_instance(): void
    {
        $template = Questionnaires::factory()->create(['name' => 'Wedding']);
        QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'header',
            'label' => 'Section A',
            'position' => 10,
        ]);
        QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'short_text',
            'label' => "Bride's Name",
            'required' => true,
            'position' => 20,
        ]);

        $booking = Bookings::factory()->create();
        $contact = Contacts::factory()->create();
        $user = User::factory()->create();

        $instance = $this->service->snapshot($template, $booking, $contact, $user);

        $this->assertSame('Wedding', $instance->name);
        $this->assertSame($booking->id, $instance->booking_id);
        $this->assertSame($contact->id, $instance->recipient_contact_id);
        $this->assertSame($user->id, $instance->sent_by_user_id);
        $this->assertSame('sent', $instance->status);
        $this->assertSame(2, $instance->fields()->count());

        $brideField = $instance->fields()->where('label', "Bride's Name")->first();
        $this->assertSame('short_text', $brideField->type);
        $this->assertTrue($brideField->required);
    }

    public function test_snapshot_rewrites_visibility_rule_to_new_field_ids(): void
    {
        $template = Questionnaires::factory()->create();

        $parent = QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'yes_no',
            'label' => 'Have a wedding party?',
            'position' => 10,
        ]);
        QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'short_text',
            'label' => 'How many people?',
            'position' => 20,
            'visibility_rule' => [
                'depends_on' => $parent->id,
                'operator' => 'equals',
                'value' => 'yes',
            ],
        ]);

        $instance = $this->service->snapshot(
            $template,
            Bookings::factory()->create(),
            Contacts::factory()->create(),
            User::factory()->create()
        );

        $newParent = $instance->fields()->where('label', 'Have a wedding party?')->first();
        $newChild = $instance->fields()->where('label', 'How many people?')->first();

        $this->assertSame($newParent->id, $newChild->visibility_rule['depends_on']);
    }

    public function test_snapshot_preserves_position_order(): void
    {
        $template = Questionnaires::factory()->create();
        QuestionnaireFields::factory()->create(['questionnaire_id' => $template->id, 'position' => 30, 'label' => 'Third']);
        QuestionnaireFields::factory()->create(['questionnaire_id' => $template->id, 'position' => 10, 'label' => 'First']);
        QuestionnaireFields::factory()->create(['questionnaire_id' => $template->id, 'position' => 20, 'label' => 'Second']);

        $instance = $this->service->snapshot(
            $template,
            Bookings::factory()->create(),
            Contacts::factory()->create(),
            User::factory()->create()
        );

        $labels = $instance->fields()->orderBy('position')->pluck('label')->all();
        $this->assertSame(['First', 'Second', 'Third'], $labels);
    }

    public function test_snapshot_handles_template_with_no_fields(): void
    {
        $template = Questionnaires::factory()->create();

        $instance = $this->service->snapshot(
            $template,
            Bookings::factory()->create(),
            Contacts::factory()->create(),
            User::factory()->create()
        );

        $this->assertSame(0, $instance->fields()->count());
    }
}
