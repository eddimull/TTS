<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Contacts;
use App\Notifications\ContactPortalAccessGranted;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ContactPortalService
{
    /**
     * Grant portal access to all contacts on a booking after contract completion
     * Only grants access if:
     * - The booking has a contract (not 'none')
     * - The contact doesn't already have portal access
     *
     * @param Bookings $booking
     * @return void
     */
    public function grantPortalAccessAfterContractCompletion(Bookings $booking): void
    {
        // Only grant access if contract option is not 'none'
        if ($booking->contract_option === 'none') {
            Log::info('Skipping portal access grant - booking has no contract', [
                'booking_id' => $booking->id,
                'booking_name' => $booking->name
            ]);
            return;
        }

        // Get all contacts for this booking
        $contacts = $booking->contacts;

        if ($contacts->isEmpty()) {
            Log::warning('No contacts found for booking', [
                'booking_id' => $booking->id,
                'booking_name' => $booking->name
            ]);
            return;
        }

        foreach ($contacts as $contact) {
            $this->grantPortalAccess($contact, $booking);
        }
    }

    /**
     * Grant portal access to a specific contact
     *
     * @param Contacts $contact
     * @param Bookings $booking
     * @return bool Returns true if access was granted, false if already had access
     */
    public function grantPortalAccess(Contacts $contact, Bookings $booking): bool
    {
        // Check if contact already has portal access
        if ($contact->can_login) {
            Log::info('Contact already has portal access', [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email
            ]);
            return false;
        }

        // Generate temporary password
        $temporaryPassword = Str::random(16);

        // Update contact with login access
        $contact->update([
            'password' => Hash::make($temporaryPassword),
            'can_login' => true,
            'password_change_required' => true,
        ]);

        // Send email notification with credentials
        try {
            $contact->notify(new ContactPortalAccessGranted(
                $temporaryPassword,
                $booking->name,
                $booking->band->name
            ));

            Log::info('Portal access granted and notification sent', [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
                'booking_id' => $booking->id,
                'booking_name' => $booking->name
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send portal access notification', [
                'contact_id' => $contact->id,
                'contact_email' => $contact->email,
                'error' => $e->getMessage()
            ]);

            // Rollback the access grant on notification failure
            $contact->update(['can_login' => false]);

            throw $e;
        }
    }
}
