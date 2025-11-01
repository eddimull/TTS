<?php

namespace App\Http\Controllers;

use App\Services\FinanceServices;
use App\Services\PaymentGroupService;
use App\Services\PaymentGroupMemberService;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class FinancesController extends Controller
{
    public function index()
    {
        return \redirect()->route('Revenue');
        // $user = Auth::user();
        // $bands = $user->bandOwner;

        // $financeServices = new FinanceServices();
        // $financialData = $this->getFinancialData($bands, $financeServices);

        // return Inertia::render('Finances/Index', $financialData);
    }

    public function paidUnpaid()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $paidUnpaid = $financeServices->getPaidUnpaid($bands);

        return Inertia::render('Finances/PaidUnpaid', ['paidUnpaid' => $paidUnpaid]);
    }

    public function revenue()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $financialData = $financeServices->getBandRevenueByYear($bands);

        return Inertia::render('Finances/Revenue', ['revenue' => $financialData]);
    }

    public function unpaidServices()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $unpaid = $financeServices->getUnpaid($bands);

        return Inertia::render('Finances/Unpaid', ['unpaid' => $unpaid]);
    }

    public function paidServices()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $paid = $financeServices->getPaid($bands);

        return Inertia::render('Finances/Paid', ['paid' => $paid]);
    }

    public function payments()
    {
        $user = Auth::user();
        $bands = $user->bandOwner;

        $financeServices = new FinanceServices();
        $payments = $financeServices->getBandPayments($bands);

        return Inertia::render('Finances/Payments', ['payments' => $payments]);
    }

    public function payoutCalculator()
    {
        $user = Auth::user();
        $bands = $user->bandOwner->load([
            'owners.user', 
            'members.user', 
            'activePayoutConfig',
            'paymentGroups.users'
        ]);

        return Inertia::render('Finances/PayoutCalculator', ['bands' => $bands]);
    }

    public function storePaymentGroup(Request $request, $bandId)
    {
        $service = app(PaymentGroupService::class);
        
        try {
            $service->create($bandId, $request->all());
            return redirect()->back()->with('success', 'Payment group created successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function updatePaymentGroup(Request $request, $bandId, $groupId)
    {
        $service = app(PaymentGroupService::class);
        
        try {
            $service->update($bandId, $groupId, $request->all());
            return redirect()->back()->with('success', 'Payment group updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function deletePaymentGroup($bandId, $groupId)
    {
        $service = app(PaymentGroupService::class);
        
        try {
            $service->delete($bandId, $groupId);
            return redirect()->back()->with('success', 'Payment group deleted successfully!');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->back()->with('error', 'Payment group not found.');
        }
    }

    public function addUserToPaymentGroup(Request $request, $bandId, $groupId)
    {
        $service = app(PaymentGroupMemberService::class);
        
        try {
            $service->addMember($bandId, $groupId, $request->user_id, $request->only(['payout_type', 'payout_value', 'notes']));
            return redirect()->back()->with('success', 'User added to payment group!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function removeUserFromPaymentGroup($bandId, $groupId, $userId)
    {
        $service = app(PaymentGroupMemberService::class);
        
        try {
            $service->removeMember($bandId, $groupId, $userId);
            return redirect()->back()->with('success', 'User removed from payment group!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function updateUserInPaymentGroup(Request $request, $bandId, $groupId, $userId)
    {
        $service = app(PaymentGroupMemberService::class);
        
        try {
            $service->updateMember($bandId, $groupId, $userId, $request->only(['payout_type', 'payout_value', 'notes']));
            return redirect()->back()->with('success', 'User payment configuration updated!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }
    }

    public function storePayoutConfig(Request $request, $bandId)
    {
        $request->validate([
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
        ]);

        // Deactivate other configs if this one is active
        if ($request->is_active) {
            \App\Models\BandPayoutConfig::where('band_id', $bandId)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $config = \App\Models\BandPayoutConfig::create([
            'band_id' => $bandId,
            'name' => $request->name,
            'is_active' => $request->is_active ?? true,
            'band_cut_type' => $request->band_cut_type,
            'band_cut_value' => $request->band_cut_value,
            'band_cut_tier_config' => $request->band_cut_tier_config,
            'member_payout_type' => $request->member_payout_type,
            'tier_config' => $request->tier_config,
            'regular_member_count' => $request->regular_member_count ?? 0,
            'production_member_count' => $request->production_member_count ?? 0,
            'production_member_types' => $request->production_member_types,
            'member_specific_config' => $request->member_specific_config,
            'include_owners' => $request->include_owners ?? true,
            'include_members' => $request->include_members ?? true,
            'minimum_payout' => $request->minimum_payout ?? 0,
            'notes' => $request->notes,
            'use_payment_groups' => $request->use_payment_groups ?? false,
            'payment_group_config' => $request->payment_group_config,
        ]);

        return redirect()->back()->with('success', 'Payout configuration saved successfully!');
    }

    public function updatePayoutConfig(Request $request, $bandId, $configId)
    {
        $config = \App\Models\BandPayoutConfig::where('band_id', $bandId)
            ->where('id', $configId)
            ->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'band_cut_type' => 'required|in:percentage,fixed,tiered,none',
            'band_cut_value' => 'required|numeric|min:0',
            'band_cut_tier_config' => 'nullable|array',
            'member_payout_type' => 'required|in:equal_split,percentage,fixed,tiered,member_specific',
            'production_member_types' => 'nullable|array',
            'use_payment_groups' => 'boolean',
            'payment_group_config' => 'nullable|array',
        ]);

        // Deactivate other configs if this one is being activated
        if ($request->is_active && !$config->is_active) {
            \App\Models\BandPayoutConfig::where('band_id', $bandId)
                ->where('id', '!=', $configId)
                ->where('is_active', true)
                ->update(['is_active' => false]);
        }

        $config->update($request->all());

        return redirect()->back()->with('success', 'Payout configuration updated successfully!');
    }

    public function deletePayoutConfig($bandId, $configId)
    {
        $config = \App\Models\BandPayoutConfig::where('band_id', $bandId)
            ->where('id', $configId)
            ->firstOrFail();

        $config->delete();

        return redirect()->back()->with('success', 'Payout configuration deleted successfully!');
    }
}
