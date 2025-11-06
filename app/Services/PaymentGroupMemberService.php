<?php

namespace App\Services;

use App\Models\BandPaymentGroup;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentGroupMemberService
{
    /**
     * Add a user to a payment group
     */
    public function addMember(int $bandId, int $groupId, int $userId, array $data = []): void
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        
        $this->validateMemberData($data);
        
        // Check if user exists
        if (!User::find($userId)) {
            throw ValidationException::withMessages([
                'user_id' => 'User not found.'
            ]);
        }

        // Check if user is already in the group
        if ($group->users()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'User is already in this payment group.'
            ]);
        }

        $group->users()->attach($userId, [
            'payout_type' => $data['payout_type'] ?? null,
            'payout_value' => $data['payout_value'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Remove a user from a payment group
     */
    public function removeMember(int $bandId, int $groupId, int $userId): void
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        
        if (!$group->users()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'User is not in this payment group.'
            ]);
        }

        $group->users()->detach($userId);
    }

    /**
     * Update a member's configuration in a payment group
     */
    public function updateMember(int $bandId, int $groupId, int $userId, array $data): void
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        
        if (!$group->users()->where('user_id', $userId)->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'User is not in this payment group.'
            ]);
        }

        $this->validateMemberData($data);

        $group->users()->updateExistingPivot($userId, [
            'payout_type' => $data['payout_type'] ?? null,
            'payout_value' => $data['payout_value'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Get all members of a payment group
     */
    public function getMembers(int $bandId, int $groupId): \Illuminate\Database\Eloquent\Collection
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        return $group->users;
    }

    /**
     * Get a specific member's configuration
     */
    public function getMemberConfig(int $bandId, int $groupId, int $userId): array
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        return $group->getUserPayoutConfig($userId);
    }

    /**
     * Bulk add members to a group
     */
    public function addMembers(int $bandId, int $groupId, array $userIds, array $defaultConfig = []): void
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        
        $this->validateMemberData($defaultConfig);

        $attachData = [];
        foreach ($userIds as $userId) {
            if (!User::find($userId)) {
                continue; // Skip invalid users
            }
            
            if ($group->users()->where('user_id', $userId)->exists()) {
                continue; // Skip already added users
            }

            $attachData[$userId] = [
                'payout_type' => $defaultConfig['payout_type'] ?? null,
                'payout_value' => $defaultConfig['payout_value'] ?? null,
                'notes' => $defaultConfig['notes'] ?? null,
            ];
        }

        if (!empty($attachData)) {
            $group->users()->attach($attachData);
        }
    }

    /**
     * Remove all members from a group
     */
    public function clearMembers(int $bandId, int $groupId): void
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        $group->users()->detach();
    }

    /**
     * Check if a user is in a payment group
     */
    public function isMember(int $bandId, int $groupId, int $userId): bool
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        return $group->users()->where('user_id', $userId)->exists();
    }

    /**
     * Get all payment groups a user belongs to for a band
     */
    public function getUserGroups(int $bandId, int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return BandPaymentGroup::where('band_id', $bandId)
            ->whereHas('users', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with('users')
            ->orderBy('display_order')
            ->get();
    }

    /**
     * Find a payment group or fail
     */
    protected function findGroupOrFail(int $bandId, int $groupId): BandPaymentGroup
    {
        return BandPaymentGroup::where('band_id', $bandId)
            ->where('id', $groupId)
            ->firstOrFail();
    }

    /**
     * Validate member data
     */
    protected function validateMemberData(array $data): void
    {
        $validator = Validator::make($data, [
            'payout_type' => 'nullable|in:percentage,fixed,equal_split',
            'payout_value' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional validation: percentage should have value <= 100
        if (isset($data['payout_type']) && 
            $data['payout_type'] === 'percentage' && 
            isset($data['payout_value']) && 
            $data['payout_value'] > 100) {
            throw ValidationException::withMessages([
                'payout_value' => 'Percentage value cannot exceed 100.'
            ]);
        }
    }
}
