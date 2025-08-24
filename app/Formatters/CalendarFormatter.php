<?php 

namespace App\Formatters;

use App\Models\Bands;
use Google\Service\Calendar\Calendar;

class CalendarFormatter
{
    public static function formatCalendar(Bands $band, string $type): Calendar
    {
        $calendar = new Calendar();

        $calendar->setSummary($band->name . ' - ' . self::calendarNameByType($type));
        $calendar->setDescription(self::getCalendarDescriptionByType($type, $band));

        return $calendar;
    }

    public static function calendarNameByType(string $type): string
    {
        switch ($type) {
            case 'booking':
                return 'Booking';
            case 'event':
                return 'Event';
            case 'public':
                return 'Public';
            default:
                return 'Unknown';
        }
    }

    public static function getCalendarDescriptionByType($type, Bands $band)
    {
       switch ($type) {
           case 'booking':
               return 'Private booking calendar for ' . $band->name . ' - Owners only';
           case 'events':
               return 'All events calendar for ' . $band->name . ' - Private and public events';
           case 'public':
               return 'Public events calendar for ' . $band->name . ' - Public events only';
           default:
               return 'Calendar for ' . $band->name;
       }
    }
}