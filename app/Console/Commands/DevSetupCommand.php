<?php

namespace App\Console\Commands;

use App\Models\Bands;
use App\Models\BandOwners;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Payments;
use App\Models\RehearsalSchedule;
use App\Models\StripeAccounts;
use App\Models\User;
use Database\Seeders\EventTypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

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
                            {--bookings : Create test bookings}
                            {--stripe : Create Stripe test accounts}
                            {--contacts : Create test contacts}
                            {--rehearsals : Create rehearsal schedules}
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

        if ($all || $this->option('stripe')) {
            $this->setupStripeAccounts($force);
        }

        if ($all || $this->option('bookings')) {
            $this->setupBookings($force);
        }

        if ($all || $this->option('contacts')) {
            $this->setupContacts($force);
        }

        if ($all || $this->option('rehearsals')) {
            $this->setupRehearsals($force);
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
               $this->option('bookings') || 
               $this->option('stripe') ||
               $this->option('contacts') ||
               $this->option('rehearsals');
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
            'contacts' => [
                'label' => 'Test Contacts',
                'description' => 'Contact records for each booking',
                'selected' => false,
            ],
            'rehearsals' => [
                'label' => 'Rehearsal Schedules',
                'description' => 'Weekly practice sessions',
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
            $allBookings[] = ['booking' => $booking, 'paid' => $i < 2];
        }

        // Add payments to paid bookings
        $paidCount = 0;
        foreach ($allBookings as $item) {
            if ($item['paid']) {
                Payments::create([
                    'band_id' => $band->id,
                    'payable_type' => Bookings::class,
                    'payable_id' => $item['booking']->id,
                    'amount' => $item['booking']->price,
                    'status' => 'paid',
                    'name' => 'Full Payment',
                    'date' => $item['booking']->created_at->addDays(7),
                    'created_at' => $item['booking']->created_at->addDays(7),
                    'updated_at' => $item['booking']->created_at->addDays(7),
                ]);
                $paidCount++;
            }
        }

        $totalCount = count($allBookings);
        $unpaidCount = $totalCount - $paidCount;
        $this->info("📅 Created {$totalCount} bookings ({$paidCount} paid, {$unpaidCount} unpaid)");
    }

    /**
     * Setup test contacts for bookings
     */
    private function setupContacts(bool $force)
    {
        $bookings = Bookings::whereDoesntHave('contacts')->get();

        if ($bookings->isEmpty() && !$force) {
            $this->info("👥 All bookings already have contacts");
            return;
        }

        $created = 0;
        foreach ($bookings as $booking) {
            $contact = Contacts::create([
                'name' => 'Test Contact ' . $booking->id,
                'email' => 'contact' . $booking->id . '@example.com',
                'phone' => '555-' . str_pad($booking->id, 4, '0', STR_PAD_LEFT),
                'band_id' => $booking->band_id,
            ]);

            $booking->contacts()->attach($contact->id);
            $created++;
        }

        $this->info("👥 Created {$created} test contacts for bookings");
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
}
