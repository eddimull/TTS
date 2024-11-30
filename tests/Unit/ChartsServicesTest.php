<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ChartsServices;
use App\Models\Charts as Chart;
use App\Models\Bands as Band;
use App\Models\ChartUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChartsServicesTest extends TestCase
{
    use RefreshDatabase;

    protected $chartsServices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chartsServices = new ChartsServices();
        Storage::fake('s3');
    }

    public function test_upload_data_creates_chart_upload()
    {
        // Arrange
        $band = Band::factory()->create();
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [[$file]]]);  // Note the double array
        $request->merge(['type_id' => 1]);

        // Act
        $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertDatabaseHas('chart_uploads', [
            'chart_id' => $chart->id,
            'upload_type_id' => 1,
            'name' => 'test.pdf',
            'displayName' => 'test.pdf',
        ]);
    }

    public function test_upload_data_stores_file_in_s3()
    {
        // Arrange
        $band = Band::factory()->create(['site_name' => 'test-band']);
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [[$file]]]);
        $request->merge(['type_id' => 1]);

        // Act
        $this->chartsServices->uploadData($chart, $request);

        // Assert
        $uploadedFile = ChartUploads::where('chart_id', $chart->id)->first();
        Storage::disk('s3')->assertExists($uploadedFile->url);
    }

    public function test_upload_data_handles_multiple_files()
    {
        // Arrange
        $band = Band::factory()->create();
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file1 = UploadedFile::fake()->create('test1.pdf', 100);
        $file2 = UploadedFile::fake()->create('test2.pdf', 100);
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [[$file1, $file2]]]);
        $request->merge(['type_id' => 1]);

        // Act
        $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertEquals(2, ChartUploads::where('chart_id', $chart->id)->count());
    }

    public function test_upload_data_sets_correct_file_type()
    {
        // Arrange
        $band = Band::factory()->create();
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [[$file]]]);
        $request->merge(['type_id' => 1]);

        // Act
        $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertDatabaseHas('chart_uploads', [
            'chart_id' => $chart->id,
            'fileType' => 'application/pdf',
        ]);
    }
}
