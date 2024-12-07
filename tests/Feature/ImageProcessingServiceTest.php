<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageProcessingService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ImageProcessingServiceTest extends TestCase
{
    protected $imageProcessor;
    protected $validBase64Image = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    protected $invalidBase64Data = 'invalid-base64-data';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        $this->imageProcessor = new ImageProcessingService();
    }

    public function testProcessAndUploadWithValidBase64Data(): void
    {
        $result = $this->imageProcessor->processAndUpload($this->validBase64Image);

        $this->assertNotNull($result);
        $this->assertStringContainsString('.png', $result);
        Storage::disk('s3')->assertExists(str_replace(Storage::disk('s3')->url(''), '', $result));
    }

    public function testProcessAndUploadWithInvalidBase64Data(): void
    {
        $result = $this->imageProcessor->processAndUpload($this->invalidBase64Data);

        $this->assertNull($result);
    }

    public function testProcessAndUploadWithCustomPath(): void
    {
        $customPath = 'custom/path';
        $result = $this->imageProcessor->processAndUpload($this->validBase64Image, $customPath);

        $this->assertNotNull($result);
        $this->assertStringContainsString($customPath, $result);
    }

    public function testProcessContentWithEmptyString(): void
    {
        $emptyString = '';
        $result = $this->imageProcessor->processContent($emptyString);

        $this->assertEquals($emptyString, $result);
    }

    public function testProcessContentWithNoImages(): void
    {
        $htmlContent = '<p>Test content without images</p>';
        $result = $this->imageProcessor->processContent($htmlContent);

        $this->assertEquals($htmlContent, $result);
    }

    public function testProcessContentWithBase64Image(): void
    {
        $htmlContent = '<p>Test content</p><img src="' . $this->validBase64Image . '" alt="test">';
        $result = $this->imageProcessor->processContent($htmlContent);
        $this->assertNotEquals($htmlContent, $result);
        $this->assertStringContainsString('<img', $result);
        $this->assertStringNotContainsString('base64', $result);
    }

    public function testProcessContentWithMultipleImages(): void
    {
        $htmlContent =
            '<img src="' . $this->validBase64Image . '" alt="test1">' .
            '<p>Some text</p>' .
            '<img src="' . $this->validBase64Image . '" alt="test2">';

        $result = $this->imageProcessor->processContent($htmlContent);

        $this->assertNotEquals($htmlContent, $result);
        $this->assertEquals(0, substr_count($result, 'base64'));
    }

    public function testProcessContentWithInvalidBase64Image(): void
    {
        $htmlContent = '<img src="' . $this->invalidBase64Data . '" alt="test">';
        $result = $this->imageProcessor->processContent($htmlContent);

        $this->assertEquals($htmlContent, $result);
    }

    public function testProcessContentWithMixedValidAndInvalidImages(): void
    {
        $htmlContent =
            '<img src="' . $this->validBase64Image . '" alt="valid">' .
            '<img src="' . $this->invalidBase64Data . '" alt="invalid">';

        $result = $this->imageProcessor->processContent($htmlContent);

        $this->assertStringContainsString($this->invalidBase64Data, $result);
    }

    public function testExceptionHandling(): void
    {
        // Mock Storage to throw an exception
        Storage::shouldReceive('disk->put')->andThrow(new \Exception('Storage error'));

        $result = $this->imageProcessor->processAndUpload($this->validBase64Image);

        $this->assertNull($result);
    }
}
