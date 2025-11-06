# Payment Groups System

## Overview
The Payment Groups system provides flexible per-member pay management for bands. Instead of the rigid owner/member classification, bands can now create custom payment groups (e.g., Sound Crew, Lighting, Dancers, Players) with configurable payout rules.

## Database Schema

### Tables Created

#### `band_payment_groups`
Stores payment group definitions for each band.

**Fields:**
- `id` - Primary key
- `band_id` - Foreign key to bands table
- `name` - Group name (e.g., "Sound Crew", "Players")
- `description` - Optional description
- `default_payout_type` - enum: 'percentage', 'fixed', 'equal_split'
- `default_payout_value` - Default percentage or fixed amount
- `display_order` - For UI sorting
- `is_active` - Boolean flag
- `timestamps`

**Unique Constraint:** `band_id` + `name` (group names must be unique per band)

#### `band_payment_group_members`
Links users to payment groups with optional individual overrides.

**Fields:**
- `id` - Primary key
- `band_payment_group_id` - Foreign key to band_payment_groups
- `user_id` - Foreign key to users
- `payout_type` - enum: 'percentage', 'fixed', 'equal_split' (overrides group default)
- `payout_value` - Individual payout value (overrides group default)
- `notes` - Optional notes
- `timestamps`

**Unique Constraint:** `band_payment_group_id` + `user_id` (user can only be in a group once)

#### `band_payout_configs` (updated)
Added fields to support payment groups:
- `use_payment_groups` - Boolean flag to enable group-based calculations
- `payment_group_config` - JSON configuration for group allocations

**JSON Structure for `payment_group_config`:**
```json
[
  {
    "group_id": 1,
    "allocation_type": "percentage",
    "allocation_value": 60
  },
  {
    "group_id": 2,
    "allocation_type": "fixed",
    "allocation_value": 1000
  }
]
```

## Models

### `BandPaymentGroup`
**Location:** `app/Models/BandPaymentGroup.php`

**Relationships:**
- `belongsTo` Band
- `belongsToMany` User (through `band_payment_group_members`)

**Key Methods:**
- `getUserPayoutConfig($userId)` - Gets payout config for a specific user (with group default fallback)
- `calculateGroupPayout($distributableAmount)` - Calculates payouts for all members in the group

### `BandPayoutConfig` (updated)
**New Methods:**
- `calculatePayoutsWithGroups($result)` - Calculates payouts using payment group configuration

### `Bands` (updated)
**New Relationship:**
- `paymentGroups()` - hasMany relationship to active payment groups

## Controllers

### `FinancesController` (updated)
**Location:** `app/Http/Controllers/FinancesController.php`

**New Methods:**
- `storePaymentGroup($bandId)` - Create a new payment group
- `updatePaymentGroup($bandId, $groupId)` - Update payment group details
- `deletePaymentGroup($bandId, $groupId)` - Delete a payment group
- `addUserToPaymentGroup($bandId, $groupId)` - Add user to group
- `removeUserFromPaymentGroup($bandId, $groupId, $userId)` - Remove user from group
- `updateUserInPaymentGroup($bandId, $groupId, $userId)` - Update user's payout config in group

## Routes

**Location:** `routes/finances.php`

```php
// Payment Groups
Route::post('/payment-group/{bandId}', 'storePaymentGroup');
Route::put('/payment-group/{bandId}/{groupId}', 'updatePaymentGroup');
Route::delete('/payment-group/{bandId}/{groupId}', 'deletePaymentGroup');
Route::post('/payment-group/{bandId}/{groupId}/user', 'addUserToPaymentGroup');
Route::delete('/payment-group/{bandId}/{groupId}/user/{userId}', 'removeUserFromPaymentGroup');
Route::put('/payment-group/{bandId}/{groupId}/user/{userId}', 'updateUserInPaymentGroup');
```

## Frontend Components

### `PaymentGroupManager.vue`
**Location:** `resources/js/Pages/Finances/Components/PaymentGroupManager.vue`

Comprehensive UI for managing payment groups:
- Create/edit/delete payment groups
- Set group-level payout defaults
- Add/remove/edit users in groups
- Individual payout overrides per user
- Display order management

**Features:**
- Visual group organization with member lists
- Inline payout configuration display
- Modal dialogs for creating/editing
- Real-time validation

### `PayoutCalculator.vue` (updated)
**Location:** `resources/js/Pages/Finances/PayoutCalculator.vue`

Now includes:
- PaymentGroupManager component
- Support for group-based calculations
- Enhanced display for group-based payout results

## Usage Examples

### Creating a Payment Group
```php
// Via seeder or manual creation
BandPaymentGroup::create([
    'band_id' => $band->id,
    'name' => 'Sound Crew',
    'description' => 'Sound engineers and audio technicians',
    'default_payout_type' => 'fixed',
    'default_payout_value' => 500.00,
    'display_order' => 1,
    'is_active' => true,
]);
```

### Adding Users to a Group
```php
$soundCrew = BandPaymentGroup::find(1);

// Add user with group default
$soundCrew->users()->attach($userId);

// Add user with individual override
$soundCrew->users()->attach($userId, [
    'payout_type' => 'fixed',
    'payout_value' => 600.00,
    'notes' => 'Senior engineer',
]);
```

### Configuring a Payout Config with Groups
```php
BandPayoutConfig::create([
    'band_id' => $band->id,
    'name' => 'Group-Based - Standard Gig',
    'band_cut_type' => 'percentage',
    'band_cut_value' => 10.00,
    'use_payment_groups' => true,
    'payment_group_config' => [
        [
            'group_id' => $playersGroup->id,
            'allocation_type' => 'percentage',
            'allocation_value' => 60, // 60% to players
        ],
        [
            'group_id' => $soundCrewGroup->id,
            'allocation_type' => 'percentage',
            'allocation_value' => 15, // 15% to sound crew
        ],
        [
            'group_id' => $lightingGroup->id,
            'allocation_type' => 'fixed',
            'allocation_value' => 1000, // Fixed $1000 for lighting
        ],
    ],
]);
```

### Calculating Payouts
```php
$config = BandPayoutConfig::where('is_active', true)->first();
$result = $config->calculatePayouts(5000.00);

// $result structure:
[
    'total_amount' => 5000.00,
    'band_cut' => 500.00, // 10%
    'distributable_amount' => 4500.00,
    'payment_group_payouts' => [
        [
            'group_name' => 'Players',
            'group_id' => 1,
            'member_count' => 5,
            'payouts' => [
                ['user_id' => 1, 'user_name' => 'John', 'amount' => 540.00],
                // ... more members
            ],
            'total' => 2700.00
        ],
        // ... more groups
    ],
    'member_payouts' => [
        ['type' => 'payment_group', 'group_name' => 'Players', 'name' => 'John', 'amount' => 540.00],
        // ... flattened list of all member payouts
    ],
    'total_member_payout' => 4500.00,
    'remaining' => 0.00
]
```

## Payout Calculation Logic

### Group-Based Calculation Flow
1. **Band Cut**: Deduct band's percentage/fixed amount from total
2. **Distributable Amount**: Total - Band Cut
3. **Group Allocation**: For each payment group:
   - Calculate group's share based on `allocation_type` and `allocation_value`
   - Within group, distribute based on member payout types:
     - **Fixed**: Member gets specified fixed amount
     - **Percentage**: Member gets percentage of group allocation
     - **Equal Split**: Member shares remaining amount equally with other equal-split members
4. **Minimum Payout**: Apply config's `minimum_payout` if set

### Payout Type Priority
1. Individual member override (if set)
2. Group default payout type
3. Equal split as fallback

## Seeder Data

### `PayoutConfigSeeder`
**Location:** `database/seeders/PayoutConfigSeeder.php`

Creates example payment groups:
- **Players**: Equal split among band musicians
- **Sound Crew**: Fixed $500 default per member
- **Lighting**: Fixed $400 default per member
- **Dancers**: Fixed $300 default per member

And sample payout configurations demonstrating:
- Group-based allocation (60% players, 15% sound, 12% lighting, 13% dancers)
- Legacy equal-split configuration
- Tiered configuration

## Migration Notes

Run migrations:
```bash
docker-compose exec app php artisan migrate
```

Seed example data:
```bash
docker-compose exec app php artisan db:seed --class=PayoutConfigSeeder
```

## UI Access

Visit: `/finances/payout-calculator`

The page now shows:
1. **Payment Groups Management** - Create and manage groups per band
2. **Payout Calculator** - Test calculations with group-based configs
3. **Configuration Management** - Create payout configs using payment groups

## Benefits Over Previous System

### Before
- Fixed classification: owners vs members
- Production members as simple count
- Member-specific configs as unstructured JSON

### After
- **Flexible Groups**: Create any number of custom groups
- **Hierarchical Configuration**: Group defaults with individual overrides
- **Clear Role Separation**: Sound crew, lighting, dancers, players, etc.
- **Scalable**: Easy to add new members to appropriate groups
- **Trackable**: Notes per member, display ordering, active/inactive flags
- **Reusable**: Groups persist across multiple payout configurations

## Future Enhancements

Potential additions:
- Group templates (copy group structure across bands)
- Time-based group membership (seasonal members)
- Performance-based multipliers
- Integration with event types (different groups for different gig types)
- Group-level expense tracking
- Historical payout reports by group
