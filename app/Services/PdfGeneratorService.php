<?php

namespace App\Services;

use Spatie\Browsershot\Browsershot;

class PdfGeneratorService
{
    /**
     * Generate PDF from HTML string
     *
     * @param string $html The HTML content to convert to PDF
     * @param string $format The page format (Legal, Letter, A4, etc.)
     * @param bool $taggedPdf Whether to generate a tagged/accessible PDF
     * @return string The PDF content as a string
     */
    public function generateFromHtml(string $html, string $format = 'Legal', bool $taggedPdf = false): string
    {
        $tempPath = storage_path('app/temp_pdf_' . uniqid() . '.pdf');

        $browsershot = Browsershot::html($html)
            ->setNodeBinary(config('browsershot.node_binary'))
            ->setNpmBinary(config('browsershot.npm_binary'))
            ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--headless'])
            ->setOption('executablePath', config('browsershot.executablePath'))
            ->format($format)
            ->showBackground();

        if ($taggedPdf) {
            $browsershot->taggedPdf();
        }

        $browsershot->savePdf($tempPath);

        $content = file_get_contents($tempPath);

        // Clean up temp file
        if (file_exists($tempPath)) {
            unlink($tempPath);
        }

        return $content;
    }

    /**
     * Generate Browsershot instance from URL (for legacy compatibility)
     *
     * @param string $url The URL to convert to PDF
     * @param string $format The page format
     * @return Browsershot
     */
    public function fromUrl(string $url, string $format = 'Legal'): Browsershot
    {
        return Browsershot::url($url)
            ->setNodeBinary(env('NODE_BINARY', '/usr/bin/node'))
            ->setNpmBinary(env('NPM_BINARY', '/usr/bin/npm'))
            ->format($format)
            ->showBackground();
    }
}
