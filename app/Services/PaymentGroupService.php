<?php

namespace App\Services;

use App\Models\BandPaymentGroup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentGroupService
{
    /**
     * Create a new payment group for a band
     */
    public function create(int $bandId, array $data): BandPaymentGroup
    {
        $this->validateCreate($bandId, $data);

        return BandPaymentGroup::create([
            'band_id' => $bandId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'default_payout_type' => $data['default_payout_type'],
            'default_payout_value' => $data['default_payout_value'] ?? null,
            'display_order' => $data['display_order'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * Update an existing payment group
     */
    public function update(int $bandId, int $groupId, array $data): BandPaymentGroup
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        
        $this->validateUpdate($bandId, $groupId, $data);

        $group->update([
            'name' => $data['name'] ?? $group->name,
            'description' => $data['description'] ?? $group->description,
            'default_payout_type' => $data['default_payout_type'] ?? $group->default_payout_type,
            'default_payout_value' => $data['default_payout_value'] ?? $group->default_payout_value,
            'display_order' => $data['display_order'] ?? $group->display_order,
            'is_active' => $data['is_active'] ?? $group->is_active,
        ]);

        return $group->fresh();
    }

    /**
     * Delete a payment group
     */
    public function delete(int $bandId, int $groupId): bool
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        return $group->delete();
    }

    /**
     * Find a payment group or fail
     */
    public function findGroupOrFail(int $bandId, int $groupId): BandPaymentGroup
    {
        return BandPaymentGroup::where('band_id', $bandId)
            ->where('id', $groupId)
            ->firstOrFail();
    }

    /**
     * Get all payment groups for a band
     */
    public function getByBand(int $bandId, bool $activeOnly = false): \Illuminate\Database\Eloquent\Collection
    {
        $query = BandPaymentGroup::where('band_id', $bandId)
            ->orderBy('display_order')
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->get();
    }

    /**
     * Reorder payment groups
     */
    public function reorder(int $bandId, array $groupIdsInOrder): void
    {
        foreach ($groupIdsInOrder as $index => $groupId) {
            BandPaymentGroup::where('band_id', $bandId)
                ->where('id', $groupId)
                ->update(['display_order' => $index]);
        }
    }

    /**
     * Toggle active status
     */
    public function toggleActive(int $bandId, int $groupId): BandPaymentGroup
    {
        $group = $this->findGroupOrFail($bandId, $groupId);
        $group->is_active = !$group->is_active;
        $group->save();
        
        return $group;
    }

    /**
     * Validate create data
     */
    protected function validateCreate(int $bandId, array $data): void
    {
        $validator = Validator::make($data, [
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:band_payment_groups,name,NULL,id,band_id,' . $bandId
            ],
            'description' => 'nullable|string',
            'default_payout_type' => 'required|in:percentage,fixed,equal_split',
            'default_payout_value' => 'nullable|numeric|min:0',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional validation: percentage should have value <= 100
        if ($data['default_payout_type'] === 'percentage' && 
            isset($data['default_payout_value']) && 
            $data['default_payout_value'] > 100) {
            throw ValidationException::withMessages([
                'default_payout_value' => 'Percentage value cannot exceed 100.'
            ]);
        }
    }

    /**
     * Validate update data
     */
    protected function validateUpdate(int $bandId, int $groupId, array $data): void
    {
        $validator = Validator::make($data, [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'unique:band_payment_groups,name,' . $groupId . ',id,band_id,' . $bandId
            ],
            'description' => 'nullable|string',
            'default_payout_type' => 'sometimes|required|in:percentage,fixed,equal_split',
            'default_payout_value' => 'nullable|numeric|min:0',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        // Additional validation: percentage should have value <= 100
        if (isset($data['default_payout_type']) && 
            $data['default_payout_type'] === 'percentage' && 
            isset($data['default_payout_value']) && 
            $data['default_payout_value'] > 100) {
            throw ValidationException::withMessages([
                'default_payout_value' => 'Percentage value cannot exceed 100.'
            ]);
        }
    }
}
