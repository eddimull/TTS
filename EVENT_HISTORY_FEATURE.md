# Event History Timeline Feature

## Overview
A comprehensive activity history tracking system that provides a visual timeline of all changes made to events, including who made the changes, when they were made, and what data was modified.

## Features Implemented

### 1. Backend Controller Method
**File**: `app/Http/Controllers/EventsController.php`

Added `history()` method that:
- Retrieves all activity logs for a specific event
- Loads causer (user) information for each activity
- Formats field names for human-readable display
- Formats values based on field type (dates, times, event types, JSON)
- Returns formatted data via Inertia.js

**Key Features**:
- Smart field name formatting (converts `event_type_id` → "Event Type")
- Date formatting (2024-10-28 → "October 28, 2024")
- Time formatting (14:30:00 → "2:30 PM")
- Event type ID resolution (shows type name instead of ID)
- JSON data handling (shows "Modified" instead of raw JSON)
- Handles null values gracefully

### 2. Route Configuration
**File**: `routes/events.php`

Added route:
```php
Route::get('/{key}/history', [EventsController::class, 'history'])->name('events.history');
```

**Route Details**:
- **Name**: `events.history`
- **Method**: GET
- **Path**: `/events/{key}/history`
- **Parameter**: Event key (UUID)
- **Middleware**: auth, verified

### 3. Reusable Timeline Component
**File**: `resources/js/Components/ActivityTimeline.vue`

A beautiful, reusable Vue component that displays activity logs in a vertical timeline format.

**Features**:
- Visual timeline with connecting lines
- Color-coded icons for different activity types (create, update, delete)
- User information display (name or "System")
- Relative timestamps (e.g., "2 hours ago") with absolute time on hover
- Event type badges with semantic colors
- Detailed change display showing old vs. new values side-by-side
- Responsive design for mobile and desktop
- Dark mode support
- Empty state handling

**Visual Design**:
- Green icons for "created" activities
- Blue icons for "updated" activities  
- Red icons for "deleted" activities
- Gray for unknown activity types
- Old values shown in red boxes
- New values shown in green boxes

### 4. History Page
**File**: `resources/js/Pages/Events/History.vue`

Full-page view for displaying event history with rich context.

**Sections**:
1. **Header Section**:
   - Event title
   - Date and time
   - Band name
   - Event type
   - Back button to events list
   - "View Event" button to see event details

2. **Statistics Dashboard**:
   - Total activities count
   - Created activities count
   - Updated activities count
   - Visual icons and color coding

3. **Activity Timeline**:
   - Chronological display of all activities
   - Uses the reusable ActivityTimeline component
   - Latest activities appear first

**Navigation**:
- Breadcrumb navigation back to events
- Direct link to view event details
- Responsive layout

### 5. UI Integration Points

#### A. Event Details Page
**File**: `resources/js/Pages/Bookings/Components/EventDetails.vue`

Added "History" button in the event header:
- Icon: Clock/history icon
- Position: Next to "Edit Event" button
- Style: Secondary outlined button
- Action: Navigates to history page

#### B. Events List
**File**: `resources/js/Pages/Events/EventList.vue`

Added Actions column with history button:
- Icon: Clock/history icon
- Position: New "Actions" column in the data table
- Style: Text button with icon only
- Action: Opens history page (prevents row selection)
- Tooltip: "View History"

### 6. Route Generation
Updated Ziggy routes to make the new `events.history` route available in Vue components via the `route()` helper.

## Usage

### Viewing Event History

**From Event Details**:
1. Navigate to any event's detail page
2. Click the "History" button in the top-right corner
3. View the complete activity timeline

**From Events List**:
1. Go to the Events page
2. Find the event in the data table
3. Click the clock icon in the Actions column
4. View the complete activity timeline

**Direct Access**:
```javascript
route('events.history', eventKey)
```

### What Gets Tracked

The following event attributes are automatically tracked:
- Title
- Date
- Time
- Notes
- Event Type
- Event Source (eventable_type/eventable_id)
- Additional Data (JSON)

### Activity Types

- **Created**: When an event is first created
- **Updated**: When any tracked field is modified
- **Deleted**: When an event is removed (soft delete)

### Change Display

For each update, the timeline shows:
- **Field Name**: Human-readable field name
- **Previous Value**: What the field was before
- **Current Value**: What the field is now
- **User**: Who made the change
- **Timestamp**: When it happened (relative and absolute)

## Technical Details

### Database
Uses the existing `activity_log` table from `spatie/laravel-activitylog` package.

**Relevant Columns**:
- `log_name`: "events"
- `description`: Auto-generated description
- `subject_type`: "App\Models\Events"
- `subject_id`: Event ID
- `causer_type`: "App\Models\User"
- `causer_id`: User ID
- `properties`: JSON with old/new values
- `event`: Activity type (created, updated, deleted)

### Frontend Dependencies
- Vue 3
- Inertia.js
- PrimeVue (for Button component)
- Luxon (for date/time formatting)
- Tailwind CSS (for styling)

### Performance Considerations
- Activities are loaded with causer relationship (single query with eager loading)
- Latest activities are shown first (descending order)
- Pagination could be added for events with extensive history
- Activity logs are automatically cleaned up after 90 days (configurable)

## Future Enhancements

Potential improvements:
1. **Filtering**: Filter by activity type, user, or date range
2. **Search**: Search within change descriptions
3. **Export**: Export history to PDF or CSV
4. **Comparison**: Side-by-side view of two specific versions
5. **Restore**: Ability to revert to a previous version
6. **Pagination**: For events with very long histories
7. **Real-time Updates**: WebSocket support for live activity updates
8. **Batch Operations**: Track multiple changes in a single transaction
9. **Custom Events**: Manual logging of important milestones
10. **Email Notifications**: Alert users when changes are made

## Testing

To test the feature:

```bash
# In Docker container
docker exec tts-app-1 bash

# Create a test event
php artisan tinker
>>> $event = Events::first();
>>> $event->title = "Updated Title";
>>> $event->save();

# Visit the history page
# Navigate to: /events/{event-key}/history
```

## Code Quality

- ✅ Follows Laravel best practices
- ✅ Uses Inertia.js SSR pattern
- ✅ Responsive design
- ✅ Dark mode compatible
- ✅ Accessible (semantic HTML, ARIA where needed)
- ✅ Type-safe (where applicable)
- ✅ Error handling for invalid dates/times
- ✅ Graceful fallbacks for missing data

## Related Documentation

- [ACTIVITY_LOG_USAGE.md](./ACTIVITY_LOG_USAGE.md) - Complete activity log documentation
- [Spatie Activity Log](https://spatie.be/docs/laravel-activitylog) - Package documentation

## Screenshots & UI Flow

### History Page Layout
```
┌─────────────────────────────────────────────────────────┐
│ ← Event History                              [View Event]│
│ 📅 Band Rehearsal                                        │
│ 🕐 October 28, 2024 at 7:00 PM                          │
│ 👥 The Band Name  •  🏷️ Rehearsal                       │
├─────────────────────────────────────────────────────────┤
│ [📊 Total: 5]  [➕ Created: 1]  [✏️ Updated: 4]        │
├─────────────────────────────────────────────────────────┤
│ 🔵 Event has been updated                    [UPDATED]  │
│    👤 John Doe  •  🕐 2 hours ago                       │
│    ┌───────────────────────────────────────────────┐   │
│    │ Title                                          │   │
│    │ Previous: [Old Title      ] → Current: [New…] │   │
│    └───────────────────────────────────────────────┘   │
│                                                          │
│ 🔵 Event has been updated                    [UPDATED]  │
│    👤 Jane Smith  •  🕐 1 day ago                       │
│    ...                                                   │
│                                                          │
│ 🟢 Event has been created                    [CREATED]  │
│    👤 John Doe  •  🕐 3 days ago                        │
└─────────────────────────────────────────────────────────┘
```

## Implementation Checklist

- [x] Add history method to EventsController
- [x] Add route for events.history
- [x] Create ActivityTimeline.vue component
- [x] Create Events/History.vue page
- [x] Add history button to EventDetails.vue
- [x] Add history action to EventList.vue
- [x] Generate Ziggy routes
- [x] Update ACTIVITY_LOG_USAGE.md documentation
- [x] Create feature documentation (this file)

## Maintenance Notes

### When Adding New Tracked Fields

1. Update `Events` model's `getActivitylogOptions()` to include the field
2. Add field name mapping in `EventsController::formatFieldName()`
3. Add custom value formatting in `EventsController::formatValue()` if needed

### When Modifying Timeline UI

The ActivityTimeline component is reusable across the application. Changes here will affect:
- Events history
- Any future history implementations (bookings, rehearsals, etc.)

Consider backward compatibility when modifying the component API.
