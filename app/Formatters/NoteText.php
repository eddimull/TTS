<?php

namespace App\Formatters;

/**
 * Normalizes notes fields that may contain either plain text (the canonical
 * format) or legacy rich-text HTML authored by the old Quill editors, so
 * plain-text consumers (mobile API, calendar descriptions) never receive raw
 * markup. Mirrors resources/js/utils/noteText.js — keep the two in sync.
 */
class NoteText
{
    public static function isHtml(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        return (bool) preg_match('/<\/?[a-z][\s\S]*>/i', $text);
    }

    /**
     * Convert legacy rich-text HTML to plain text, preserving line structure:
     * one line per paragraph, newlines for <br>, bullet/numbered lines for
     * list items. Plain text passes through untouched.
     */
    public static function toPlainText(?string $text): ?string
    {
        if ($text === null || !self::isHtml($text)) {
            return $text;
        }

        $clean = preg_replace('/<br\s*\/?>/i', "\n", $text);

        $clean = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $clean);
        $clean = preg_replace('/<\/?p[^>]*>/i', '', $clean);

        $clean = preg_replace('/<\/?(ul|ol)[^>]*>/i', "\n", $clean);
        $clean = preg_replace('/<li[^>]*>/i', "\n  • ", $clean);
        $clean = str_ireplace('</li>', '', $clean);

        $clean = preg_replace('/<\/?h[1-6][^>]*>/i', "\n", $clean);

        $clean = strip_tags($clean);
        $clean = html_entity_decode($clean, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $clean = preg_replace("/\n{3,}/", "\n\n", $clean);

        return trim($clean);
    }
}
