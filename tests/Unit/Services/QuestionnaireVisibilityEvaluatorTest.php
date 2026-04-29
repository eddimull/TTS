<?php

namespace Tests\Unit\Services;

use App\Services\QuestionnaireVisibilityEvaluator;
use PHPUnit\Framework\TestCase;

class QuestionnaireVisibilityEvaluatorTest extends TestCase
{
    private QuestionnaireVisibilityEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new QuestionnaireVisibilityEvaluator();
    }

    /** Builds a flat field array with given fields each as an associative array */
    private function fields(array $fields): array
    {
        return $fields;
    }

    public function test_field_is_visible_when_no_rule_set(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
        ]);

        $this->assertTrue($this->evaluator->isVisible(1, $fields, []));
    }

    public function test_equals_operator_for_single_value_field(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'equals', 'value' => 'yes']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => 'yes']));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => 'no']));
    }

    public function test_equals_operator_for_multi_value_field(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'equals', 'value' => 'rock']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => ['rock', 'jazz']]));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => ['pop']]));
    }

    public function test_not_equals_operator(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'not_equals', 'value' => 'no']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => 'yes']));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => 'no']));
    }

    public function test_contains_operator_for_string(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'contains', 'value' => 'wedding']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => 'a wedding event']));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => 'birthday party']));
    }

    public function test_contains_operator_for_array(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'contains', 'value' => 'cake']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => ['I want cake', 'plus drinks']]));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => ['just drinks']]));
    }

    public function test_empty_operator(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'empty', 'value' => null]],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => '']));
        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => null]));
        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => []]));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => 'something']));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => ['x']]));
    }

    public function test_not_empty_operator(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'not_empty', 'value' => null]],
        ]);

        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => '']));
        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => 'something']));
        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => ['x']]));
    }

    public function test_field_is_hidden_when_controller_is_hidden_transitively(): void
    {
        // 1 is always visible. 2 depends on 1=hide. 3 depends on 2=anything.
        // When 1='show', 2 becomes hidden, so 3 must be hidden too.
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'equals', 'value' => 'hide']],
            ['id' => 3, 'visibility_rule' => ['depends_on' => 2, 'operator' => 'not_empty', 'value' => null]],
        ]);

        // 1 = 'show' so 2 is hidden → 3 cascades hidden
        $this->assertFalse($this->evaluator->isVisible(3, $fields, [1 => 'show', 2 => 'anything']));

        // 1 = 'hide' so 2 visible → 3 follows its own rule
        $this->assertTrue($this->evaluator->isVisible(3, $fields, [1 => 'hide', 2 => 'anything']));
        $this->assertFalse($this->evaluator->isVisible(3, $fields, [1 => 'hide', 2 => '']));
    }

    public function test_returns_true_when_target_field_does_not_exist(): void
    {
        // Defensive: missing target field shouldn't crash the evaluator.
        // Treat as "always visible" since we can't satisfy the rule.
        $fields = $this->fields([
            ['id' => 2, 'visibility_rule' => ['depends_on' => 999, 'operator' => 'equals', 'value' => 'x']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, []));
    }
}
