<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

class ImageControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function testReturnsAnExistingImage()
    {
        // Arrange
        $filename = 'test-image.jpg';
        $content = 'fake-image-content';

        Storage::disk('s3')->put($filename, $content);

        // Act
        $response = $this->get("/images/{$filename}");

        // Assert
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertSee($content);
    }

    public function testReturnsDefaultImageWhenRequestedImageDoesNotExist()
    {
        // Arrange
        $defaultContent = 'default-image-content';
        Storage::disk('s3')->put('default.png', $defaultContent);

        // Act
        $response = $this->get('/images/non-existent.jpg');

        // Assert
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee($defaultContent);
    }

    public function testReturnsSiteImageWhenExists()
    {
        // Arrange
        $bandSite = 'band123';
        $filename = 'profile.jpg';
        $path = "{$bandSite}/{$filename}";
        $content = 'band-image-content';

        Storage::disk('s3')->put($path, $content);

        // Act
        $response = $this->get("/images/{$bandSite}/{$filename}");

        // Assert
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertSee($content);
    }

    public function testReturnsDefaultImageWhenSiteImageDoesNotExist()
    {
        // Arrange
        $defaultContent = 'default-image-content';
        Storage::disk('s3')->put('default.png', $defaultContent);

        // Act
        $response = $this->get('/images/non-existent-band/non-existent.jpg');

        // Assert
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee($defaultContent);
    }
}
