import { describe, expect, it } from 'vitest';
import { htmlToPlainText, isHtmlContent, noteToPlainText } from '@/utils/noteText';

describe('noteText', () => {
  describe('isHtmlContent', () => {
    it('detects Quill-authored HTML', () => {
      expect(isHtmlContent('<p>Practice setlist</p><p>Bring charts</p>')).toBe(true);
    });

    it('treats plain multiline text as plain text', () => {
      expect(isHtmlContent('Practice setlist\nBring charts')).toBe(false);
    });

    it('handles empty and null values', () => {
      expect(isHtmlContent('')).toBe(false);
      expect(isHtmlContent(null)).toBe(false);
      expect(isHtmlContent(undefined)).toBe(false);
    });

    it('does not flag a bare less-than sign', () => {
      expect(isHtmlContent('tempo < 120 bpm')).toBe(false);
    });
  });

  describe('htmlToPlainText', () => {
    it('converts paragraphs to separate lines', () => {
      expect(htmlToPlainText('<p>line one</p><p>line two</p>')).toBe('line one\nline two');
    });

    it('converts <br> to newlines', () => {
      expect(htmlToPlainText('<p>line one<br>line two</p>')).toBe('line one\nline two');
    });

    it('converts unordered lists to bullet lines', () => {
      expect(htmlToPlainText('<ul><li>first</li><li>second</li></ul>'))
        .toBe('• first\n  • second');
    });

    it('converts ordered lists to numbered lines', () => {
      expect(htmlToPlainText('<ol><li>first</li><li>second</li></ol>'))
        .toBe('1. first\n2. second');
    });

    it('uppercases headings', () => {
      expect(htmlToPlainText('<h2>Setlist</h2><p>Song A</p>')).toBe('SETLIST\nSong A');
    });

    it('keeps inline formatting content without tags', () => {
      expect(htmlToPlainText('<p>play <strong>loud</strong> and <em>fast</em></p>'))
        .toBe('play loud and fast');
    });

    it('collapses runs of blank lines to at most one', () => {
      expect(htmlToPlainText('<p>one</p><p><br></p><p><br></p><p>two</p>'))
        .toBe('one\n\ntwo');
    });
  });

  describe('noteToPlainText', () => {
    it('passes plain text through unchanged, preserving newlines', () => {
      expect(noteToPlainText('line one\nline two')).toBe('line one\nline two');
    });

    it('converts legacy HTML notes', () => {
      expect(noteToPlainText('<p>line one</p><p>line two</p>')).toBe('line one\nline two');
    });

    it('returns empty string for null/undefined', () => {
      expect(noteToPlainText(null)).toBe('');
      expect(noteToPlainText(undefined)).toBe('');
    });
  });
});
