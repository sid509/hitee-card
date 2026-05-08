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
     * This endpoint supports Khalti and Stripe payment initiation.
     * The amount is added as Hitee Points (pts), where 1 Rs = 1 pt.
     */
    public function topup(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1',
            'method' => 'required|string|in:khalti,stripe',
            'remarks' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        if ($request->method === 'khalti' && $user->is_tourist) {
            return apiResponse(false, 'Khalti is only available for local users. Please use Stripe.', null, 400);
        }

        if ($request->method === 'stripe' && !$user->is_tourist) {
            return apiResponse(false, 'Stripe is only available for tourists. Please use Khalti.', null, 400);
        }

        if ($request->method === 'khalti') {
            return $this->initiateKhalti($user, $request->amount, $request->remarks);
        } else {
            return $this->initiateStripe($user, $request->amount, $request->remarks);
        }
    }

    private function initiateKhalti($user, $amount, $remarks)
    {
        $amountInPaisa = $amount * 100;
        $purchaseOrderNo = 'TRANS_' . time() . '_' . $user->id;

        $response = \Illuminate\Support\Facades\Http::withHeaders([
            'Authorization' => 'Key ' . config('services.khalti.secret_key'),
            'Content-Type' => 'application/json',
        ])->post('https://a.khalti.com/api/v2/epayment/initiate/', [
            'return_url' => route('khalti.verify'),
            'website_url' => config('app.url'),
            'amount' => $amountInPaisa,
            'purchase_order_id' => $purchaseOrderNo,
            'purchase_order_name' => 'Balance Topup',
            'customer_info' => [
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            BalanceIn::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => 'khalti',
                'remarks' => $remarks ?? 'Khalti Topup Initiation',
                'transaction_id' => $data['pidx'],
                'status' => 'pending',
                'gateway_name' => 'khalti',
                'payload' => $data,
            ]);

            return apiResponse(true, 'Khalti payment initiated.', [
                'payment_url' => $data['payment_url'],
                'pidx' => $data['pidx']
            ]);
        }

        return apiResponse(false, 'Failed to initiate Khalti payment.', null, 500);
    }

    private function initiateStripe($user, $amount, $remarks)
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'usd',
                    'product_data' => [
                        'name' => 'Hitee Balance Topup',
                    ],
                    'unit_amount' => $amount * 100,
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => route('stripe.verify') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('app.url'), // Frontend URL ideally
            'client_reference_id' => $user->id,
            'customer_email' => $user->email,
        ]);

        BalanceIn::create([
            'user_id' => $user->id,
            'amount' => $amount, 
            'type' => 'stripe',
            'remarks' => $remarks ?? 'Stripe Topup Initiation',
            'transaction_id' => $session->id,
            'status' => 'pending',
            'gateway_name' => 'stripe',
            'payload' => $session->toArray(),
        ]);

        return apiResponse(true, 'Stripe payment initiated.', [
            'payment_url' => $session->url,
            'session_id' => $session->id
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
