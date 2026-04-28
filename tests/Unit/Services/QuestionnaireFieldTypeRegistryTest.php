<?php

namespace Tests\Unit\Services;

use App\Services\QuestionnaireFieldTypeRegistry;
use PHPUnit\Framework\TestCase;

class QuestionnaireFieldTypeRegistryTest extends TestCase
{
    private QuestionnaireFieldTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new QuestionnaireFieldTypeRegistry();
    }

    public function test_known_types_returns_all_twelve_field_types(): void
    {
        $types = $this->registry->knownTypes();

        $this->assertCount(12, $types);
        $this->assertContains('short_text', $types);
        $this->assertContains('long_text', $types);
        $this->assertContains('date', $types);
        $this->assertContains('time', $types);
        $this->assertContains('email', $types);
        $this->assertContains('phone', $types);
        $this->assertContains('dropdown', $types);
        $this->assertContains('multi_select', $types);
        $this->assertContains('checkbox_group', $types);
        $this->assertContains('yes_no', $types);
        $this->assertContains('header', $types);
        $this->assertContains('instructions', $types);
    }

    public function test_is_known_type_returns_true_for_registered_types(): void
    {
        $this->assertTrue($this->registry->isKnownType('short_text'));
        $this->assertTrue($this->registry->isKnownType('header'));
    }

    public function test_is_known_type_returns_false_for_unknown_type(): void
    {
        $this->assertFalse($this->registry->isKnownType('song_picker'));
        $this->assertFalse($this->registry->isKnownType(''));
    }

    public function test_is_input_type_returns_false_for_header_and_instructions(): void
    {
        $this->assertFalse($this->registry->isInputType('header'));
        $this->assertFalse($this->registry->isInputType('instructions'));
    }

    public function test_is_input_type_returns_true_for_actual_inputs(): void
    {
        $this->assertTrue($this->registry->isInputType('short_text'));
        $this->assertTrue($this->registry->isInputType('dropdown'));
        $this->assertTrue($this->registry->isInputType('yes_no'));
    }

    public function test_definitions_includes_label_for_every_type(): void
    {
        foreach ($this->registry->knownTypes() as $type) {
            $def = $this->registry->definition($type);
            $this->assertArrayHasKey('label', $def);
            $this->assertNotEmpty($def['label']);
        }
    }

    public function test_definition_throws_for_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->definition('nonexistent');
    }

    public function test_dropdown_definition_marks_options_as_required_setting(): void
    {
        $def = $this->registry->definition('dropdown');
        $this->assertContains('options', $def['required_settings'] ?? []);
    }
}
