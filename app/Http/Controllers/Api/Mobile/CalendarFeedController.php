<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendarFeedController extends Controller
{
    /**
     * Return the authenticated user's calendar subscription URLs.
     *
     * Mints the calendar token on first call. The app uses these to offer
     * "Subscribe in Google Calendar" (google_subscribe_url) and a generic
     * "Copy link" for Apple Calendar / Outlook (webcal_url).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($this->payload($user));
    }

    /**
     * Regenerate the token, invalidating the previously shared feed URL, and
     * return the new URLs.
     */
    public function reset(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->regenerateCalendarToken();

        return response()->json($this->payload($user));
    }

    /**
     * @return array<string, string>
     */
    private function payload(User $user): array
    {
        $token = $user->getCalendarToken();

        // Absolute https URL to the public ICS feed.
        $httpsUrl = route('calendar.feed', ['token' => $token . '.ics']);

        // webcal:// makes Apple Calendar / Outlook subscribe (rather than
        // download) when the link is tapped.
        $webcalUrl = preg_replace('/^https?:\/\//', 'webcal://', $httpsUrl);

        // Deep link that opens Google Calendar's "add by URL" flow prefilled
        // with the feed. Google expects the feed URL (webcal or https) in cid.
        $googleSubscribeUrl = 'https://calendar.google.com/calendar/r?cid=' . urlencode($webcalUrl);

        return [
            'url'                  => $httpsUrl,
            'webcal_url'           => $webcalUrl,
            'google_subscribe_url' => $googleSubscribeUrl,
        ];
    }
}
