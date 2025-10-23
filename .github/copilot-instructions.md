# TTS Bandmate - AI Coding Agent Instructions

## Project Overview
TTS Bandmate is a **Laravel 12 + Vue 3 + Inertia.js** booking application that eliminates the need for agents by automating proposal generation, contract signing via PandaDoc, payment collection through Stripe, calendar integration, and band management workflows.

## Architecture & Key Patterns
- **Microservices Architecture**: The application is structured as a set of loosely coupled services, each responsible for a specific domain (e.g., booking, billing, calendar).
- **Event-Driven Architecture**: Services communicate via events, allowing for asynchronous processing and decoupling of components.

### Backend (Laravel)
- **Polymorphic Events System**: The `events` table uses polymorphic relationships (`eventable_type`/`eventable_id`) to link to either `bookings` or legacy `band_events`. Always check both when querying events.
- **Multi-Band Tenancy**: Users can own/belong to multiple bands via `band_owners`/`band_members` pivot tables. Most operations are scoped by `band_id`.
- **Permission System**: Fine-grained permissions in `user_permissions` table control read/write access to events, proposals, invoices, charts, bookings, and colors per band.
- **Proposal Lifecycle**: Draft → Finalized → Sent → Approved → Contract Signed → Event Created (via `proposals` → `events` flow)
- **Google Calendar Integration**: Events sync to Google Calendar via `google_events` polymorphic relationship and `band_calendars` (booking, event, public types)

### Frontend (Vue 3 + Inertia)
- **Inertia SSR**: Uses `@inertiajs/vue3` for seamless Laravel-Vue integration. Pages in `resources/js/Pages/`, components in `resources/js/Components/`
- **Ziggy Routes**: Laravel routes available in Vue via `route()` helper from `ziggy-js`
- **PrimeVue Components**: Heavy use of PrimeVue for UI components (DataTable, Calendar, Dialog, etc.) with Aura theme
- **Vuex Store Modules**: Separate modules for `questionnaire`, `user`, `eventTypes` in `resources/js/Store/`
- **Collapsible Component Architecture**: Complex forms use collapsible sections (see `EventEditor.vue` with `SectionCard` pattern)
- **Layout System**: Specialized layouts for different sections (`BookingLayout`, `FinanceLayout`) with contextual navigation

### Data Model Essentials
```sql
-- Core entities and their relationships
bands (1:many) -> bookings -> events (polymorphic)
bands (1:many) -> band_events (legacy) -> events (polymorphic)  
bands (1:many) -> proposals -> events (when approved)
bands (1:many) -> colorways (attire options)
bands (many:many) -> users (via band_owners/band_members)
events (1:many) -> google_events (calendar sync)
```

## Development Workflow

### Docker Development Environment
```bash
# Start containers
docker-compose up -d  # or podman-compose --in-pod 1 up -d

# In app container:
composer install && npm ci
php artisan key:generate
php artisan migrate && php artisan db:seed
php artisan db:seed --class=DevSetupSeeder  # Testing data + admin@example.com:password

# Frontend development
npm run dev  # Vite HMR on https://localhost:8710

# Background jobs
php artisan schedule:work  # Required for PandaDoc webhooks, calendar sync

# Routes
php artisan ziggy:generate  # After route changes
```

### Critical Files to Understand
- `app/Models/Events.php` - Central event model with polymorphic relationships
- `app/Http/Controllers/EventsController.php` - Event CRUD with calendar integration
- `app/Services/FinanceServices.php` - Financial calculations, payment tracking, revenue analysis
- `resources/js/Pages/Bookings/Components/EventEditor.vue` - Complex form architecture
- `resources/js/app.js` - Vue app setup with global components and mixins
- `resources/js/Layouts/Authenticated.vue` - Main layout with navigation, notifications, search
- `resources/js/Pages/Dashboard.vue` - Event-centric dashboard with calendar sidebar

### Testing & Quality
```bash
# PHP tests
php artisan test

# Frontend tests  
npm run test        # Vitest with jsdom
npm run test:pipeline  # CI mode

# Build
npm run build
```

## Key Integrations & External Dependencies
- **Google Calendar API**: For syncing events with Google Calendar
- **PandaDoc API**: For contract generation and signing workflows
- **Stripe API**: For payment processing and invoice management
- **AWS S3 / MinIO**: For file storage of charts, contracts, images

### Finance & Revenue Management
- **FinanceServices**: Centralized service for payment tracking, revenue analysis, and financial reporting
- **Payment Processing**: Tracks both manual payments and Stripe-processed invoices via polymorphic `payments` table
- **Revenue Analytics**: Chart.js visualizations for paid vs unpaid amounts, booking forecasts, yearly revenue
- **Multi-Band Financial Isolation**: All financial data properly scoped by `band_id` for tenant separation

### Chart Library Management
- **File Storage Integration**: Charts (sheet music) stored in S3/MinIO with multiple upload types per chart
- **Chart Organization**: Searchable library with composer, title, description, and public/private visibility
- **Band-Scoped Access**: Charts filtered by user's band permissions via `user_permissions.read_charts`

### Dashboard & Performance Organization
- **Event-Centric Dashboard**: Main view shows upcoming events as cards with integrated calendar sidebar
- **Smart Navigation**: Hash-based scrolling to specific events with header offset calculation
- **Contextual Layouts**: `BookingLayout` and `FinanceLayout` provide specialized navigation for different workflows

### PandaDoc Contract Flow
1. Proposal finalized → Generate PDF contract → Send to PandaDoc
2. Client signs → Webhook updates `proposal_contracts.status`
3. All parties signed → Create event from proposal

### Google Calendar Sync
- Service account credentials in `storage/app/google-calendar/`
- Multiple calendar types per band: booking, event, public
- Events sync bidirectionally with custom `CalendarEventFormatter`

### Stripe Payment Processing
- Connect accounts for bands in `stripe_accounts`
- Invoice generation with convenience fees
- Webhook handling for payment status updates

### File Storage (S3/MinIO)
- Charts, contracts, images stored in configurable bucket
- Local MinIO for development (admin:minioadmin @ localhost:9001)

## Component Patterns & Conventions
- Reusable components should be placed in `resources/js/Components/`
- Page-specific components go in `resources/js/Pages/[PageName]/Components/`

### Vue Component Structure
```vue
<!-- Collapsible sections for complex forms -->
<SectionCard title="Notes" :is-open="openSections.notes" @toggle="toggleSection('notes')">
  <NotesSection v-model="event" />
</SectionCard>

<!-- PrimeVue integration -->
<DataTable :value="items" paginator :rows="10">
  <Column field="name" header="Name" />
</DataTable>

<!-- Layout-specific navigation -->
<template>
  <BreezeAuthenticatedLayout>
    <container>
      <FinanceMenu :routes="filteredRoutes" />
      <slot />
    </container>
  </BreezeAuthenticatedLayout>
</template>
```

### Laravel Controller Patterns
```php
// Multi-step data preparation
private function prepareEventData(EventRequest $request): array
{
    // Process time fields, generate UUIDs, etc.
}

// Service integration
private function writeEventToCalendar(Bands $band, BandEvents $event): void
{
    $calService = new CalendarService($band);
    $calService->writeEventToCalendar($event);
}

// Finance service usage
$financeService = new FinanceServices();
$bands = $financeService->getBandFinances($userBands);
```

Keep the controllers thin by offloading business logic to services. Utilize form requests for validation.

### Permission Checking
```php
// In controllers - check band permissions
$userCan = Auth::user()->canWrite('events', $band->id);

// In views - conditionally show features
v-if="navigation && navigation.Events"
```

## Troubleshooting & Common Issues

### Calendar Integration
- Ensure service account JSON exists in `storage/app/google-calendar/`
- Check `band_calendars` table for proper calendar_id mapping
- Verify Google Calendar API is enabled and scoped correctly

### Frontend Asset Issues
```bash
# Clear Vite cache
rm -rf node_modules/.vite
npm run dev

# Regenerate routes after Laravel route changes
php artisan ziggy:generate
```

### Database Polymorphic Relations
When working with events, always handle both event types:
```php
// Query both booking events and legacy band events
$events = Auth::user()->getEventsAttribute($afterDate);

// Check polymorphic type
if ($event->eventable_type === 'App\\Models\\Bookings') {
    // Handle booking event
} else {
    // Handle legacy band event  
}
```

### SSL/HTTPS in Development
- Uses self-signed certificates in `ssl/` directory
- Generate with `mkcert` for trusted local HTTPS
- Required for proper PandaDoc/Stripe webhook testing

---

*This codebase prioritizes band workflow automation over generic event management. When adding features, consider multi-band tenancy, permission scoping, and integration touchpoints (calendar, payments, contracts).*