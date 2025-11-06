# Sequential Payment Group Allocation - Real World Example

## Overview
Payment groups are now allocated **sequentially** based on their display order. This allows you to create formulas like:

```
(Total - Band Cut - Production Costs - Sound Crew) / Remaining Players
```

## Example Scenario: Wedding Gig

**Total Booking**: $5,000  
**Band Cut**: 10% = $500  
**Distributable Amount**: $4,500

### Payment Group Configuration (in order)

#### Group 1: Production (Display Order: 1)
- **Purpose**: Cover fixed costs for sound equipment and setup
- **Allocation Type**: Fixed
- **Allocation Value**: $700
- **Members**: 1 (Sound Engineer)
- **Member Config**: Uses group default (fixed $700)

**Calculation**:
- Takes: $700 (fixed amount)
- Remaining: $4,500 - $700 = **$3,800**

---

#### Group 2: Guest Musicians (Display Order: 2)
- **Purpose**: Pay contracted guest performers
- **Allocation Type**: Fixed
- **Allocation Value**: $800
- **Members**: 2
  - Guest Vocalist: Fixed $500
  - Guest Saxophonist: Fixed $300

**Calculation**:
- Takes from remaining: $800 (fixed amount)
- Individual payouts:
  - Guest Vocalist: $500
  - Guest Saxophonist: $300
- Remaining: $3,800 - $800 = **$3,000**

---

#### Group 3: Core Band Members (Display Order: 3)
- **Purpose**: Split remaining amount among regular band members
- **Allocation Type**: Percentage
- **Allocation Value**: 100% (of remaining)
- **Members**: 4
  - Lead Guitar: Equal split
  - Bass: Equal split
  - Drums: Equal split
  - Keys: Equal split

**Calculation**:
- Takes from remaining: 100% of $3,000 = $3,000
- Individual payouts: $3,000 / 4 = **$750 each**
- Remaining: $3,000 - $3,000 = **$0**

---

## Complete Breakdown

| Group | Member | Type | Amount | Running Remaining |
|-------|--------|------|--------|-------------------|
| **Start** | - | - | - | $5,000 |
| Band Cut | - | 10% | -$500 | $4,500 |
| **Production** | Sound Engineer | Fixed | $700 | $3,800 |
| **Guest Musicians** | Guest Vocalist | Fixed | $500 | $3,300 |
| **Guest Musicians** | Guest Saxophonist | Fixed | $300 | $3,000 |
| **Core Band** | Lead Guitar | Equal Split | $750 | $2,250 |
| **Core Band** | Bass | Equal Split | $750 | $1,500 |
| **Core Band** | Drums | Equal Split | $750 | $750 |
| **Core Band** | Keys | Equal Split | $750 | $0 |

**Total Distributed**: $5,000  
**Final Remaining**: $0

---

## Why Sequential Allocation?

### Problem with Parallel Allocation
In the old system, percentages were calculated from the original distributable amount:
- Production: 15% of $4,500 = $675
- Guests: 18% of $4,500 = $810
- Core Band: 67% of $4,500 = $3,015
- **Total**: $4,500 ✓

But you couldn't express: "Pay production their fixed cost, then split the rest"

### Solution with Sequential Allocation
Now you can express complex allocation formulas:
- Production gets their fixed $700 **first**
- Guests get their fixed amounts **next**
- Core band splits **whatever remains**

This matches real-world thinking: "After covering our costs, we'll split the rest"

---

## Configuration in UI

When you configure payment groups in the Payout Calculator:

1. **Create Groups in Order** (use drag-and-drop to reorder)
   - Production (Order: 1)
   - Guest Musicians (Order: 2)
   - Core Band Members (Order: 3)

2. **Set Allocation per Group**
   - Production: Fixed $700
   - Guests: Fixed $800
   - Core Band: 100% (of remaining)

3. **Set Individual Member Overrides** (optional)
   - Within Guests: Vocalist gets $500, Sax gets $300
   - Within Core Band: All use equal_split

4. **Visual Indicators**
   - Groups numbered with order
   - Color-coded borders (blue, green, orange, purple)
   - "Sequential Allocation" info banner explains the flow

---

## Calculator Results Display

When you calculate, you'll see:

```
Payment Group Breakdown (Sequential)

1. Production ($700)
   ├─ John Sound Engineer (fixed): $700

2. Guest Musicians ($800)
   ├─ Sarah Vocalist (fixed): $500
   └─ Mike Saxophonist (fixed): $300

3. Core Band Members ($3,000)
   ├─ Tom Guitar (equal_split): $750
   ├─ Lisa Bass (equal_split): $750
   ├─ Bob Drums (equal_split): $750
   └─ Alice Keys (equal_split): $750

Remaining (unallocated): $0.00
```

---

## Advanced Example: Tiered Production Costs

You can even use **percentage** allocations for earlier groups:

### Scenario: Festival with Variable Production
- **Total**: $10,000
- **Band Cut**: 15% = $1,500
- **Distributable**: $8,500

#### Group 1: Production (30% of distributable)
- Takes: 30% of $8,500 = $2,550
- Remaining: $8,500 - $2,550 = $5,950

#### Group 2: Core Band (100% of remaining)
- Takes: 100% of $5,950 = $5,950
- Remaining: $0

This ensures production costs scale with the gig size, while core band gets the rest.

---

## Testing Your Configuration

Use the **Quick Calculator** to test different scenarios:

1. Enter different booking amounts
2. See how sequential allocation distributes funds
3. Adjust group order or allocation values
4. Save configuration when satisfied

The calculator shows real-time previews with the exact formula being applied.

---

## Best Practices

1. **Order Matters**: Put fixed costs first (production, guests, venue fees)
2. **Use 100% for Final Group**: Usually your core band gets "everything left"
3. **Test Different Amounts**: Try small gigs ($1,000) and large ($20,000) to ensure fair distribution
4. **Document Your Formula**: Add notes explaining why groups are ordered this way
5. **Review Regularly**: As your band grows, you may need to adjust allocations

---

## Migration from Old System

If you were using the old parallel allocation:

**Old Config** (Parallel):
- Production: 15% of $4,500 = $675
- Core Band: 85% of $4,500 = $3,825

**New Config** (Sequential - Equivalent):
- Production: 15% allocation (Order: 1) = 15% of $4,500 = $675
- Core Band: 100% allocation (Order: 2) = 100% of remaining $3,825 = $3,825

**Or (Better - Fixed Costs First)**:
- Production: Fixed $700 (Order: 1)
- Core Band: 100% (Order: 2) = Whatever remains

The new system gives you **more flexibility** while maintaining backward compatibility.
