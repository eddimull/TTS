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

    public function test_keys_returns_all_seven_curated_targets(): void
    {
        $keys = $this->registry->keys();

        $this->assertCount(7, $keys);
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
        $this->assertSame(['short_text'], $this->registry->compatibleFieldTypes('wedding.dance.first'));
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
