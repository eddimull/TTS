<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Bands;
use App\Models\BandCalendars;
use App\Models\CalendarAccess;
use App\Services\CalendarService;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function createMockCalendarService($band, $calendarType = null)
    {
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        return new class($band, $calendarType) extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }
            
            // Override setGoogleClient to prevent file existence check
            private function setGoogleClient()
            {
                $this->googleClient = $this->createGoogleClient();
            }
            
            // Override setGoogleCalendar to prevent Google API calls
            private function setGoogleCalendar()
            {
                // Skip the actual Google Calendar setup in tests
                $this->googleCalendar = null;
            }
        };
    }

    public function test_can_instantiate_with_a_band()
    {
        // Mock the Google credentials path so it doesn't try to access real files
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        // Create a real band using factory
        $band = Bands::factory()->create([
            'name' => 'Test Band',
        ]);

        // Create testable service that bypasses Google API calls
        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                // Return a mock client that doesn't make real API calls
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }
        };

        $this->assertInstanceOf(CalendarService::class, $service);
    }


    public function test_creates_band_calendar_by_type()
    {
        // Mock the Google credentials path
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        // Create a real band using factory
        $band = Bands::factory()->create([
            'name' => 'Test Band'
        ]);

        // Create testable service that mocks Google API calls
        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                // Return a mock client that doesn't make real API calls
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }
        };

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('createBandCalendarByType');
        $method->setAccessible(true);

        // Test creating a booking calendar
        $result = $method->invoke($service, 'booking');

        // Since we're mocking the Google API, this will return null
        // But we can verify the method executes without errors
        $this->assertNull($result); // Expected since we're not making real API calls

        // Verify no BandCalendars were created in the database since Google API is mocked
        $this->assertDatabaseMissing('band_calendars', [
            'band_id' => $band->id,
            'type' => 'booking'
        ]);
    }

    public function test_create_all_band_calendars()
    {
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        $band = Bands::factory()->create(['name' => 'Test Band']);

        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }

            public function createBandCalendarByType($type)
            {
                // Mock successful calendar creation
                BandCalendars::create([
                    'band_id' => $this->band->id,
                    'calendar_id' => 'mock_calendar_id_' . $type,
                    'type' => $type
                ]);
                return 'mock_calendar_id_' . $type;
            }
        };

        $results = $service->createAllBandCalendars();

        $this->assertCount(3, $results);
        $this->assertArrayHasKey('booking', $results);
        $this->assertArrayHasKey('event', $results);
        $this->assertArrayHasKey('public', $results);
        
        $this->assertDatabaseHas('band_calendars', [
            'band_id' => $band->id,
            'type' => 'booking'
        ]);
        $this->assertDatabaseHas('band_calendars', [
            'band_id' => $band->id,
            'type' => 'event'
        ]);
        $this->assertDatabaseHas('band_calendars', [
            'band_id' => $band->id,
            'type' => 'public'
        ]);
    }

    public function test_get_calendar_name_by_type()
    {
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        $band = Bands::factory()->create(['name' => 'Test Band']);

        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }

            public function testGetCalendarNameByType($type)
            {
                return $this->getCalendarNameByType($type);
            }
        };

        $this->assertEquals('Test Band - Bookings (Private)', $service->testGetCalendarNameByType('booking'));
        $this->assertEquals('Test Band - All Events', $service->testGetCalendarNameByType('events'));
        $this->assertEquals('Test Band - Public Events', $service->testGetCalendarNameByType('public'));
        $this->assertEquals('Test Band - Calendar', $service->testGetCalendarNameByType('unknown'));
    }

    public function test_get_calendar_description_by_type()
    {
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        $band = Bands::factory()->create(['name' => 'Test Band']);

        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }

            public function testGetCalendarDescriptionByType(String $type)
            {
                return $this->getCalendarDescriptionByType($type);
            }
        };

        $this->assertStringContainsString('Private booking calendar for Test Band', $service->testGetCalendarDescriptionByType('booking'));
        $this->assertStringContainsString('All events calendar for Test Band', $service->testGetCalendarDescriptionByType('events'));
        $this->assertStringContainsString('Public events calendar for Test Band', $service->testGetCalendarDescriptionByType('public'));
        $this->assertStringContainsString('Calendar for Test Band', $service->testGetCalendarDescriptionByType('unknown'));
    }

    public function test_get_booking_status_color()
    {
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        $band = Bands::factory()->create();

        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }

            public function testGetBookingStatusColor($status)
            {
                return $this->getBookingStatusColor($status);
            }
        };

        $this->assertEquals(8, $service->testGetBookingStatusColor('draft'));
        $this->assertEquals(5, $service->testGetBookingStatusColor('pending'));
        $this->assertEquals(10, $service->testGetBookingStatusColor('confirmed'));
        $this->assertEquals(1, $service->testGetBookingStatusColor('unknown'));
    }

    public function test_user_has_access_to_calendar_type()
    {
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        $band = Bands::factory()->create();
        $user = User::factory()->create();
        
        // Create a calendar for the band
        $calendar = BandCalendars::create([
            'band_id' => $band->id,
            'calendar_id' => 'test_calendar_id',
            'type' => 'booking'
        ]);
        
        // Grant access to the user
        CalendarAccess::create([
            'user_id' => $user->id,
            'band_calendar_id' => $calendar->id,
            'role' => 'writer'
        ]);

        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }
        };

        $this->assertTrue($service->userHasAccessToCalendarType($user, 'booking'));
        $this->assertTrue($service->userHasAccessToCalendarType($user, 'booking', 'writer'));
        $this->assertFalse($service->userHasAccessToCalendarType($user, 'booking', 'owner'));
        $this->assertFalse($service->userHasAccessToCalendarType($user, 'events'));
    }

    public function test_get_user_calendar_access()
    {
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        $band = Bands::factory()->create();
        $user = User::factory()->create();
        
        // Create calendars for the band
        $bookingCalendar = BandCalendars::create([
            'band_id' => $band->id,
            'calendar_id' => 'booking_calendar_id',
            'type' => 'booking'
        ]);
        
        $eventsCalendar = BandCalendars::create([
            'band_id' => $band->id,
            'calendar_id' => 'events_calendar_id',
            'type' => 'event'
        ]);
        
        // Grant access to booking calendar only
        CalendarAccess::create([
            'user_id' => $user->id,
            'band_calendar_id' => $bookingCalendar->id,
            'role' => 'writer'
        ]);

        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }
        };

        $access = $service->getUserCalendarAccess($user);
        
        $this->assertCount(1, $access);
        $this->assertContains('booking', $access);
        $this->assertNotContains('events', $access);
    }

    public function test_user_has_minimum_role()
    {
        $band = Bands::factory()->create();
        $calendar = BandCalendars::create([
            'band_id' => $band->id,
            'calendar_id' => 'test_calendar_id',
            'type' => 'booking'
        ]);
        
        $user = User::factory()->create();
        CalendarAccess::create([
            'user_id' => $user->id,
            'band_calendar_id' => $calendar->id,
            'role' => 'writer'
        ]);

        $service = new class($band, 'booking') extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }
            
            // Override setGoogleClient to prevent file existence check
            private function setGoogleClient()
            {
                $this->googleClient = $this->createGoogleClient();
            }
            
            // Override setGoogleCalendar to prevent Google API calls
            private function setGoogleCalendar()
            {
                // Skip the actual Google Calendar setup in tests
                $this->googleCalendar = null;
            }
        };

        $this->assertTrue($service->userHasMinimumRole($user, 'reader'));
        $this->assertTrue($service->userHasMinimumRole($user, 'writer'));
        $this->assertFalse($service->userHasMinimumRole($user, 'owner'));
    }

    public function test_map_role_to_acl_role()
    {
        Config::set('google-calendar.auth_profiles.service_account.credentials_json', '/fake/path/credentials.json');
        
        $band = Bands::factory()->create();

        $service = new class($band) extends CalendarService {
            protected function createGoogleClient()
            {
                return new class {
                    public function setAuthConfig($path) {}
                    public function addScope($scope) {}
                };
            }

            public function testMapRoleToAclRole($role)
            {
                return $this->mapRoleToAclRole($role);
            }
        };

        $this->assertEquals('owner', $service->testMapRoleToAclRole('owner'));
        $this->assertEquals('writer', $service->testMapRoleToAclRole('writer'));
        $this->assertEquals('reader', $service->testMapRoleToAclRole('reader'));
        $this->assertEquals('reader', $service->testMapRoleToAclRole('unknown'));
    }
}