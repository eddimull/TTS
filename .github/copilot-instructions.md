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

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.30
- inertiajs/inertia-laravel (INERTIA) - v1
- laravel/framework (LARAVEL) - v12
- laravel/horizon (HORIZON) - v5
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/scout (SCOUT) - v10
- tightenco/ziggy (ZIGGY) - v2
- laravel/breeze (BREEZE) - v2
- laravel/dusk (DUSK) - v8
- laravel/mcp (MCP) - v0
- laravel/sail (SAIL) - v1
- laravel/telescope (TELESCOPE) - v5
- phpunit/phpunit (PHPUNIT) - v11
- @inertiajs/vue3 (INERTIA) - v2
- laravel-echo (ECHO) - v2
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure - don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.


=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double check the available parameters.

## URLs
- Whenever you share a project URL with the user you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain / IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation specific for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The 'search-docs' tool is perfect for all Laravel related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel-ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries - package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit"
3. Quoted Phrases (Exact Position) - query="infinite scroll" - Words must be adjacent and in that order
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit"
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms


=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over comments. Never use comments within the code itself unless there is something _very_ complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.


=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test` with a specific filename or filter.


=== inertia-laravel/core rules ===

## Inertia Core

- Inertia.js components should be placed in the `resources/js/Pages` directory unless specified differently in the JS bundler (vite.config.js).
- Use `Inertia::render()` for server-side routing instead of traditional Blade views.
- Use `search-docs` for accurate guidance on all things Inertia.

<code-snippet lang="php" name="Inertia::render Example">
// routes/web.php example
Route::get('/users', function () {
    return Inertia::render('Users/Index', [
        'users' => User::all()
    ]);
});
</code-snippet>


=== inertia-laravel/v1 rules ===

## Inertia v1

- Inertia v1 does _not_ come with these features. Do not recommend using these Inertia v2 features directly.
    - Polling
    - Prefetching
    - Deferred props
    - Infinite scrolling using merging props and `WhenVisible`
    - Lazy loading data on scroll


=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.


=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version specific documentation.
- This project upgraded from Laravel 10 without migrating to the new streamlined Laravel file structure.
- This is **perfectly fine** and recommended by Laravel. Follow the existing structure from Laravel 10. We do not to need migrate to the new Laravel structure unless the user explicitly requests that.

### Laravel 10 Structure
- Middleware typically lives in `app/Http/Middleware/` and service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in a Laravel 10 structure:
    - Middleware registration happens in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule register in `app/Console/Kernel.php`
    - Rate limits likely exist in `RouteServiceProvider` or `app/Http/Kernel.php`

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.


=== phpunit/core rules ===

## PHPUnit Core

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files, these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test`.
- To run all tests in a file: `php artisan test tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --filter=testName` (recommended after making a change to a related file).


=== inertia-vue/core rules ===

## Inertia + Vue

- Vue components must have a single root element.
- Use `router.visit()` or `<Link>` for navigation instead of traditional links.

<code-snippet name="Inertia Client Navigation" lang="vue">

    import { Link } from '@inertiajs/vue3'
    <Link href="/">Home</Link>

</code-snippet>


=== inertia-vue/v2/forms rules ===

## Inertia + Vue Forms

<code-snippet name="`<Form>` Component Example" lang="vue">

<Form
    action="/users"
    method="post"
    #default="{
        errors,
        hasErrors,
        processing,
        progress,
        wasSuccessful,
        recentlySuccessful,
        setError,
        clearErrors,
        resetAndClearErrors,
        defaults,
        isDirty,
        reset,
        submit,
  }"
>
    <input type="text" name="name" />

    <div v-if="errors.name">
        {{ errors.name }}
    </div>

    <button type="submit" :disabled="processing">
        {{ processing ? 'Creating...' : 'Create User' }}
    </button>

    <div v-if="wasSuccessful">User created successfully!</div>
</Form>

</code-snippet>


=== tailwindcss/core rules ===

## Tailwind Core

- Use Tailwind CSS classes to style HTML, check and use existing tailwind conventions within the project before writing your own.
- Offer to extract repeated patterns into components that match the project's conventions (i.e. Blade, JSX, Vue, etc..)
- Think through class placement, order, priority, and defaults - remove redundant classes, add classes to parent or child carefully to limit repetition, group elements logically
- You can use the `search-docs` tool to get exact examples from the official documentation when needed.

### Spacing
- When listing items, use gap utilities for spacing, don't use margins.

    <code-snippet name="Valid Flex Gap Spacing Example" lang="html">
        <div class="flex gap-8">
            <div>Superior</div>
            <div>Michigan</div>
            <div>Erie</div>
        </div>
    </code-snippet>


### Dark Mode
- If existing pages and components support dark mode, new pages and components must support dark mode in a similar way, typically using `dark:`.


=== tailwindcss/v3 rules ===

## Tailwind 3

- Always use Tailwind CSS v3 - verify you're using only classes supported by this version.
</laravel-boost-guidelines>
