# Payment Group Calculator Integration

## Overview
The Payout Calculator now supports payment groups as an alternative to the traditional member-based payout calculations. When enabled, payouts are distributed based on configured payment groups instead of individual members.

## How It Works

### Enabling Payment Groups
1. Navigate to **Finances → Payout Calculator**
2. Click "Edit Configuration" for your band
3. Check the **"Use Payment Groups"** checkbox
4. Configure allocation for each payment group

### Configuration Options

#### Payment Group Allocation
Each payment group can be allocated funds in two ways:
- **Percentage**: A percentage of the distributable amount (after band cut)
- **Fixed**: A fixed dollar amount

#### Within-Group Distribution
Each member in a payment group receives payment based on:
- **Their individual configuration** (if set): Can be percentage, fixed, or equal_split
- **Group default** (if no individual config): Uses the group's default payout type and value

### Example Configuration

**Scenario**: $5,000 booking with 10% band cut = $4,500 distributable

**Payment Groups (Sequential Allocation)**:

Groups are allocated **in order** based on their display order. Each group takes from the **remaining** amount after previous groups.

1. **Production** (1 member) - Display Order: 1
   - Allocation: Fixed $700
   - Remaining after: $4,500 - $700 = $3,800
   - Result: Production member gets $700

2. **Players** (4 members) - Display Order: 2
   - Allocation: 100% of remaining = $3,800
   - 3 members on equal_split
   - 1 member with fixed $900
   - Calculation:
     - Fixed member: $900
     - Remaining for equal_split: $3,800 - $900 = $2,900
     - Each equal_split member: $2,900 / 3 = $966.67
   - Remaining after: $3,800 - $3,800 = $0

**Alternative Example** (Percentage-based):
1. **Sound Crew** (2 members) - Display Order: 1
   - Allocation: 25% of $4,500 = $1,125
   - Remaining after: $4,500 - $1,125 = $3,375

2. **Lighting** (2 members) - Display Order: 2
   - Allocation: 15% of remaining $3,375 = $506.25
   - Remaining after: $3,375 - $506.25 = $2,868.75

3. **Players** (4 members) - Display Order: 3
   - Allocation: 100% of remaining $2,868.75
   - Result: $2,868.75 / 4 = $717.19 each

### Calculator Display

When using payment groups, the calculator results show:
- **Payment Group Breakdown**: Displays each group with member details
- **Group Total**: Sum of all payouts within the group
- **Member Details**: Individual member names, payout types, and amounts

### Traditional vs Payment Group Mode

| Feature | Traditional Mode | Payment Group Mode |
|---------|-----------------|-------------------|
| Member Selection | Include Owners/Members checkboxes | Managed via Payment Groups |
| Payout Types | Applied to all members | Applied per group or per member |
| Configuration | Single payout type for all | Flexible per group/member |
| Display | Generic "Owner", "Member", "Production" | Named groups with member names |

## Database Schema

### Tables Involved
- `band_payout_configs`: Stores `use_payment_groups` flag and `payment_group_config` JSON
- `band_payment_groups`: Group definitions with default payout settings
- `band_payment_group_members`: Member assignments with optional overrides

### Payment Group Config Structure
```json
{
  "use_payment_groups": true,
  "payment_group_config": [
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
}
```

## API Endpoints

### Save Configuration
```
POST /finances/payout-config/{bandId}
PUT /finances/payout-config/{bandId}/{configId}
```

**Payload**:
```json
{
  "name": "Festival Rate",
  "band_cut_type": "percentage",
  "band_cut_value": 10,
  "use_payment_groups": true,
  "payment_group_config": [
    {
      "group_id": 1,
      "allocation_type": "percentage",
      "allocation_value": 60
    }
  ],
  "minimum_payout": 0,
  "notes": "Standard festival configuration"
}
```

## Calculation Flow (Sequential Allocation)

1. **Calculate Band Cut**: Apply band cut (percentage/fixed/tiered) to total amount
2. **Determine Distributable Amount**: Total - Band Cut
3. **Sequential Group Allocation**: For each active payment group (in display_order):
   - Calculate group allocation from **remaining amount**:
     - **Percentage**: X% of current remaining amount
     - **Fixed**: Exact dollar amount
   - Within the group, calculate member payouts:
     - First pass: Calculate fixed and percentage members
     - Second pass: Distribute remaining to equal_split members
   - **Subtract** group total from remaining amount
   - Move to next group with new remaining amount
4. **Apply Minimum Payout**: Ensure each member meets minimum payout threshold
5. **Calculate Final Remaining**: Any unallocated funds after all groups

### Key Difference from Parallel Allocation
- **Sequential**: Each group takes from what's left after previous groups
- **Parallel** (old): Each group takes from original distributable amount
- **Formula Support**: Enables expressions like `(Net - Band Cut - Production) / Players`

## Frontend Components

### PayoutCalculator.vue
Main component that:
- Toggles between traditional and payment group mode
- Displays payment group allocation inputs
- Shows results with group breakdowns
- Handles configuration saving

### PaymentGroupManager.vue
Separate component for:
- Creating/editing payment groups
- Managing group members
- Setting individual member overrides

## Testing

Test data has been seeded with:
- 10 users across 4 payment groups
- Various payout configurations (equal_split, percentage, fixed)
- Different allocation strategies

To test:
1. Visit `/finances/payout-calculator`
2. Enable "Use Payment Groups"
3. Configure group allocations
4. Save configuration
5. Use Quick Calculator to see results

## Business Logic

Located in:
- **Backend**: `app/Models/BandPayoutConfig.php` → `calculatePayoutsWithGroups()`
- **Frontend**: `resources/js/Pages/Finances/PayoutCalculator.vue` → `calculatePayoutsWithGroups()`

Both implementations mirror the same logic to provide real-time preview in the UI.

## Benefits

1. **Flexibility**: Different payout rules for different team members
2. **Clarity**: Named groups make purpose clear (Players, Crew, etc.)
3. **Scalability**: Easy to add new members to existing groups
4. **Override Support**: Individual members can have custom configurations
5. **Transparency**: Clear breakdown shows exactly how money is distributed

## Future Enhancements

- [ ] Historical tracking of group configurations per event
- [ ] Template configurations for common scenarios (wedding, festival, etc.)
- [ ] Automated suggestions based on past bookings
- [ ] Export group payout breakdown to PDF/CSV
- [ ] Integration with payment processing for automatic distribution
