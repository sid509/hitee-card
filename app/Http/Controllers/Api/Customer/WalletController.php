<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\BalanceIn;
use App\Models\BalanceOut;
use Illuminate\Http\Request;

/**
 * @group CustomerApi
 * @subgroup Wallet
 */
class WalletController extends Controller
{
    /**
     * GET /api/wallet
     *
     * Returns the wallet summary, active cards, and latest 5 transactions.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        // 1. Balance & Cards
        $cards = $user->cards()->where('is_currently_active', true)->get()->map(fn($card) => [
            'id'          => $card->id,
            'card_number' => $card->card_number,
            'status'      => $card->status,
            'balance_pts' => (float) $card->balance(),
        ]);

        // 2. Latest 5 Transactions
        $ins  = $user->balanceIns()->latest()->take(5)->get()->map(fn($tx) => $this->mapTopup($tx));
        $outs = $user->balanceOuts()->latest()->take(5)->get()->map(fn($tx) => $this->mapDeduction($tx));
        
        $latestTransactions = $ins->concat($outs)
            ->sortByDesc('date')
            ->take(5)
            ->values();

        return apiResponse(true, __('messages.wallet_fetched'), [
            'current_balance_pts' => (float) $user->balance(),
            'active_cards'        => $cards,
            'latest_transactions' => $latestTransactions,
            'disclaimer'          => __('messages.wallet_disclaimer'),
        ]);
    }

    /**
     * GET /api/wallet/categories
     *
     * Returns available transaction categories for filtering.
     */
    public function categories()
    {
        $categories = [
            ['id' => 'all',     'name' => __('messages.all')],
            ['id' => 'topup',   'name' => __('messages.direct_topup')],
            ['id' => 'khalti',  'name' => __('messages.khalti_payment')],
            ['id' => 'parking', 'name' => __('messages.parking_fee')],
            ['id' => 'travel',  'name' => __('messages.travel_fare')],
        ];

        return apiResponse(true, __('messages.categories_fetched'), $categories);
    }

    /**
     * GET /api/wallet/transactions
     *
     * Returns a filtered, paginated list of all transactions.
     */
    public function transactions(Request $request)
    {
        $request->validate([
            'category'  => 'nullable|string|in:all,topup,khalti,parking,travel',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'perPage'   => 'nullable|integer|min:1|max:100',
        ]);

        $user     = $request->user();
        $category = $request->get('category');
        $perPage  = (int) $request->get('perPage', 10);

        $inQuery  = $user->balanceIns();
        $outQuery = $user->balanceOuts();

        if ($category && $category !== 'all') {
            if ($category === 'topup') {
                $inQuery->where('type', 'manual');
                $outQuery->whereRaw('1=0');
            } elseif ($category === 'khalti') {
                $inQuery->where('type', 'khalti');
                $outQuery->whereRaw('1=0');
            } elseif ($category === 'parking') {
                $inQuery->whereRaw('1=0');
                $outQuery->where('type', 'parking');
            } elseif ($category === 'travel') {
                $inQuery->whereRaw('1=0');
                $outQuery->where('type', 'fare_deduction');
            }
        }

        if ($request->filled('date_from')) {
            $inQuery->whereDate('created_at', '>=', $request->date_from);
            $outQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $inQuery->whereDate('created_at', '<=', $request->date_to);
            $outQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $ins  = $inQuery->latest()->get()->map(fn($tx) => $this->mapTopup($tx));
        $outs = $outQuery->latest()->get()->map(fn($tx) => $this->mapDeduction($tx));

        $merged  = $ins->concat($outs)->sortByDesc('date')->values();
        $page    = (int) $request->get('page', 1);
        $total   = $merged->count();
        $items   = $merged->slice(($page - 1) * $perPage, $perPage)->values();

        return apiResponse(true, __('messages.transactions_fetched'), $items, 200, [], [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * Wallet Top-up
     *
     * Adds balance to the authenticated user's wallet.
     * This endpoint supports manual top-ups and Khalti (KPG) payments.
     * The amount is added as Hitee Points (pts), where 1 Rs = 1 pt.
     */
    public function topup(Request $request)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:1',
            'type'           => 'nullable|string|in:manual,khalti',
            'transaction_id' => 'nullable|string',
            'remarks'        => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $balanceIn = BalanceIn::create([
            'user_id'        => $user->id,
            'amount'         => $request->amount,
            'type'           => $request->get('type', 'manual'),
            'status'         => 'completed',
            'gateway_name'   => $request->get('type', 'manual') === 'khalti' ? 'Khalti' : 'App Manual',
            'transaction_id' => $request->transaction_id ?? 'TXN-' . strtoupper(uniqid()),
            'remarks'        => $request->remarks ?? 'Top-up from app',
            'created_by'     => $user->id,
        ]);

        return apiResponse(true, __('messages.wallet_topup_success'), [
            'transaction'     => $this->mapTopup($balanceIn),
            'current_balance' => (float) $user->balance(),
        ]);
    }

    private function mapTopup(BalanceIn $tx): array
    {
        return [
            'id'             => $tx->id,
            'direction'      => 'credit',
            'category'       => $tx->type === 'khalti' ? 'khalti' : 'topup',
            'amount_pts'     => (float) $tx->amount,
            'amount_rs'      => (float) $tx->amount,
            'status'         => $tx->status,
            'gateway'        => $tx->gateway_name,
            'transaction_id' => $tx->transaction_id,
            'remarks'        => $tx->remarks,
            'date'           => $tx->created_at?->toISOString(),
        ];
    }

    private function mapDeduction(BalanceOut $tx): array
    {
        return [
            'id'         => $tx->id,
            'direction'  => 'debit',
            'category'   => $tx->type === 'fare_deduction' ? 'travel' : 'parking',
            'amount_pts' => (float) $tx->amount,
            'remarks'    => $tx->remarks,
            'date'       => $tx->created_at?->toISOString(),
        ];
    }
}
