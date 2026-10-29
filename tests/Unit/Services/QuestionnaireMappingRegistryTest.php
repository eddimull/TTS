<?php

namespace Tests\Unit\Services;

use App\Services\QuestionnaireMappingRegistry;
use Tests\TestCase;

class QuestionnaireMappingRegistryTest extends TestCase
{
    private QuestionnaireMappingRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new QuestionnaireMappingRegistry();
    }

    public function test_keys_returns_all_curated_targets(): void
    {
        $keys = $this->registry->keys();

        $this->assertCount(11, $keys);
        $this->assertContains('wedding.toasts', $keys);
        $this->assertContains('wedding.colors', $keys);
        $this->assertContains('performance.notes', $keys);
        $this->assertContains('event.notes', $keys);
        $this->assertContains('wedding.onsite', $keys);
        $this->assertContains('wedding.outside', $keys);
        $this->assertContains('wedding.dance.first', $keys);
        $this->assertContains('wedding.dance.father_daughter', $keys);
        $this->assertContains('wedding.dance.mother_son', $keys);
        $this->assertContains('wedding.dance.money', $keys);
        $this->assertContains('wedding.dance.bouquet_garter', $keys);
    }

    public function test_target_exists_returns_true_for_registered_key(): void
    {
        $this->assertTrue($this->registry->targetExists('wedding.onsite'));
    }

    public function test_target_exists_returns_false_for_unknown_key(): void
    {
        $this->assertFalse($this->registry->targetExists('wedding.nonexistent'));
    }

    public function test_compatible_field_types_for_yes_no_targets(): void
    {
        $this->assertSame(['yes_no'], $this->registry->compatibleFieldTypes('wedding.onsite'));
        $this->assertSame(['yes_no'], $this->registry->compatibleFieldTypes('wedding.outside'));
    }

    public function test_compatible_field_types_for_dance_targets(): void
    {
        $this->assertSame(['short_text', 'long_text'], $this->registry->compatibleFieldTypes('wedding.dance.first'));
        $this->assertSame(['short_text', 'long_text'], $this->registry->compatibleFieldTypes('wedding.toasts'));
    }

    public function test_preset_long_text_fields_are_compatible_with_their_targets(): void
    {
        // "Names and order of toasts", "Wedding colors" and "Anything else
        // we should know?" are long_text in the wedding preset.
        $this->assertTrue($this->registry->isCompatible('wedding.toasts', 'long_text'));
        $this->assertTrue($this->registry->isCompatible('wedding.colors', 'long_text'));
        $this->assertTrue($this->registry->isCompatible('performance.notes', 'long_text'));
    }

    public function test_notes_targets_accept_text_and_choice_types_but_not_song_picker(): void
    {
        foreach (['performance.notes', 'event.notes'] as $key) {
            foreach (['short_text', 'long_text', 'dropdown', 'multi_select', 'checkbox_group', 'yes_no', 'email', 'phone', 'date', 'time'] as $type) {
                $this->assertTrue($this->registry->isCompatible($key, $type), "{$key} should accept {$type}");
            }
            $this->assertFalse($this->registry->isCompatible($key, 'song_picker'));
        }
    }

    public function test_new_kinds_are_registered(): void
    {
        $this->assertSame(QuestionnaireMappingRegistry::TYPE_DANCE_ENTRY, $this->registry->kind('wedding.toasts'));
        $this->assertSame('Toasts', $this->registry->danceTitle('wedding.toasts'));
        $this->assertSame(QuestionnaireMappingRegistry::TYPE_TEXT_SET, $this->registry->kind('wedding.colors'));
        $this->assertSame(['additional_data', 'wedding', 'colors'], $this->registry->eventPath('wedding.colors'));
        $this->assertSame(QuestionnaireMappingRegistry::TYPE_TEXT_APPEND, $this->registry->kind('performance.notes'));
        $this->assertSame(['additional_data', 'performance', 'notes'], $this->registry->eventPath('performance.notes'));
        $this->assertSame(QuestionnaireMappingRegistry::TYPE_TEXT_APPEND, $this->registry->kind('event.notes'));
        $this->assertSame(['notes'], $this->registry->eventPath('event.notes'));
        $this->assertTrue($this->registry->prefixesLabel('event.notes'));
        $this->assertFalse($this->registry->prefixesLabel('performance.notes'));
    }

    public function test_every_target_has_a_known_group_and_short_label(): void
    {
        foreach ($this->registry->keys() as $key) {
            $this->assertArrayHasKey($this->registry->group($key), QuestionnaireMappingRegistry::GROUPS, $key);
            $this->assertNotSame('', $this->registry->shortLabel($key));
        }
        $this->assertSame('timeline', $this->registry->group('wedding.dance.first'));
        $this->assertSame('First dance', $this->registry->shortLabel('wedding.dance.first'));
        $this->assertSame('performance', $this->registry->group('performance.notes'));
        $this->assertSame('notes', $this->registry->group('event.notes'));
    }

    public function test_compatible_targets_for_field_type(): void
    {
        $this->assertSame(['wedding.onsite', 'wedding.outside', 'performance.notes', 'event.notes'], $this->registry->compatibleTargetsFor('yes_no'));
        $this->assertSame([], $this->registry->compatibleTargetsFor('song_picker'));
        $this->assertSame([], $this->registry->compatibleTargetsFor(null));
    }

    public function test_compatible_field_types_throws_for_unknown_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->compatibleFieldTypes('wedding.nonexistent');
    }

    public function test_label_returns_human_readable_target_name(): void
    {
        $this->assertSame('Wedding · Onsite Ceremony', $this->registry->label('wedding.onsite'));
        $this->assertSame('Wedding · First Dance', $this->registry->label('wedding.dance.first'));
    }

    public function test_dance_title_returns_array_title_for_dance_targets(): void
    {
        $this->assertSame('First Dance', $this->registry->danceTitle('wedding.dance.first'));
        $this->assertSame('Father Daughter', $this->registry->danceTitle('wedding.dance.father_daughter'));
        $this->assertSame('Mother Son', $this->registry->danceTitle('wedding.dance.mother_son'));
        $this->assertSame('Money Dance', $this->registry->danceTitle('wedding.dance.money'));
        $this->assertSame('Bouquet/Garter', $this->registry->danceTitle('wedding.dance.bouquet_garter'));
    }

    public function test_dance_title_returns_null_for_non_dance_targets(): void
    {
        $this->assertNull($this->registry->danceTitle('wedding.onsite'));
    }
}
