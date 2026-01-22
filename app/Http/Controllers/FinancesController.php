<?php

namespace App\Http\Controllers;

use App\Models\BandPayoutConfig;
use App\Services\FinanceServices;
use App\Services\PaymentGroupService;
use App\Services\PaymentGroupMemberService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class FinancesController extends Controller
{
    public function __construct(
        private readonly FinanceServices $financeServices
    ) {}

    public function index()
    {
        return redirect()->route('Revenue');
    }

    public function paidUnpaid(Request $request)
    {
        $bands = $this->getUserBands();
        $allBookings = $this->financeServices->getPaidUnpaid($bands, null);

        return Inertia::render('Finances/PaidUnpaid', [
            'allBookings' => $allBookings,
            'snapshotDate' => $request->input('snapshot_date'),
            'compareWithCurrent' => $request->boolean('compare_with_current'),
            'selectedYear' => $request->input('year')
        ]);
    }

    public function revenue()
    {
        $bands = $this->getUserBands();
        $financialData = $this->financeServices->getBandRevenueByYear($bands);

        return Inertia::render('Finances/Revenue', ['revenue' => $financialData]);
    }

    public function unpaidServices()
    {
        $bands = $this->getUserBands();
        $unpaid = $this->financeServices->getUnpaid($bands);

        return Inertia::render('Finances/Unpaid', ['unpaid' => $unpaid]);
    }

    public function paidServices()
    {
        $bands = $this->getUserBands();
        $paid = $this->financeServices->getPaid($bands);

        return Inertia::render('Finances/Paid', ['paid' => $paid]);
    }

    public function payments()
    {
        $bands = $this->getUserBands();
        $payments = $this->financeServices->getBandPayments($bands);

        return Inertia::render('Finances/Payments', ['payments' => $payments]);
    }

    public function payoutCalculator()
    {
        $bands = $this->getUserBands()->load([
            'owners.user',
            'members.user',
            'activePayoutConfig',
            'paymentGroups.users'
        ]);

        return Inertia::render('Finances/PayoutCalculator', ['bands' => $bands]);
    }

    public function payoutConfigurations()
    {
        $bands = $this->getUserBands()->load([
            'payoutConfigs' => fn($query) => $query->orderBy('is_active', 'desc')->orderBy('updated_at', 'desc')
        ]);

        return Inertia::render('Finances/PayoutConfigurations', ['bands' => $bands]);
    }

    public function storePaymentGroup(Request $request, $bandId, PaymentGroupService $service)
    {
        try {
            $service->create($bandId, $request->all());
            return redirect()->back()->with('success', 'Payment group created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function updatePaymentGroup(Request $request, $bandId, $groupId, PaymentGroupService $service)
    {
        try {
            $service->update($bandId, $groupId, $request->all());
            return redirect()->back()->with('success', 'Payment group updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function deletePaymentGroup($bandId, $groupId, PaymentGroupService $service)
    {
        try {
            $service->delete($bandId, $groupId);
            return redirect()->back()->with('success', 'Payment group deleted successfully!');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->back()->with('error', 'Payment group not found.');
        }
    }

    public function addUserToPaymentGroup(Request $request, $bandId, $groupId, PaymentGroupMemberService $service)
    {
        try {
            $service->addMember($bandId, $groupId, $request->user_id, $request->only(['payout_type', 'payout_value', 'notes']));
            return redirect()->back()->with('success', 'User added to payment group!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function removeUserFromPaymentGroup($bandId, $groupId, $userId, PaymentGroupMemberService $service)
    {
        try {
            $service->removeMember($bandId, $groupId, $userId);
            return redirect()->back()->with('success', 'User removed from payment group!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function updateUserInPaymentGroup(Request $request, $bandId, $groupId, $userId, PaymentGroupMemberService $service)
    {
        try {
            $service->updateMember($bandId, $groupId, $userId, $request->only(['payout_type', 'payout_value', 'notes']));
            return redirect()->back()->with('success', 'User payment configuration updated!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function storePayoutConfig(Request $request, $bandId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'band_cut_type' => 'required|in:percentage,fixed,tiered,none',
            'band_cut_value' => 'required|numeric|min:0',
            'band_cut_tier_config' => 'nullable|array',
            'member_payout_type' => 'required|in:equal_split,percentage,fixed,tiered,member_specific',
            'tier_config' => 'nullable|array',
            'regular_member_count' => 'nullable|integer|min:0',
            'production_member_count' => 'nullable|integer|min:0',
            'production_member_types' => 'nullable|array',
            'member_specific_config' => 'nullable|array',
            'include_owners' => 'boolean',
            'include_members' => 'boolean',
            'minimum_payout' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'use_payment_groups' => 'boolean',
            'payment_group_config' => 'nullable|array',
            'flow_diagram' => 'nullable|array',
        ]);

        if ($request->is_active) {
            $this->deactivateOtherConfigs($bandId);
        }

        BandPayoutConfig::create([
            'band_id' => $bandId,
            ...$validated,
            'is_active' => $request->is_active ?? true,
            'regular_member_count' => $validated['regular_member_count'] ?? 0,
            'production_member_count' => $validated['production_member_count'] ?? 0,
            'include_owners' => $validated['include_owners'] ?? true,
            'include_members' => $validated['include_members'] ?? true,
            'minimum_payout' => $validated['minimum_payout'] ?? 0,
            'use_payment_groups' => $validated['use_payment_groups'] ?? false,
        ]);

        return redirect()->back()->with('success', 'Payout configuration saved successfully!');
    }

    public function updatePayoutConfig(Request $request, $bandId, $configId)
    {
        $config = $this->findPayoutConfig($bandId, $configId);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'band_cut_type' => 'sometimes|required|in:percentage,fixed,tiered,none',
            'band_cut_value' => 'sometimes|required|numeric|min:0',
            'band_cut_tier_config' => 'nullable|array',
            'member_payout_type' => 'sometimes|required|in:equal_split,percentage,fixed,tiered,member_specific',
            'production_member_types' => 'nullable|array',
            'use_payment_groups' => 'sometimes|boolean',
            'payment_group_config' => 'nullable|array',
            'flow_diagram' => 'nullable|array',
        ]);

        if ($request->is_active && !$config->is_active) {
            $this->deactivateOtherConfigs($bandId, $configId);
        }

        $config->update($request->all());

        return redirect()->back()->with('success', 'Payout configuration updated successfully!');
    }

    public function deletePayoutConfig($bandId, $configId)
    {
        $this->findPayoutConfig($bandId, $configId)->delete();

        return redirect()->back()->with('success', 'Payout configuration deleted successfully!');
    }

    public function setActivePayoutConfig($bandId, $configId)
    {
        $config = $this->findPayoutConfig($bandId, $configId);

        $this->deactivateOtherConfigs($bandId, $configId);
        $config->update(['is_active' => true]);

        return redirect()->back()->with('success', 'Payout configuration activated successfully!');
    }

    public function duplicatePayoutConfig($bandId, $configId)
    {
        $original = $this->findPayoutConfig($bandId, $configId);

        $duplicate = $original->replicate();
        $duplicate->name = $original->name . ' (Copy)';
        $duplicate->is_active = false;
        $duplicate->save();

        return redirect()->back()->with('success', 'Payout configuration duplicated successfully!');
    }

    /**
     * Get user's owned bands.
     */
    private function getUserBands()
    {
        return Auth::user()->bandOwner;
    }

    /**
     * Find a payout config for the given band.
     */
    private function findPayoutConfig($bandId, $configId): BandPayoutConfig
    {
        return BandPayoutConfig::where('band_id', $bandId)
            ->where('id', $configId)
            ->firstOrFail();
    }

    /**
     * Deactivate all other payout configs for a band.
     */
    private function deactivateOtherConfigs($bandId, $excludeConfigId = null): void
    {
        $query = BandPayoutConfig::where('band_id', $bandId)
            ->where('is_active', true);

        if ($excludeConfigId) {
            $query->where('id', '!=', $excludeConfigId);
        }

        $query->update(['is_active' => false]);
    }
}
