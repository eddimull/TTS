<?php

namespace Tests\Feature;

use Mockery;
use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use Illuminate\Support\Str;
use Google\Service\Calendar;
use App\Models\BandCalendars;
use App\Models\CalendarAccess;
use App\Services\CalendarService;
use Illuminate\Http\UploadedFile;
use Google\Service\Calendar\AclRule;
use App\Services\GoogleCalendarService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Testing\FileFactory;
use Spatie\GoogleCalendar\GoogleCalendar;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BandsControllerTest extends TestCase
{
    private $band;
    private $owner;
    private $member;
    private $nonMember;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->nonMember = User::factory()->create();

        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->band->members()->create(['user_id' => $this->member->id]);
    }
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_upload_logo(): void
    {
        Storage::fake('s3');

        $logo = UploadedFile::fake()->image('logo.png');
        $response = $this->actingAs($this->owner)->post(route('bands.uploadLogo', $this->band), [
            'logo' => $logo,
        ]);

        $imageName = Str::slug($this->band->name) . '-logo-' . time() . '.' . $logo->extension();
        $response->assertSessionHas('successMessage');
        $response->assertStatus(302);
        Storage::disk('s3')->assertExists($this->band->site_name . '/' . $imageName);
        $this->assertDatabaseHas('bands', [
            'id' => $this->band->id,
            'logo' => '/images/' . $this->band->site_name . '/' . $imageName,
        ]);
    }

    public function test_random_user_cannot_upload_logo(): void
    {
        Storage::fake('s3');
        $logo = UploadedFile::fake()->image('logo.png');
        $response = $this->actingAs($this->nonMember)->post(route('bands.uploadLogo', $this->band), [
            'logo' => $logo,
        ]);

        $response->assertStatus(403);
    }

    public function test_user_can_be_granted_access_to_calendar(): void
    {
        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('addAccess')->once();
        $mockService->shouldReceive('getCalendar')->once();
        $calendar = BandCalendars::factory()->create(['band_id' => $this->band->id]);


        $response = $this->actingAs($this->owner)->post(route('bands.grantCalendarAccess', [$calendar]), [
            'user_id' => $this->member->id,
            'role' => 'owner',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('successMessage');

        $this->assertDatabaseHas('calendar_access', [
            'user_id' => $this->member->id,
            'band_calendar_id' => $calendar->id,
            'role' => 'owner',
        ]);

        Mockery::close();
    }

    public function test_user_access_can_be_revoked(): void
    {
        $mockService = $this->mock(GoogleCalendarService::class);
        $mockService->shouldReceive('revokeAccess')->once();
        $mockService->shouldReceive('findAccess')->once();
        $mockService->shouldReceive('getCalendar')->once();
        $calendar = BandCalendars::factory()->create(['band_id' => $this->band->id]);
        CalendarAccess::create([
            'band_calendar_id' => $calendar->id,
            'user_id' => $this->member->id,
            'role' => 'writer',
        ]);

        $response = $this->actingAs($this->owner)->delete(route('bands.revokeCalendarAccess', [$calendar, $this->member]));

        $response->assertStatus(302);
        $response->assertSessionHas('successMessage');

        $this->assertDatabaseMissing('calendar_access', [
            'user_id' => $this->member->id,
            'band_calendar_id' => $calendar->id,
        ]);

        Mockery::close();
    }
}
