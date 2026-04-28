<?php

namespace Tests\Unit\Services;

use App\Services\FieldSettingsValidator;
use App\Services\QuestionnaireFieldTypeRegistry;
use PHPUnit\Framework\TestCase;

class FieldSettingsValidatorTest extends TestCase
{
    private FieldSettingsValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FieldSettingsValidator(new QuestionnaireFieldTypeRegistry());
    }

    public function test_short_text_accepts_null_settings(): void
    {
        $errors = $this->validator->validate('short_text', null);
        $this->assertEmpty($errors);
    }

    public function test_short_text_accepts_empty_array_settings(): void
    {
        $errors = $this->validator->validate('short_text', []);
        $this->assertEmpty($errors);
    }

    public function test_dropdown_rejects_null_settings(): void
    {
        $errors = $this->validator->validate('dropdown', null);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('options', strtolower(implode(' ', $errors)));
    }

    public function test_dropdown_rejects_empty_options_array(): void
    {
        $errors = $this->validator->validate('dropdown', ['options' => []]);
        $this->assertNotEmpty($errors);
    }

    public function test_dropdown_accepts_valid_options(): void
    {
        $errors = $this->validator->validate('dropdown', [
            'options' => [
                ['value' => 'a', 'label' => 'Option A'],
                ['value' => 'b', 'label' => 'Option B'],
            ],
        ]);
        $this->assertEmpty($errors);
    }

    public function test_dropdown_rejects_options_missing_value_or_label(): void
    {
        $errors = $this->validator->validate('dropdown', [
            'options' => [['value' => 'a']],
        ]);
        $this->assertNotEmpty($errors);
    }

    public function test_multi_select_validates_same_as_dropdown(): void
    {
        $errors = $this->validator->validate('multi_select', null);
        $this->assertNotEmpty($errors);

        $errors = $this->validator->validate('multi_select', [
            'options' => [['value' => 'x', 'label' => 'X']],
        ]);
        $this->assertEmpty($errors);
    }

    public function test_checkbox_group_validates_same_as_dropdown(): void
    {
        $errors = $this->validator->validate('checkbox_group', null);
        $this->assertNotEmpty($errors);

        $errors = $this->validator->validate('checkbox_group', [
            'options' => [['value' => 'x', 'label' => 'X']],
        ]);
        $this->assertEmpty($errors);
    }

    public function test_unknown_type_returns_error(): void
    {
        $errors = $this->validator->validate('mystery_type', null);
        $this->assertNotEmpty($errors);
    }
}
