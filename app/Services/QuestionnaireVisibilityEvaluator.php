<?php

namespace App\Services;

class QuestionnaireVisibilityEvaluator
{
    /**
     * @param int $fieldId  The field whose visibility we're evaluating
     * @param array<int, array{id: int, visibility_rule: array|null}> $allFields
     * @param array<int, mixed> $responses  Keyed by field id
     */
    public function isVisible(int $fieldId, array $allFields, array $responses): bool
    {
        $field = $this->findField($fieldId, $allFields);
        if ($field === null) {
            return true;
        }
        return $this->fieldIsVisible($field, $allFields, $responses);
    }

    private function fieldIsVisible(array $field, array $allFields, array $responses): bool
    {
        $rule = $field['visibility_rule'] ?? null;
        if (empty($rule)) {
            return true;
        }

        $targetId = $rule['depends_on'] ?? null;
        $target = $this->findField($targetId, $allFields);
        if ($target === null) {
            return true;
        }

        if (!$this->fieldIsVisible($target, $allFields, $responses)) {
            return false;
        }

        $value = $responses[$targetId] ?? null;
        return $this->evaluate($rule, $value);
    }

    private function findField(?int $id, array $allFields): ?array
    {
        foreach ($allFields as $f) {
            if (($f['id'] ?? null) === $id) {
                return $f;
            }
        }
        return null;
    }

    /**
     * @param array{operator: string, value: mixed} $rule
     */
    private function evaluate(array $rule, mixed $value): bool
    {
        $operator = $rule['operator'];
        $expected = $rule['value'] ?? null;

        return match ($operator) {
            'equals' => $this->valueEquals($value, $expected),
            'not_equals' => !$this->valueEquals($value, $expected),
            'contains' => $this->valueContains($value, $expected),
            'empty' => $this->valueIsEmpty($value),
            'not_empty' => !$this->valueIsEmpty($value),
            default => false,
        };
    }

    private function valueEquals(mixed $value, mixed $expected): bool
    {
        if (is_array($value)) {
            return in_array($expected, $value, true);
        }
        return (string) $value === (string) $expected;
    }

    private function valueContains(mixed $value, mixed $expected): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && str_contains($item, (string) $expected)) {
                    return true;
                }
            }
            return false;
        }
        return is_string($value) && str_contains($value, (string) $expected);
    }

    private function valueIsEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return empty($value);
        }
        return $value === null || $value === '';
    }
}
