<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BalanceIn;
use App\Models\BalanceOut;
use Illuminate\Http\Request;

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

        return apiResponse(true, 'Wallet details fetched successfully', [
            'current_balance_pts' => (float) $user->balance(),
            'active_cards'        => $cards,
            'latest_transactions' => $latestTransactions,
            'disclaimer'          => '1 Rs = 1 Hitee Point (pts). Top-up amounts reflect real money paid.',
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
            ['id' => 'topup',   'name' => 'Direct Top-up'],
            ['id' => 'khalti',  'name' => 'Khalti Payment'],
            ['id' => 'parking', 'name' => 'Parking Fee'],
            ['id' => 'travel',  'name' => 'Travel Fare'],
        ];

        return apiResponse(true, 'Categories fetched successfully', $categories);
    }

    /**
     * GET /api/wallet/transactions
     *
     * Returns a filtered, paginated list of all transactions.
     */
    public function transactions(Request $request)
    {
        $request->validate([
            'category'  => 'nullable|string|in:topup,khalti,parking,travel',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'perPage'   => 'nullable|integer|min:1|max:100',
        ]);

        $user     = $request->user();
        $category = $request->get('category');
        $perPage  = (int) $request->get('perPage', 10);

        $inQuery  = $user->balanceIns()->getQuery();
        $outQuery = $user->balanceOuts()->getQuery();

        if ($category) {
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

        return apiResponse(true, 'Transactions fetched successfully', $items, 200, [], [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
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
