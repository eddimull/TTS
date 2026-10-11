<?php

namespace App\Services;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;

/**
 * Attach a client contact to a booking — shared by the mobile add-contact
 * endpoint and the band developer API / MCP (create_booking, add_booking_contact).
 */
class BookingContactService
{
    /**
     * Find or create the band's contact and attach it to the booking.
     *
     * With `contact_id` the contact must belong to the band (404 otherwise).
     * Otherwise the band's contact with this email is reused (its name/phone
     * are left as they are) or a new one is created. Contacts are never
     * matched across bands. Re-attaching an attached contact updates its role
     * and is_primary.
     *
     * @param  array{contact_id?: int|null, name?: string, email?: string, phone?: string|null, role?: string|null, is_primary?: bool|null}  $data
     */
    public function attach(Bands $band, Bookings $booking, array $data): Contacts
    {
        if (! empty($data['contact_id'])) {
            $contact = Contacts::where('band_id', $band->id)->findOrFail($data['contact_id']);
        } else {
            $contact = Contacts::firstOrCreate(
                ['band_id' => $band->id, 'email' => $data['email']],
                ['name' => $data['name'], 'phone' => $data['phone'] ?? null]
            );
        }

        $booking->contacts()->syncWithoutDetaching([
            $contact->id => [
                'role'       => $data['role'] ?? null,
                'is_primary' => $data['is_primary'] ?? false,
            ],
        ]);

        return $contact;
    }
}
