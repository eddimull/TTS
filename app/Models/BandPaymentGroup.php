<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BandPaymentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'band_id',
        'name',
        'description',
        'default_payout_type',
        'default_payout_value',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'default_payout_value' => 'decimal:2',
        'display_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class);
    }

    /**
     * Users in this payment group
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'band_payment_group_members')
            ->withPivot(['payout_type', 'payout_value', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get the payout configuration for a specific user
     */
    public function getUserPayoutConfig($userId): array
    {
        $member = $this->users()->where('user_id', $userId)->first();
        
        if (!$member) {
            return [
                'payout_type' => $this->default_payout_type,
                'payout_value' => $this->default_payout_value,
            ];
        }

        return [
            'payout_type' => $member->pivot->payout_type ?? $this->default_payout_type,
            'payout_value' => $member->pivot->payout_value ?? $this->default_payout_value,
        ];
    }

    /**
     * Calculate payout for this group given a distributable amount
     * 
     * Note: The $distributableAmount is the total allocated to this group.
     * Member payout types (percentage/fixed) are applied WITHIN this allocation.
     * - Percentage: percentage of the group's allocation
     * - Fixed: exact dollar amount (taken from group allocation)
     * - Equal Split: share remaining after fixed/percentage members
     */
    public function calculateGroupPayout(float $distributableAmount, $attendanceData = null): array
    {
        $members = $this->users()->get();

        if ($members->isEmpty()) {
            return [
                'group_name' => $this->name,
                'group_id' => $this->id,
                'member_count' => 0,
                'payouts' => [],
                'total' => 0,
            ];
        }

        $payouts = [];
        $totalGroupPayout = 0;

        // First pass: Calculate fixed and percentage payouts
        foreach ($members as $member) {
            $config = $this->getUserPayoutConfig($member->id);
            $amount = 0;

            if ($config['payout_type'] === 'percentage') {
                $amount = ($distributableAmount * $config['payout_value']) / 100;
            } elseif ($config['payout_type'] === 'fixed') {
                $amount = $config['payout_value'];
            }
            // equal_split will be calculated in second pass

            // Get role from attendance data if available
            $role = null;
            if ($attendanceData && is_object($attendanceData)) {
                $attendance = $attendanceData->firstWhere('user_id', $member->id);
                if ($attendance) {
                    $role = $attendance['role'] ?? null;
                }
            }

            $payouts[] = [
                'user_id' => $member->id,
                'user_name' => $member->name,
                'group_name' => $this->name,
                'role' => $role,
                'payout_type' => $config['payout_type'],
                'amount' => $amount,
            ];

            if ($config['payout_type'] !== 'equal_split') {
                $totalGroupPayout += $amount;
            }
        }

        // Second pass: Calculate equal_split members
        $equalSplitMembers = array_filter($payouts, fn($p) => $p['payout_type'] === 'equal_split');
        if (count($equalSplitMembers) > 0) {
            $remainingAmount = $distributableAmount - $totalGroupPayout;
            $perMemberAmount = $remainingAmount / count($equalSplitMembers);
            
            foreach ($payouts as &$payout) {
                if ($payout['payout_type'] === 'equal_split') {
                    $payout['amount'] = $perMemberAmount;
                    $totalGroupPayout += $perMemberAmount;
                }
            }
        }

        return [
            'group_name' => $this->name,
            'group_id' => $this->id,
            'member_count' => count($members),
            'payouts' => $payouts,
            'total' => $totalGroupPayout,
        ];
    }
}
