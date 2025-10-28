<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Payments;
use App\Models\Contracts;
use App\Models\EventTypes;
use Carbon\Carbon;
use Spatie\Activitylog\Models\Activity;

class BookingActivityService
{
    /**
     * Get comprehensive activity history for a booking
     * Includes activities from: booking, contacts, payments, and contracts
     *
     * @param  Bookings  $booking
     * @return \Illuminate\Support\Collection
     */
    public function getBookingTimeline(Bookings $booking)
    {
        $activities = collect();

        // 1. Get booking activities
        $bookingActivities = $booking->activities()
            ->with('causer')
            ->get()
            ->map(function ($activity) {
                return $this->formatActivity($activity, 'booking');
            });
        $activities = $activities->merge($bookingActivities);

        // 2. Get contact activities (from booking_contacts pivot)
        $contactActivities = Activity::inLog('booking_contacts')
            ->where(function ($query) use ($booking) {
                $query->where('properties->attributes->booking_id', $booking->id)
                    ->orWhere('properties->old->booking_id', $booking->id);
            })
            ->with('causer')
            ->get()
            ->map(function ($activity) {
                return $this->formatActivity($activity, 'contact');
            });
        $activities = $activities->merge($contactActivities);

        // 3. Get contact information changes (from contacts table)
        $bookingContactIds = $booking->contacts()->pluck('contacts.id');
        if ($bookingContactIds->isNotEmpty()) {
            $contactInfoActivities = Activity::inLog('contacts')
                ->whereIn('subject_id', $bookingContactIds)
                ->with('causer')
                ->get()
                ->map(function ($activity) {
                    return $this->formatActivity($activity, 'contact_info');
                });
            $activities = $activities->merge($contactInfoActivities);
        }

        // 4. Get payment activities
        $paymentActivities = $booking->payments()
            ->get()
            ->flatMap(function ($payment) {
                return $payment->activities()
                    ->with('causer')
                    ->get()
                    ->map(function ($activity) {
                        return $this->formatActivity($activity, 'payment');
                    });
            });
        $activities = $activities->merge($paymentActivities);

        // 5. Get contract activities (via morphTo relationship)
        if ($booking->contract) {
            $contractActivities = $booking->contract->activities()
                ->with('causer')
                ->get()
                ->map(function ($activity) {
                    return $this->formatActivity($activity, 'contract');
                });
            $activities = $activities->merge($contractActivities);
        }

        // Sort by created_at descending (most recent first)
        return $activities->sortByDesc('created_at')->values();
    }

    /**
     * Format an activity for display
     *
     * @param  Activity  $activity
     * @param  string  $category
     * @return array
     */
    private function formatActivity($activity, $category)
    {
        $changes = $activity->changes();
        
        // Format changes for better display
        $formattedChanges = [];
        if (isset($changes['attributes'])) {
            foreach ($changes['attributes'] as $key => $newValue) {
                $oldValue = $changes['old'][$key] ?? null;
                
                // Format field name for display
                $fieldName = $this->formatFieldName($key, $category);
                
                // Format values for display
                $formattedChanges[] = [
                    'field' => $fieldName,
                    'old' => $this->formatValue($key, $oldValue, $category),
                    'new' => $this->formatValue($key, $newValue, $category),
                ];
            }
        }
        
        return [
            'id' => $activity->id,
            'description' => $this->formatDescription($activity->description, $category),
            'event_type' => $activity->event,
            'category' => $category,
            'causer' => $activity->causer ? [
                'id' => $activity->causer->id,
                'name' => $activity->causer->name,
                'email' => $activity->causer->email,
            ] : null,
            'changes' => $formattedChanges,
            'created_at' => $activity->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $activity->created_at->diffForHumans(),
        ];
    }

    /**
     * Format field name for display
     *
     * @param  string  $field
     * @param  string  $category
     * @return string
     */
    private function formatFieldName($field, $category)
    {
        $fieldMaps = [
            'booking' => [
                'event_type_id' => 'Event Type',
                'band_id' => 'Band',
                'author_id' => 'Created By',
                'start_time' => 'Start Time',
                'end_time' => 'End Time',
                'venue_name' => 'Venue Name',
                'venue_address' => 'Venue Address',
                'contract_option' => 'Contract Option',
            ],
            'contact' => [
                'booking_id' => 'Booking',
                'contact_id' => 'Contact',
                'is_primary' => 'Primary Contact',
                'additional_info' => 'Additional Information',
            ],
            'contact_info' => [
                'band_id' => 'Band',
                'phone' => 'Phone Number',
            ],
            'payment' => [
                'band_id' => 'Band',
                'user_id' => 'Processed By',
                'invoices_id' => 'Invoice',
                'payable_type' => 'Payment For',
                'payable_id' => 'Payment For ID',
            ],
            'contract' => [
                'envelope_id' => 'PandaDoc Envelope ID',
                'author_id' => 'Created By',
                'asset_url' => 'Contract File',
                'contractable_type' => 'Contract Type',
                'contractable_id' => 'Contract For',
            ],
        ];
        
        $categoryMap = $fieldMaps[$category] ?? [];
        
        if (isset($categoryMap[$field])) {
            return $categoryMap[$field];
        }
        
        // Convert snake_case to Title Case
        return ucwords(str_replace('_', ' ', $field));
    }

    /**
     * Format value for display
     *
     * @param  string  $field
     * @param  mixed  $value
     * @param  string  $category
     * @return string
     */
    private function formatValue($field, $value, $category)
    {
        if (is_null($value)) {
            return '(empty)';
        }
        
        // Format dates
        if (($field === 'date' || str_ends_with($field, '_date')) && $value) {
            try {
                return Carbon::parse($value)->format('F j, Y');
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        // Format times
        if (($field === 'start_time' || $field === 'end_time' || $field === 'time') && $value) {
            try {
                return Carbon::parse($value)->format('g:i A');
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        // Format event type ID
        if ($field === 'event_type_id' && $value) {
            $eventType = EventTypes::find($value);
            return $eventType ? $eventType->name : "ID: {$value}";
        }

        // Format contact ID
        if ($field === 'contact_id' && $value) {
            $contact = Contacts::find($value);
            return $contact ? $contact->name : "ID: {$value}";
        }
        
        // Format amounts (assuming cents)
        if ($field === 'amount' || $field === 'price') {
            return '$' . number_format($value / 100, 2);
        }
        
        // Format status with capitalization
        if ($field === 'status' && is_string($value)) {
            return ucfirst($value);
        }

        // Format role with capitalization
        if ($field === 'role' && is_string($value)) {
            return ucwords(str_replace('_', ' ', $value));
        }
        
        // Format notes (strip HTML and preview)
        if ($field === 'notes' && is_string($value)) {
            return $this->formatNotesForDisplay($value);
        }
        
        // Format additional_info/additional_data (JSON)
        if (($field === 'additional_info' || $field === 'additional_data') && (is_array($value) || is_object($value))) {
            $data = is_object($value) ? (array) $value : $value;
            if (empty($data)) {
                return '(empty)';
            }
            // Format as key: value pairs
            $formatted = [];
            foreach ($data as $key => $val) {
                $formatted[] = ucwords(str_replace('_', ' ', $key)) . ': ' . $val;
            }
            return implode(' | ', $formatted);
        }
        
        // Convert boolean values
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        return (string) $value;
    }

    /**
     * Format notes field for display (strip HTML and create preview)
     *
     * @param  string  $html
     * @return string
     */
    private function formatNotesForDisplay($html)
    {
        // Strip all HTML tags
        $text = strip_tags($html);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove excessive whitespace and normalize line breaks
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        // If empty after processing
        if (empty($text)) {
            return '(empty)';
        }
        
        // Create preview if too long
        if (strlen($text) > 200) {
            return substr($text, 0, 200) . '...';
        }
        
        return $text;
    }

    /**
     * Format description based on category
     *
     * @param  string  $description
     * @param  string  $category
     * @return string
     */
    private function formatDescription($description, $category)
    {
        $categoryLabels = [
            'booking' => 'Booking',
            'contact' => 'Contact',
            'contact_info' => 'Contact Information',
            'payment' => 'Payment',
            'contract' => 'Contract',
        ];

        $label = $categoryLabels[$category] ?? 'Item';
        
        // Replace generic description with category-specific one
        return str_replace(
            ['Booking has been', 'Booking contact has been', 'Contact has been', 'Payment has been', 'Contract has been'],
            [$label . ' has been', 'Contact has been', 'Contact info has been', 'Payment has been', 'Contract has been'],
            $description
        );
    }
}
