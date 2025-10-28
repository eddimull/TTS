# Activity Log Implementation for Events & Rehearsals

## Overview
Activity logging has been implemented for `Events`, `Rehearsal`, `Bookings`, and `Payments` models using the `spatie/laravel-activitylog` package. This allows automatic tracking of all changes made to these models.

## What Gets Logged

### Events Model
The following attributes are tracked:
- `title`
- `date`
- `time`
- `notes`
- `event_type_id`
- `eventable_type`
- `eventable_id`
- `additional_data`

### Rehearsal Model
The following attributes are tracked:
- `rehearsal_schedule_id`
- `band_id`
- `venue_name`
- `venue_address`
- `notes`
- `additional_data`
- `is_cancelled`

### Bookings Model
The following attributes are tracked:
- `band_id`
- `name`
- `event_type_id`
- `date`
- `start_time`
- `end_time`
- `venue_name`
- `venue_address`
- `price`
- `status`
- `contract_option`
- `author_id`
- `notes`

### Payments Model
The following attributes are tracked:
- `name`
- `amount`
- `date`
- `band_id`
- `user_id`
- `status`
- `invoices_id`
- `payable_type`
- `payable_id`

## Automatic Logging
Changes are automatically logged when you:
- **Create** a new event or rehearsal
- **Update** any tracked attributes
- **Delete** an event or rehearsal

Only dirty (changed) attributes are logged to keep the logs clean and relevant.

## Retrieving Activity History

### Get all activity for a specific event:
```php
$event = Events::find($id);
$activities = $event->activities;

// Display the changes
foreach ($activities as $activity) {
    echo "{$activity->description} at {$activity->created_at}";
    echo "Changes: " . json_encode($activity->changes());
}
```

### Get all activity for a specific rehearsal:
```php
$rehearsal = Rehearsal::find($id);
$activities = $rehearsal->activities;

// Display the changes
foreach ($activities as $activity) {
    echo "{$activity->description} at {$activity->created_at}";
    echo "Changes: " . json_encode($activity->changes());
}
```

### Get all activity for a specific booking:
```php
$booking = Bookings::find($id);
$activities = $booking->activities;

// Display the changes
foreach ($activities as $activity) {
    echo "{$activity->description} at {$activity->created_at}";
    echo "Changes: " . json_encode($activity->changes());
}
```

### Get all activity for a specific payment:
```php
$payment = Payments::find($id);
$activities = $payment->activities;

// Display the changes
foreach ($activities as $activity) {
    echo "{$activity->description} at {$activity->created_at}";
    echo "Changes: " . json_encode($activity->changes());
}
```

### Get recent activity across all events:
```php
use Spatie\Activitylog\Models\Activity;

$recentEventActivity = Activity::forSubject(Events::class)
    ->latest()
    ->take(10)
    ->get();
```

### Get recent activity across all rehearsals:
```php
use Spatie\Activitylog\Models\Activity;

$recentRehearsalActivity = Activity::forSubject(Rehearsal::class)
    ->latest()
    ->take(10)
    ->get();
```

### Get recent activity across all bookings:
```php
use Spatie\Activitylog\Models\Activity;

$recentBookingActivity = Activity::forSubject(Bookings::class)
    ->latest()
    ->take(10)
    ->get();
```

### Get recent activity across all payments:
```php
use Spatie\Activitylog\Models\Activity;

$recentPaymentActivity = Activity::forSubject(Payments::class)
    ->latest()
    ->take(10)
    ->get();
```

### Filter by log name:
```php
// Get only event logs
$eventLogs = Activity::inLog('events')
    ->latest()
    ->get();

// Get only rehearsal logs
$rehearsalLogs = Activity::inLog('rehearsals')
    ->latest()
    ->get();

// Get only booking logs
$bookingLogs = Activity::inLog('bookings')
    ->latest()
    ->get();

// Get only payment logs
$paymentLogs = Activity::inLog('payments')
    ->latest()
    ->get();
```

### Get activity by a specific user (causer):
```php
$userActivity = Activity::causedBy($user)
    ->latest()
    ->get();
```

## Activity Properties

Each activity log entry contains:
- `description` - Description of what happened (e.g., "Event has been created")
- `subject_type` - The model type (App\Models\Events or App\Models\Rehearsal)
- `subject_id` - The ID of the model
- `causer_type` - Who made the change (usually App\Models\User)
- `causer_id` - The ID of the user who made the change
- `properties` - JSON containing old and new values
- `created_at` - When the change occurred

## Displaying in Controllers

Example controller method to get event history:
```php
public function history(Events $event)
{
    $activities = $event->activities()
        ->with('causer')
        ->latest()
        ->get()
        ->map(function ($activity) {
            return [
                'description' => $activity->description,
                'causer' => $activity->causer ? $activity->causer->name : 'System',
                'changes' => $activity->changes(),
                'created_at' => $activity->created_at->diffForHumans(),
            ];
        });

    return Inertia::render('Events/History', [
        'event' => $event,
        'activities' => $activities,
    ]);
}
```

**✅ IMPLEMENTED:** The `EventsController::history()` method has been fully implemented with:
- Proper activity retrieval with causer relationships
- Field name formatting for better display
- Value formatting for dates, times, event types, and JSON data
- Complete integration with Inertia.js for frontend rendering
- Available at route: `events.history` (GET `/events/{key}/history`)

### Viewing Event History in the Application

Users can now view the complete activity history of any event through multiple access points:

1. **From Event Details Page**: Click the "History" button in the event details header
2. **From Events List**: Click the history icon (clock) in the actions column
3. **Direct URL**: Visit `/events/{event-key}/history`

The history page displays:
- Event metadata (title, date, time, band, event type)
- Activity statistics (total activities, created count, updates count)
- Interactive timeline with:
  - User who performed each action
  - Timestamp (both absolute and relative)
  - Detailed change information showing old vs. new values
  - Visual indicators for different activity types (created, updated, deleted)

Example controller method to get booking history:
```php
public function bookingHistory(Bookings $booking)
{
    $activities = $booking->activities()
        ->with('causer')
        ->latest()
        ->get()
        ->map(function ($activity) {
            return [
                'description' => $activity->description,
                'causer' => $activity->causer ? $activity->causer->name : 'System',
                'changes' => $activity->changes(),
                'created_at' => $activity->created_at->diffForHumans(),
            ];
        });

    return Inertia::render('Bookings/History', [
        'booking' => $booking,
        'activities' => $activities,
    ]);
}
```

Example to get payment history for a booking:
```php
public function paymentHistory(Bookings $booking)
{
    // Get all payments for this booking
    $payments = $booking->payments;
    
    // Get activities for all payments
    $activities = Activity::forSubject(Payments::class)
        ->whereIn('subject_id', $payments->pluck('id'))
        ->with('causer')
        ->latest()
        ->get()
        ->map(function ($activity) {
            return [
                'description' => $activity->description,
                'causer' => $activity->causer ? $activity->causer->name : 'System',
                'changes' => $activity->changes(),
                'created_at' => $activity->created_at->diffForHumans(),
            ];
        });

    return Inertia::render('Bookings/PaymentHistory', [
        'booking' => $booking,
        'activities' => $activities,
    ]);
}
```

## Vue Component Example

```vue
<template>
  <div class="activity-log">
    <h2>Activity History</h2>
    <div v-for="activity in activities" :key="activity.id" class="activity-item">
      <div class="activity-header">
        <strong>{{ activity.description }}</strong>
        <span class="activity-time">{{ activity.created_at }}</span>
      </div>
      <div class="activity-causer">
        By: {{ activity.causer }}
      </div>
      <div v-if="activity.changes.attributes" class="activity-changes">
        <div v-for="(value, key) in activity.changes.attributes" :key="key">
          <strong>{{ key }}:</strong> 
          <span class="old-value">{{ activity.changes.old[key] }}</span>
          → 
          <span class="new-value">{{ value }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  activities: Array,
});
</script>
```

## Customization

### Adding Causer (User) Information
The logged-in user is automatically tracked. You can also manually set the causer:
```php
activity()
    ->performedOn($event)
    ->causedBy($user)
    ->log('Custom activity description');
```

### Custom Log Entries
You can manually log custom activities:
```php
activity()
    ->performedOn($event)
    ->withProperties(['custom_field' => 'custom_value'])
    ->log('Custom action performed');
```

## Database Table
All activity is stored in the `activity_log` table with the following structure:
- `id`
- `log_name`
- `description`
- `subject_type`
- `subject_id`
- `causer_type`
- `causer_id`
- `properties` (JSON)
- `event` (for special event types)
- `batch_uuid` (for grouping related activities)
- `created_at`
- `updated_at`

## Configuration
The activity log configuration can be found at `config/activitylog.php`.

Key settings:
- `enabled` - Enable/disable logging globally
- `delete_records_older_than_days` - Automatic cleanup (90 days by default)
- `subject_returns_soft_deleted_models` - Include soft-deleted models
- `activity_model` - The activity model class

## Performance Considerations
- Only dirty attributes are logged
- Empty logs are not submitted
- Consider implementing a cleanup job for old logs
- Index the `activity_log` table on `subject_type`, `subject_id`, and `created_at` for better query performance

## Further Reading
Full documentation: https://spatie.be/docs/laravel-activitylog/
