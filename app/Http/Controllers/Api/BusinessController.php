<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusinessSite;
use App\Models\LedgerEntry;
use App\Services\EscrowService;
use App\Services\LedgerService;
use App\Services\PlatformConfig;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessController extends Controller
{
    public function show(Request $request, LedgerService $ledger, PlatformConfig $config): JsonResponse
    {
        $business = $this->business($request);
        $business->load('sites', 'wallet');
        $balance = $ledger->walletBalance($business->wallet);

        return response()->json([
            'data' => [
                'id' => $business->id,
                'name' => $business->name,
                'wallet' => [
                    'id' => $business->wallet->id,
                    'balance' => $balance,
                    'currency' => $config->get('currency'),
                    'auto_topup_enabled' => $business->wallet->auto_topup_enabled,
                    'auto_topup_threshold' => $business->wallet->auto_topup_threshold,
                    'auto_topup_amount' => $business->wallet->auto_topup_amount,
                ],
                'sites' => $business->sites->map(fn (BusinessSite $site) => $this->sitePayload($site, $ledger, $config)),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:160'],
            'auto_topup_enabled' => ['sometimes', 'boolean'],
            'auto_topup_threshold' => ['nullable', 'numeric', 'min:0'],
            'auto_topup_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if (isset($data['name'])) {
            $business->name = $data['name'];
            $business->save();
        }

        $business->wallet->fill(collect($data)->only([
            'auto_topup_enabled', 'auto_topup_threshold', 'auto_topup_amount',
        ])->all());
        $business->wallet->save();

        return response()->json(['data' => $business->load('wallet', 'sites')]);
    }

    public function storeSite(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $this->siteData($request);
        $site = $business->sites()->create($data);

        return response()->json(['data' => $site], 201);
    }

    public function updateSite(Request $request, BusinessSite $site): JsonResponse
    {
        $business = $this->business($request);
        abort_unless($site->business_id === $business->id, 403);
        $site->fill($this->siteData($request, partial: true));
        $site->save();

        return response()->json(['data' => $site]);
    }

    public function topUp(Request $request, EscrowService $escrow, LedgerService $ledger): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:10000000'],
            'method' => ['required', 'in:airtel_money,mtn_momo,zamtel_kwacha,bank_transfer'],
        ]);

        $escrow->topUp($business->wallet, $request->user(), (string) $data['amount'], $data['method']);

        return response()->json([
            'data' => [
                'balance' => $ledger->walletBalance($business->wallet),
                'currency' => config('xpressbine.currency'),
            ],
        ]);
    }

    public function statement(Request $request, LedgerService $ledger): JsonResponse
    {
        $business = $this->business($request);
        $entries = LedgerEntry::query()
            ->where('wallet_id', $business->wallet->id)
            ->latest()
            ->paginate(30);

        return response()->json([
            'balance' => $ledger->walletBalance($business->wallet),
            'currency' => config('xpressbine.currency'),
            'data' => $entries->items(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    private function business(Request $request): \App\Models\Business
    {
        $business = $request->user()->business;
        abort_unless($business && $business->wallet, 404, 'No business wallet on this account.');

        return $business;
    }

    /**
     * @return array<string, mixed>
     */
    private function siteData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$required, 'string', 'max:160'],
            'area' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'spending_limit' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePayload(BusinessSite $site, LedgerService $ledger, PlatformConfig $config): array
    {
        $spent = $ledger->siteSpendThisMonth($site);
        $limit = $site->spending_limit;
        $alert = null;

        if ($limit !== null && Money::isPositive($limit)) {
            $ratio = (float) $spent / (float) $limit;
            if ($ratio >= (float) $config->get('budget_alert_full')) {
                $alert = 'full';
            } elseif ($ratio >= (float) $config->get('budget_alert_warn')) {
                $alert = 'warn';
            }
        }

        return [
            'id' => $site->id,
            'name' => $site->name,
            'area' => $site->area,
            'address' => $site->address,
            'latitude' => $site->latitude,
            'longitude' => $site->longitude,
            'spending_limit' => $site->spending_limit,
            'spent_this_month' => $spent,
            'budget_alert' => $alert,
        ];
    }
}
