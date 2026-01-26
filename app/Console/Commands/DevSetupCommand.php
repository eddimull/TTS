<?php

namespace App\Console\Commands;

use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\BandMembers;
use App\Models\BandRole;
use App\Models\Bookings;
use App\Models\BookingContacts;
use App\Models\Contacts;
use App\Models\Payments;
use App\Models\RehearsalSchedule;
use App\Models\Roster;
use App\Models\StripeAccounts;
use App\Models\User;
use App\Models\BandPayoutConfig;
use App\Enums\PaymentType;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DevSetupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dev:setup
                            {--user : Create test user}
                            {--band : Create test band}
                            {--members : Create band members}
                            {--roles : Create band roles}
                            {--rosters : Create event rosters}
                            {--bookings : Create test bookings}
                            {--events : Create events for bookings}
                            {--payments : Create partial payments with types}
                            {--contacts : Create realistic contacts}
                            {--stripe : Create Stripe test accounts}
                            {--rehearsals : Create rehearsal schedules}
                            {--payout-config : Create payout configurations}
                            {--all : Create all test data}
                            {--force : Force creation even if data exists}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup development data for testing (modular setup with options)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 TTS Bandmate Development Setup');
        $this->newLine();

        // Check if running all
        $all = $this->option('all');
        $force = $this->option('force');

        // If no options specified, show menu
        if (!$all && !$this->hasAnyOption()) {
            return $this->interactiveSetup();
        }

        // Seed event types first if needed
        if (\App\Models\EventTypes::count() === 0) {
            $this->info('📅 Seeding event types...');
            $this->call(EventTypeSeeder::class);
        }

        // Setup components based on options
        if ($all || $this->option('user')) {
            $this->setupUser($force);
        }

        if ($all || $this->option('band')) {
            $this->setupBand($force);
        }

        if ($all || $this->option('members')) {
            $this->setupMembers($force);
        }

        if ($all || $this->option('roles')) {
            $this->setupRoles($force);
        }

        if ($all || $this->option('rosters')) {
            $this->setupRosters($force);
        }

        if ($all || $this->option('stripe')) {
            $this->setupStripeAccounts($force);
        }

        if ($all || $this->option('bookings')) {
            $this->setupBookings($force);
        }

        if ($all || $this->option('events')) {
            $this->setupEvents($force);
        }

        if ($all || $this->option('payments')) {
            $this->setupPayments($force);
        }

        if ($all || $this->option('contacts')) {
            $this->setupContacts($force);
        }

        if ($all || $this->option('rehearsals')) {
            $this->setupRehearsals($force);
        }

        if ($all || $this->option('payout-config')) {
            $this->setupPayoutConfig($force);
        }

        $this->newLine();
        $this->info('✅ Development setup complete!');
        
        return Command::SUCCESS;
    }

    /**
     * Check if any setup option is specified
     */
    private function hasAnyOption(): bool
    {
        return $this->option('user') ||
               $this->option('band') ||
               $this->option('members') ||
               $this->option('roles') ||
               $this->option('rosters') ||
               $this->option('bookings') ||
               $this->option('events') ||
               $this->option('payments') ||
               $this->option('stripe') ||
               $this->option('contacts') ||
               $this->option('rehearsals') ||
               $this->option('payout-config');
    }

    /**
     * Interactive setup menu with checkbox selection
     */
    private function interactiveSetup()
    {
        $this->info('╔═══════════════════════════════════════════════════════╗');
        $this->info('║       TTS Bandmate - Development Setup Tool          ║');
        $this->info('╚═══════════════════════════════════════════════════════╝');
        $this->newLine();

        // Check current state
        $userExists = User::where('email', 'admin@example.com')->exists();
        $bandExists = Bands::where('site_name', 'test_band')->exists();
        $band = Bands::where('site_name', 'test_band')->first();
        $bookingsExist = $band ? Bookings::where('band_id', $band->id)->exists() : false;
        $stripeExists = $band ? StripeAccounts::where('band_id', $band->id)->exists() : false;
        $rehearsalsExist = $band ? RehearsalSchedule::where('band_id', $band->id)->exists() : false;

        // Show current state
        $this->info('Current Status:');
        $this->line('  ' . ($userExists ? '✓' : '✗') . ' Test User (admin@example.com)');
        $this->line('  ' . ($bandExists ? '✓' : '✗') . ' Test Band');
        $this->line('  ' . ($stripeExists ? '✓' : '✗') . ' Stripe Test Accounts');
        $this->line('  ' . ($bookingsExist ? '✓' : '✗') . ' Test Bookings');
        $this->line('  ' . ($rehearsalsExist ? '✓' : '✗') . ' Rehearsal Schedules');
        $this->newLine();

        // Get selections
        $selected = $this->getSelections();

        if (empty($selected)) {
            $this->warn('No items selected. Exiting.');
            return Command::SUCCESS;
        }

        $this->newLine();
        $force = $this->confirm('Force creation (overwrite existing data)?', false);

        $this->newLine();
        $this->info('Creating selected components...');
        $this->newLine();

        // Execute selected setups
        $options = ['--force' => $force];
        foreach ($selected as $item) {
            $options["--{$item}"] = true;
        }
        $this->call('dev:setup', $options);

        return Command::SUCCESS;
    }

    /**
     * Get user selections using a checkbox-style interface
     */
    private function getSelections(): array
    {
        $options = [
            'user' => [
                'label' => 'Test User',
                'description' => 'admin@example.com / password',
                'selected' => false,
            ],
            'band' => [
                'label' => 'Test Band',
                'description' => 'Test Band with site name: test_band',
                'selected' => false,
            ],
            'members' => [
                'label' => 'Band Members',
                'description' => '5 additional band members',
                'selected' => false,
            ],
            'roles' => [
                'label' => 'Band Roles',
                'description' => '8 instrument roles (Vocals, Guitar, Bass, etc.)',
                'selected' => false,
            ],
            'rosters' => [
                'label' => 'Event Rosters',
                'description' => 'Default roster with role assignments',
                'selected' => false,
            ],
            'stripe' => [
                'label' => 'Stripe Test Accounts',
                'description' => 'Enables contact portal checkout (test mode)',
                'selected' => false,
            ],
            'bookings' => [
                'label' => 'Test Bookings',
                'description' => '15 bookings spread over past year',
                'selected' => false,
            ],
            'events' => [
                'label' => 'Events for Bookings',
                'description' => 'Creates events linked to bookings',
                'selected' => false,
            ],
            'payments' => [
                'label' => 'Partial Payments',
                'description' => 'Deposit + balance with varied payment types',
                'selected' => false,
            ],
            'contacts' => [
                'label' => 'Realistic Contacts',
                'description' => 'Contacts for confirmed/pending bookings',
                'selected' => false,
            ],
            'rehearsals' => [
                'label' => 'Rehearsal Schedules',
                'description' => 'Weekly practice sessions',
                'selected' => false,
            ],
            'payout-config' => [
                'label' => 'Payout Configuration',
                'description' => 'Roster-based payout config',
                'selected' => false,
            ],
        ];

        $this->info('Select components to create (use number keys, <Enter> to confirm):');
        $this->newLine();

        while (true) {
            // Display options
            $index = 1;
            foreach ($options as $key => $option) {
                $checkbox = $option['selected'] ? '[✓]' : '[ ]';
                $this->line("  {$index}. {$checkbox} {$option['label']}");
                $this->line("      └─ {$option['description']}");
                $index++;
            }
            $this->newLine();

            // Show selection options
            $this->line('Options:');
            $this->line('  • Enter number to toggle selection (1-' . count($options) . ')');
            $this->line('  • Type "all" to select all');
            $this->line('  • Type "none" to clear all');
            $this->line('  • Press Enter to confirm and continue');
            $this->newLine();

            $input = $this->ask('Your choice');

            // Handle input
            if ($input === null || $input === '') {
                // Confirm selection
                break;
            } elseif (strtolower($input) === 'all') {
                foreach ($options as $key => $option) {
                    $options[$key]['selected'] = true;
                }
                $this->info('✓ All items selected');
                $this->newLine();
                continue;
            } elseif (strtolower($input) === 'none') {
                foreach ($options as $key => $option) {
                    $options[$key]['selected'] = false;
                }
                $this->info('✗ All items deselected');
                $this->newLine();
                continue;
            } elseif (is_numeric($input)) {
                $number = (int)$input;
                if ($number >= 1 && $number <= count($options)) {
                    $keys = array_keys($options);
                    $key = $keys[$number - 1];
                    $options[$key]['selected'] = !$options[$key]['selected'];
                    $status = $options[$key]['selected'] ? 'selected' : 'deselected';
                    $this->info("✓ {$options[$key]['label']} {$status}");
                    $this->newLine();
                } else {
                    $this->error('Invalid number. Please enter 1-' . count($options));
                    $this->newLine();
                }
            } else {
                $this->error('Invalid input. Please enter a number, "all", "none", or press Enter');
                $this->newLine();
            }

            // Clear screen for next iteration (optional, comment out if too aggressive)
            // $this->line("\033[2J\033[;H");
        }

        // Get selected items
        $selected = [];
        foreach ($options as $key => $option) {
            if ($option['selected']) {
                $selected[] = $key;
            }
        }

        return $selected;
    }

    /**
     * Setup test user
     */
    private function setupUser(bool $force)
    {
        $email = 'admin@example.com';
        $existing = User::where('email', $email)->first();

        if ($existing && !$force) {
            $this->warn("👤 User {$email} already exists (use --force to recreate)");
            return;
        }

        if ($existing && $force) {
            $existing->delete();
            $this->info("🗑️  Deleted existing user {$email}");
        }

        $user = User::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $this->info("👤 Created user: {$email} / password");
    }

    /**
     * Setup test band
     */
    private function setupBand(bool $force)
    {
        $siteName = 'test_band';
        $existing = Bands::where('site_name', $siteName)->first();

        if ($existing && !$force) {
            $this->warn("🎸 Band '{$siteName}' already exists (use --force to recreate)");
            return;
        }

        if ($existing && $force) {
            // Delete related data
            BandOwners::where('band_id', $existing->id)->delete();
            Bookings::where('band_id', $existing->id)->delete();
            StripeAccounts::where('band_id', $existing->id)->delete();
            RehearsalSchedule::where('band_id', $existing->id)->delete();
            $existing->delete();
            $this->info("🗑️  Deleted existing band '{$siteName}' and related data");
        }

        $user = User::where('email', 'admin@example.com')->first();
        if (!$user) {
            $this->error("❌ No admin user found. Run with --user first.");
            return;
        }

        $band = Bands::create([
            'name' => 'Test Band',
            'site_name' => $siteName,
        ]);

        BandOwners::create([
            'user_id' => $user->id,
            'band_id' => $band->id,
        ]);

        $this->info("🎸 Created band: {$band->name} ({$siteName})");
    }

    /**
     * Setup Stripe test accounts for all bands
     */
    private function setupStripeAccounts(bool $force)
    {
        $bands = Bands::all();

        if ($bands->isEmpty()) {
            $this->error("❌ No bands found. Run with --band first.");
            return;
        }

        // Initialize Stripe with the API key
        \Stripe\Stripe::setApiKey(config('services.stripe.key'));

        $created = 0;
        foreach ($bands as $band) {
            $existing = StripeAccounts::where('band_id', $band->id)->first();

            if ($existing && !$force) {
                $this->warn("💳 Stripe account for '{$band->name}' already exists");
                continue;
            }

            if ($existing && $force) {
                $existing->delete();
                $this->info("🗑️  Deleted existing Stripe account for '{$band->name}'");
            }

            try {
                // Create a real Stripe Connect account in test mode
                $account = \Stripe\Account::create([
                    'type' => 'express',
                    'country' => 'US',
                    'email' => $band->email ?? 'testband+' . $band->id . '@example.com',
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                ]);

                StripeAccounts::create([
                    'band_id' => $band->id,
                    'stripe_account_id' => $account->id,
                    'status' => 'active',
                ]);

                $created++;
                $this->info("💳 Created Stripe Connect account for: {$band->name} ({$account->id})");
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $this->error("❌ Failed to create Stripe account for '{$band->name}': {$e->getMessage()}");
            }
        }

        if ($created > 0) {
            $this->info("✅ Created {$created} Stripe Connect account(s)");
            $this->info("💡 Use Stripe test cards: 4242 4242 4242 4242");
        }
    }

    /**
     * Setup test bookings
     */
    private function setupBookings(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();
        $user = User::where('email', 'admin@example.com')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        if (!$user) {
            $this->error("❌ Admin user not found. Run with --user first.");
            return;
        }

        $existingCount = Bookings::where('band_id', $band->id)->count();

        if ($existingCount > 0 && !$force) {
            $this->warn("📅 Band already has {$existingCount} bookings (use --force to recreate)");
            return;
        }

        if ($existingCount > 0 && $force) {
            Bookings::where('band_id', $band->id)->delete();
            Payments::where('band_id', $band->id)->delete();
            $this->info("🗑️  Deleted {$existingCount} existing bookings");
        }

        $allBookings = [];

        // Create bookings across different time periods
        $periods = [
            ['months' => 12, 'count' => 3, 'paid' => 2],
            ['months' => 6, 'count' => 3, 'paid' => 2],
            ['months' => 3, 'count' => 2, 'paid' => 1],
            ['months' => 1, 'count' => 2, 'paid' => 1],
        ];

        foreach ($periods as $period) {
            for ($i = 0; $i < $period['count']; $i++) {
                $booking = Bookings::factory()->create([
                    'band_id' => $band->id,
                    'author_id' => $user->id,
                    'created_at' => now()->subMonths($period['months'])->addDays($i * 5),
                    'updated_at' => now()->subMonths($period['months'])->addDays($i * 5),
                ]);
                $allBookings[] = ['booking' => $booking, 'paid' => $i < $period['paid']];
            }
        }

        // Recent bookings
        for ($i = 0; $i < 5; $i++) {
            $booking = Bookings::factory()->create([
                'band_id' => $band->id,
                'author_id' => $user->id,
                'created_at' => now()->subDays($i * 2),
                'updated_at' => now()->subDays($i * 2),
            ]);
            $allBookings[] = $booking;
        }

        $totalCount = count($allBookings);
        $this->info("📅 Created {$totalCount} bookings");
        $this->info("💡 Use --payments to add partial payments with varied payment types");
    }

    /**
     * Setup rehearsal schedules
     */
    private function setupRehearsals(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        $existingCount = RehearsalSchedule::where('band_id', $band->id)->count();

        if ($existingCount > 0 && !$force) {
            $this->warn("🎵 Band already has {$existingCount} rehearsal schedules (use --force to recreate)");
            return;
        }

        if ($existingCount > 0 && $force) {
            RehearsalSchedule::where('band_id', $band->id)->delete();
            $this->info("🗑️  Deleted {$existingCount} existing rehearsal schedules");
        }

        RehearsalSchedule::create([
            'band_id' => $band->id,
            'name' => 'Weekly Practice',
            'description' => 'Regular weekly practice sessions',
            'frequency' => 'weekly',
            'day_of_week' => 'tuesday',
            'default_time' => '19:00:00',
            'location_name' => 'Band Practice Space',
            'location_address' => '123 Music St, New Orleans, LA 70115',
            'notes' => 'Bring your gear and be ready to rock!',
            'active' => true,
        ]);

        RehearsalSchedule::create([
            'band_id' => $band->id,
            'name' => 'Thursday Jam',
            'description' => 'Casual jam sessions',
            'frequency' => 'weekly',
            'day_of_week' => 'thursday',
            'default_time' => '20:00:00',
            'location_name' => 'Studio B',
            'location_address' => '456 Jazz Ave, New Orleans, LA 70116',
            'active' => true,
        ]);

        $this->info("🎵 Created 2 rehearsal schedules");
    }

    /**
     * Setup band members
     */
    private function setupMembers(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        $memberNames = [
            ['name' => 'Sarah Johnson', 'email' => 'sarah@testband.com'],
            ['name' => 'Mike Davis', 'email' => 'mike@testband.com'],
            ['name' => 'Emily Rodriguez', 'email' => 'emily@testband.com'],
            ['name' => 'James Wilson', 'email' => 'james@testband.com'],
            ['name' => 'Lisa Martinez', 'email' => 'lisa@testband.com'],
        ];

        $created = 0;
        foreach ($memberNames as $memberData) {
            $existingUser = User::where('email', $memberData['email'])->first();

            if ($existingUser && !$force) {
                continue;
            }

            if ($existingUser && $force) {
                BandMembers::where('user_id', $existingUser->id)->where('band_id', $band->id)->delete();
                $existingUser->delete();
            }

            $member = User::create([
                'name' => $memberData['name'],
                'email' => $memberData['email'],
                'password' => Hash::make('password'),
            ]);

            BandMembers::create([
                'user_id' => $member->id,
                'band_id' => $band->id,
            ]);

            $created++;
        }

        if ($created > 0) {
            $this->info("👥 Created {$created} band members");
        } else {
            $this->info("👥 Band members already exist");
        }
    }

    /**
     * Setup band roles
     */
    private function setupRoles(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        $roles = [
            ['name' => 'Vocals', 'display_order' => 1],
            ['name' => 'Guitar', 'display_order' => 2],
            ['name' => 'Bass', 'display_order' => 3],
            ['name' => 'Drums', 'display_order' => 4],
            ['name' => 'Keys', 'display_order' => 5],
            ['name' => 'Sax', 'display_order' => 6],
            ['name' => 'Trumpet', 'display_order' => 7],
            ['name' => 'Trombone', 'display_order' => 8],
        ];

        $existingCount = BandRole::where('band_id', $band->id)->count();

        if ($existingCount > 0 && !$force) {
            $this->warn("🎸 Band already has {$existingCount} roles (use --force to recreate)");
            return;
        }

        if ($existingCount > 0 && $force) {
            BandRole::where('band_id', $band->id)->delete();
            $this->info("🗑️  Deleted {$existingCount} existing roles");
        }

        foreach ($roles as $roleData) {
            BandRole::create([
                'band_id' => $band->id,
                'name' => $roleData['name'],
                'display_order' => $roleData['display_order'],
                'is_active' => true,
            ]);
        }

        $this->info("🎸 Created " . count($roles) . " band roles");
    }

    /**
     * Setup event rosters
     */
    private function setupRosters(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        $defaultRoster = $band->defaultRoster;

        if ($defaultRoster && !$force) {
            $this->warn("📋 Default roster already exists (use --force to recreate)");
            return;
        }

        if ($defaultRoster && $force) {
            $defaultRoster->delete();
            $this->info("🗑️  Deleted existing default roster");
        }

        $defaultRoster = Roster::createDefaultForBand($band);
        $this->info("📋 Created default roster");

        // Assign roles to roster members
        $roles = BandRole::where('band_id', $band->id)->orderBy('display_order')->get();

        if ($roles->isEmpty()) {
            $this->warn("⚠ No roles found. Run with --roles first to assign roles to roster members.");
            return;
        }

        $rosterMembers = $defaultRoster->members;
        foreach ($rosterMembers as $index => $member) {
            $role = $roles[$index % $roles->count()];
            $member->update([
                'band_role_id' => $role->id,
                'role' => $role->name,
            ]);
        }

        $this->info("✓ Assigned roles to {$rosterMembers->count()} roster members");
    }

    /**
     * Setup events for bookings
     */
    private function setupEvents(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        $bookings = Bookings::where('band_id', $band->id)->get();

        if ($bookings->isEmpty()) {
            $this->error("❌ No bookings found. Run with --bookings first.");
            return;
        }

        $defaultRoster = $band->defaultRoster;
        $created = 0;

        foreach ($bookings as $booking) {
            if ($booking->events()->exists() && !$force) {
                continue;
            }

            if ($booking->events()->exists() && $force) {
                $booking->events()->delete();
            }

            $event = [
                'event_type_id' => $booking->event_type_id,
                'key' => Str::uuid(),
                'title' => $booking->name,
                'date' => $booking->date,
                'time' => $booking->start_time,
                'roster_id' => $defaultRoster?->id,
                'additional_data' => [
                    'times' => [
                        ['title' => 'Load In', 'time' => $booking->start_date_time->copy()->subHours(4)->format('Y-m-d H:i')],
                        ['title' => 'Soundcheck', 'time' => $booking->start_date_time->copy()->subHours(3)->format('Y-m-d H:i')],
                        ['title' => 'Quiet', 'time' => $booking->start_date_time->copy()->subHours(1)->format('Y-m-d H:i')],
                        ['title' => 'End Time', 'time' => $booking->end_date_time->format('Y-m-d H:i')],
                    ],
                    'backline_provided' => false,
                    'production_needed' => true,
                    'color' => 'TBD',
                    'lodging' => [
                        ['title' => 'Provided', 'type' => 'checkbox', 'data' => false],
                        ['title' => 'location', 'type' => 'text', 'data' => 'TBD'],
                        ['title' => 'check_in', 'type' => 'text', 'data' => 'TBD'],
                        ['title' => 'check_out', 'type' => 'text', 'data' => 'TBD'],
                    ],
                    'public' => true,
                    'outside' => false,
                ]
            ];

            // Add wedding-specific data
            if ($booking->event_type_id === 1) {
                $event['additional_data']['wedding']['onsite'] = true;
                $event['additional_data']['wedding']['dances'] = [
                    ['title' => 'first_dance', 'data' => 'TBD'],
                    ['title' => 'father_daughter', 'data' => 'TBD'],
                    ['title' => 'mother_son', 'data' => 'TBD'],
                    ['title' => 'money_dance', 'data' => 'TBD'],
                    ['title' => 'bouquet_garter', 'data' => 'TBD']
                ];
                $event['additional_data']['times'][] = ['title' => 'Ceremony', 'time' => $booking->start_date_time->copy()->format('Y-m-d H:i')];
                $event['additional_data']['onsite'] = true;
                $event['additional_data']['public'] = false;
            }

            $booking->events()->create($event);
            $created++;
        }

        $this->info("📅 Created events for {$created} bookings");
    }

    /**
     * Setup partial payments with payment types
     */
    private function setupPayments(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        $bookings = Bookings::where('band_id', $band->id)->get();

        if ($bookings->isEmpty()) {
            $this->error("❌ No bookings found. Run with --bookings first.");
            return;
        }

        $existingCount = Payments::where('band_id', $band->id)
            ->where('payable_type', Bookings::class)
            ->count();

        if ($existingCount > 0 && !$force) {
            $this->warn("💰 Band already has {$existingCount} payments (use --force to recreate)");
            return;
        }

        if ($existingCount > 0 && $force) {
            Payments::where('band_id', $band->id)
                ->where('payable_type', Bookings::class)
                ->delete();
            $this->info("🗑️  Deleted {$existingCount} existing payments");
        }

        $paidCount = 0;
        $cancelledCount = 0;

        // Update booking statuses and create payments
        foreach ($bookings as $booking) {
            // For testing, make about half the bookings paid
            $shouldBePaid = (random_int(0, 100) < 50);

            if (!$shouldBePaid) {
                continue;
            }

            // Determine status
            $isCancelled = ($paidCount > 0 && $paidCount % 4 === 0);
            $status = $isCancelled ? 'cancelled' : 'confirmed';

            $booking->update(['status' => $status]);

            if ($isCancelled) {
                $cancelledCount++;
            }

            // Create partial payments
            $depositPercent = 0.5;
            $depositAmount = round($booking->price * $depositPercent, 2);
            $balanceAmount = $booking->price - $depositAmount;

            $paymentMethods = [
                PaymentType::Check,
                PaymentType::Portal,
                PaymentType::Venmo,
                PaymentType::Zelle,
                PaymentType::Cash,
            ];

            $depositMethod = $paymentMethods[$paidCount % count($paymentMethods)];
            $balanceMethod = $paymentMethods[($paidCount + 1) % count($paymentMethods)];

            Payments::create([
                'band_id' => $band->id,
                'payable_type' => Bookings::class,
                'payable_id' => $booking->id,
                'amount' => $depositAmount,
                'status' => 'paid',
                'payment_type' => $depositMethod,
                'name' => 'Deposit (50%)',
                'date' => $booking->created_at->copy()->addDays(7),
                'created_at' => $booking->created_at->copy()->addDays(7),
                'updated_at' => $booking->created_at->copy()->addDays(7),
            ]);

            Payments::create([
                'band_id' => $band->id,
                'payable_type' => Bookings::class,
                'payable_id' => $booking->id,
                'amount' => $balanceAmount,
                'status' => 'paid',
                'payment_type' => $balanceMethod,
                'name' => 'Balance (50%)',
                'date' => $booking->created_at->copy()->addDays(21),
                'created_at' => $booking->created_at->copy()->addDays(21),
                'updated_at' => $booking->created_at->copy()->addDays(21),
            ]);

            $paidCount++;
        }

        $confirmedCount = $paidCount - $cancelledCount;
        $this->info("💰 Created payments for {$paidCount} bookings ({$confirmedCount} confirmed, {$cancelledCount} cancelled)");
    }

    /**
     * Setup realistic contacts for bookings (replaces simple contacts)
     */
    private function setupContacts(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        $bookings = Bookings::where('band_id', $band->id)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get();

        if ($bookings->isEmpty()) {
            $this->warn("⚠ No confirmed/pending bookings found. Payments should be set up first.");
            return;
        }

        $existingCount = BookingContacts::whereIn('booking_id', $bookings->pluck('id'))->count();

        if ($existingCount > 0 && !$force) {
            $this->warn("👥 Bookings already have {$existingCount} contacts (use --force to recreate)");
            return;
        }

        if ($existingCount > 0 && $force) {
            BookingContacts::whereIn('booking_id', $bookings->pluck('id'))->delete();
            Contacts::where('band_id', $band->id)->delete();
            $this->info("🗑️  Deleted existing contacts");
        }

        $contactNames = [
            ['name' => 'Jennifer Smith', 'email' => 'jennifer.smith@example.com', 'phone' => '(555) 123-4567'],
            ['name' => 'Michael Johnson', 'email' => 'michael.j@example.com', 'phone' => '(555) 234-5678'],
            ['name' => 'Sarah Williams', 'email' => 'sarah.w@example.com', 'phone' => '(555) 345-6789'],
            ['name' => 'David Brown', 'email' => 'david.brown@example.com', 'phone' => '(555) 456-7890'],
            ['name' => 'Amanda Davis', 'email' => 'amanda.davis@example.com', 'phone' => '(555) 567-8901'],
            ['name' => 'Robert Miller', 'email' => 'robert.m@example.com', 'phone' => '(555) 678-9012'],
            ['name' => 'Emily Wilson', 'email' => 'emily.wilson@example.com', 'phone' => '(555) 789-0123'],
            ['name' => 'Christopher Moore', 'email' => 'chris.moore@example.com', 'phone' => '(555) 890-1234'],
            ['name' => 'Jessica Taylor', 'email' => 'jessica.t@example.com', 'phone' => '(555) 901-2345'],
            ['name' => 'Matthew Anderson', 'email' => 'matt.anderson@example.com', 'phone' => '(555) 012-3456'],
        ];

        $contactIndex = 0;
        $created = 0;

        foreach ($bookings as $booking) {
            $contactData = $contactNames[$contactIndex % count($contactNames)];

            $contact = Contacts::firstOrCreate(
                [
                    'band_id' => $band->id,
                    'email' => $contactData['email'],
                ],
                [
                    'name' => $contactData['name'],
                    'phone' => $contactData['phone'],
                    'can_login' => true,
                    'password' => Hash::make('password'),
                ]
            );

            BookingContacts::firstOrCreate(
                [
                    'booking_id' => $booking->id,
                    'contact_id' => $contact->id,
                ],
                [
                    'role' => $booking->event_type_id === 1 ? 'Bride' : 'Primary Contact',
                    'is_primary' => true,
                ]
            );

            $created++;
            $contactIndex++;
        }

        $this->info("👥 Created {$created} realistic contacts for bookings");
    }

    /**
     * Setup payout configuration
     */
    private function setupPayoutConfig(bool $force)
    {
        $band = Bands::where('site_name', 'test_band')->first();

        if (!$band) {
            $this->error("❌ Test band not found. Run with --band first.");
            return;
        }

        $existingCount = BandPayoutConfig::where('band_id', $band->id)->count();

        if ($existingCount > 0 && !$force) {
            $this->warn("💵 Band already has {$existingCount} payout config(s) (use --force to recreate)");
            return;
        }

        if ($existingCount > 0 && $force) {
            BandPayoutConfig::where('band_id', $band->id)->delete();
            $this->info("🗑️  Deleted {$existingCount} existing payout config(s)");
        }

        BandPayoutConfig::create([
            'band_id' => $band->id,
            'name' => 'Roster-Based - Event Attendance',
            'is_active' => true,
            'band_cut_type' => 'percentage',
            'band_cut_value' => 10.00,
            'member_payout_type' => 'equal_split',
            'tier_config' => null,
            'regular_member_count' => 0,
            'production_member_count' => 0,
            'member_specific_config' => null,
            'include_owners' => true,
            'include_members' => true,
            'minimum_payout' => 100.00,
            'notes' => 'Roster-based configuration using event attendance. Distributes payouts based on who actually played each event, weighted by attendance.',
            'use_payment_groups' => false,
            'payment_group_config' => null,
            'flow_diagram' => [
                'nodes' => [
                    [
                        'id' => 'income-1',
                        'type' => 'income',
                        'position' => ['x' => 100, 'y' => 100],
                        'data' => ['label' => 'Total Income'],
                    ],
                    [
                        'id' => 'bandcut-1',
                        'type' => 'bandCut',
                        'position' => ['x' => 300, 'y' => 100],
                        'data' => [
                            'label' => 'Band Cut (10%)',
                            'cutType' => 'percentage',
                            'value' => 10,
                            'deactivated' => false,
                        ],
                    ],
                    [
                        'id' => 'roster-players-1',
                        'type' => 'payoutGroup',
                        'position' => ['x' => 500, 'y' => 100],
                        'data' => [
                            'label' => 'Roster Members',
                            'sourceType' => 'roster',
                            'incomingAllocationType' => 'remainder',
                            'incomingAllocationValue' => 0,
                            'distributionMode' => 'equal_split',
                            'rosterConfig' => [
                                'memberTypeFilter' => 'all',
                                'filterByRoleId' => [],
                                'filterByRole' => [],
                            ],
                        ],
                    ],
                ],
                'edges' => [
                    ['id' => 'edge-1', 'source' => 'income-1', 'target' => 'bandcut-1'],
                    ['id' => 'edge-2', 'source' => 'bandcut-1', 'target' => 'roster-players-1'],
                ],
            ],
        ]);

        $this->info("💵 Created roster-based payout configuration");
    }
}
