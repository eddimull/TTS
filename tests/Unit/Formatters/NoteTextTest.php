<?php

namespace Tests\Unit\Formatters;

use App\Formatters\NoteText;
use PHPUnit\Framework\TestCase;

class NoteTextTest extends TestCase
{
    public function test_plain_text_passes_through_unchanged(): void
    {
        $notes = "Setlist:\nSong A\nSong B";

        $this->assertSame($notes, NoteText::toPlainText($notes));
    }

    public function test_null_passes_through(): void
    {
        $this->assertNull(NoteText::toPlainText(null));
    }

    public function test_bare_less_than_sign_is_not_treated_as_html(): void
    {
        $notes = 'tempo < 120 bpm';

        $this->assertSame($notes, NoteText::toPlainText($notes));
    }

    public function test_quill_paragraphs_become_separate_lines(): void
    {
        $this->assertSame(
            "Setlist:\nSong A\nSong B",
            NoteText::toPlainText('<p>Setlist:</p><p>Song A</p><p>Song B</p>')
        );
    }

    public function test_br_tags_become_newlines(): void
    {
        $this->assertSame(
            "line one\nline two",
            NoteText::toPlainText('<p>line one<br>line two</p>')
        );
    }

    public function test_empty_quill_paragraph_becomes_blank_line(): void
    {
        $this->assertSame(
            "one\n\ntwo",
            NoteText::toPlainText('<p>one</p><p><br></p><p>two</p>')
        );
    }

    public function test_list_items_become_bullet_lines(): void
    {
        $this->assertSame(
            "• first\n  • second",
            NoteText::toPlainText('<ul><li>first</li><li>second</li></ul>')
        );
    }

    public function test_inline_formatting_is_stripped_without_losing_content(): void
    {
        $this->assertSame(
            'play loud and fast',
            NoteText::toPlainText('<p>play <strong>loud</strong> and <em>fast</em></p>')
        );
    }

    public function test_html_entities_are_decoded(): void
    {
        $this->assertSame(
            'Rock & Roll',
            NoteText::toPlainText('<p>Rock &amp; Roll</p>')
        );
    }

    public function test_is_html_detection(): void
    {
        $this->assertTrue(NoteText::isHtml('<p>notes</p>'));
        $this->assertFalse(NoteText::isHtml("plain\ntext"));
        $this->assertFalse(NoteText::isHtml(null));
        $this->assertFalse(NoteText::isHtml(''));
    }
}
