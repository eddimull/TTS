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
        $band = Band::factory()->create(['site_name' => 'test-band']);
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [$file]]);  // Use indexed array format
        $request->merge(['type_id' => 1]);

        // Act
        $result = $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertEquals(1, $result);
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
        $request->files->add(['files' => [$file]]);
        $request->merge(['type_id' => 1]);

        // Act
        $result = $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertEquals(1, $result);
        $uploadedFile = ChartUploads::where('chart_id', $chart->id)->first();
        Storage::disk('s3')->assertExists($uploadedFile->url);
    }

    public function test_upload_data_handles_multiple_files()
    {
        // Arrange
        $band = Band::factory()->create(['site_name' => 'test-band']);
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file1 = UploadedFile::fake()->create('test1.pdf', 100);
        $file2 = UploadedFile::fake()->create('test2.pdf', 100);
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [$file1, $file2]]);
        $request->merge(['type_id' => 1]);

        // Act
        $result = $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertEquals(2, $result);
        $this->assertEquals(2, ChartUploads::where('chart_id', $chart->id)->count());
    }

    public function test_upload_data_sets_correct_file_type()
    {
        // Arrange
        $band = Band::factory()->create(['site_name' => 'test-band']);
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file = UploadedFile::fake()->create('test.pdf', 100, 'application/pdf');
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [$file]]);
        $request->merge(['type_id' => 1]);

        // Act
        $result = $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertEquals(1, $result);
        $this->assertDatabaseHas('chart_uploads', [
            'chart_id' => $chart->id,
            'fileType' => 'application/pdf',
        ]);
    }

    public function test_upload_data_throws_exception_when_no_files()
    {
        // Arrange
        $band = Band::factory()->create(['site_name' => 'test-band']);
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $request = new \Illuminate\Http\Request();
        $request->merge(['type_id' => 1]);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No files provided for upload');
        $this->chartsServices->uploadData($chart, $request);
    }

    public function test_upload_data_throws_exception_when_no_type_id()
    {
        // Arrange
        $band = Band::factory()->create(['site_name' => 'test-band']);
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file = UploadedFile::fake()->create('test.pdf', 100);
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [$file]]);

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chart and type_id are required');
        $this->chartsServices->uploadData($chart, $request);
    }

    public function test_upload_data_sanitizes_filename()
    {
        // Arrange
        $band = Band::factory()->create(['site_name' => 'test-band']);
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file = UploadedFile::fake()->create('test file @#$%.pdf', 100);
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [$file]]);
        $request->merge(['type_id' => 1]);

        // Act
        $result = $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertEquals(1, $result);
        $this->assertDatabaseHas('chart_uploads', [
            'chart_id' => $chart->id,
            'name' => 'test_file_____.pdf', // All special chars replaced with underscores
            'displayName' => 'test file @#$%.pdf',
        ]);
    }

    public function test_upload_data_handles_indexed_files()
    {
        // Arrange - Test files sent as files[0], files[1] format like from Vue
        $band = Band::factory()->create(['site_name' => 'test-band']);
        $chart = Chart::factory()->create(['band_id' => $band->id]);
        $file1 = UploadedFile::fake()->create('test1.pdf', 100);
        $file2 = UploadedFile::fake()->create('test2.pdf', 100);
        $request = new \Illuminate\Http\Request();
        $request->files->add(['files' => [0 => $file1, 1 => $file2]]);
        $request->merge(['type_id' => 1]);

        // Act
        $result = $this->chartsServices->uploadData($chart, $request);

        // Assert
        $this->assertEquals(2, $result);
        $this->assertEquals(2, ChartUploads::where('chart_id', $chart->id)->count());
    }
}
