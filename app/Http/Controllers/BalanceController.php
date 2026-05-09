<?php

namespace App\Http\Controllers;

use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\MerchantIncome;
use App\Models\MerchantWithdrawal;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Yajra\DataTables\Facades\DataTables;

class BalanceController extends Controller
{
    /**
     * Manual balance addition by Super Admin.
     */
    public function manualAdd(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'card_id' => 'nullable|exists:cards,id',
            'amount' => 'required|numeric|min:1',
            'type' => 'required|string|in:manual,cashback,penalty_reversal',
            'remarks' => 'nullable|string|max:255',
        ]);

        if (!auth()->user()->hasRole('super-admin')) {
            return back()->with('error', 'Unauthorized action.');
        }

        BalanceIn::create([
            'user_id' => $request->user_id,
            'card_id' => $request->card_id,
            'amount' => $request->amount,
            'type' => $request->type,
            'remarks' => $request->remarks,
            'created_by' => auth()->id(),
            'status' => 'completed',
        ]);

        logActivity('balance_addition', 'Balance added manually', [
            'amount' => $request->amount,
            'target_user_id' => $request->user_id,
            'card_id' => $request->card_id,
            'type' => $request->type,
            'remarks' => $request->remarks
        ]);

        return back()->with('success', 'Funds loaded successfully.');
    }

    /**
     * Deduct balance (for penalty or manual deduction).
     */
    public function manualDeduct(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'card_id' => 'nullable|exists:cards,id',
            'amount' => 'required|numeric|min:1',
            'type' => 'required|string|in:fare_deduction,parking,penalty,manual_deduction',
            'merchant_id' => 'nullable|exists:users,id',
            'reference_id' => 'nullable|numeric',
            'remarks' => 'nullable|string|max:255',
        ]);

        if (!auth()->user()->hasRole('super-admin')) {
            return back()->with('error', 'Unauthorized action.');
        }

        $user = User::find($request->user_id);
        if ($user->balance() < $request->amount) {
            return back()->with('error', 'Insufficient balance.');
        }

        DB::transaction(function () use ($request) {
            $balanceOut = BalanceOut::create([
                'user_id' => $request->user_id,
                'card_id' => $request->card_id,
                'merchant_id' => $request->merchant_id,
                'amount' => $request->amount,
                'type' => $request->type,
                'remarks' => $request->remarks,
                'reference_id' => $request->reference_id,
                'reference_type' => in_array($request->type, ['fare_deduction', 'parking']) ? ($request->type === 'fare_deduction' ? 'App\Models\Bus' : 'App\Models\Parking') : null,
                'created_by' => auth()->id(),
            ]);

            // If a merchant is involved, record their income
            if ($request->merchant_id && in_array($request->type, ['fare_deduction', 'parking'])) {
                MerchantIncome::create([
                    'merchant_id' => $request->merchant_id,
                    'balance_out_id' => $balanceOut->id,
                    'reference_id' => $request->reference_id,
                    'reference_type' => $balanceOut->reference_type,
                    'amount' => $request->amount,
                    'type' => $request->type === 'fare_deduction' ? 'fare' : 'parking',
                ]);
            }

            logActivity('balance_deduction', 'Balance deducted manually', [
                'amount' => $request->amount,
                'target_user_id' => $request->user_id,
                'type' => $request->type,
                'remarks' => $request->remarks,
                'merchant_id' => $request->merchant_id
            ]);
        });

        return back()->with('success', 'Transaction processed successfully.');
    }

    /**
     * Merchant withdrawal via Khalti.
     */
    public function merchantWithdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:100',
            'remarks' => 'nullable|string|max:255',
        ]);

        $merchant = auth()->user();
        if ($merchant->merchantBalance() < $request->amount) {
            return back()->with('error', 'Insufficient income balance.');
        }

        // Create withdrawal record
        $withdrawal = MerchantWithdrawal::create([
            'merchant_id' => $merchant->id,
            'amount' => $request->amount,
            'status' => 'pending',
            'gateway_name' => 'khalti',
            'remarks' => $request->remarks,
        ]);

        // In a real scenario, we would call Khalti Payout/Load API here
        // Simulation of Khalti response
        $simulatedPayload = [
            'transaction_id' => 'KHLT_' . time() . '_' . $withdrawal->id,
            'status' => 'success',
            'payout_method' => 'wallet',
            'amount' => $request->amount * 100, // paisa
        ];

        $withdrawal->update([
            'status' => 'completed',
            'transaction_id' => $simulatedPayload['transaction_id'],
            'payload' => $simulatedPayload,
        ]);

        logActivity('merchant_withdrawal', 'Merchant withdrawal completed', [
            'amount' => $request->amount,
            'transaction_id' => $withdrawal->transaction_id,
            'remarks' => $request->remarks,
            'gateway' => 'khalti'
        ]);

        return back()->with('success', 'Withdrawal processed successfully to your Khalti wallet.');
    }

    /**
     * Get merchant income transactions.
     */
    public function merchantTransactions(Request $request)
    {
        $user = auth()->user();
        $merchantIds = array_merge([$user->id], $user->merchants->pluck('id')->toArray());
        
        if ($request->ajax()) {
            $query = MerchantIncome::with(['transaction.user', 'transaction.card']);

            // If super admin and a specific asset is requested, don't limit by authorized ids
            if ($user->hasRole('super-admin') && $request->filled('reference_id') && $request->filled('reference_type')) {
                // No merchant_id filter needed for admin viewing specific asset
            } else {
                $query->whereIn('merchant_id', $merchantIds);
            }

            if ($request->filled('reference_id')) {
                $query->where('reference_id', $request->get('reference_id'));
            }

            if ($request->filled('reference_type')) {
                $query->where('reference_type', $request->get('reference_type'));
            }

            return DataTables::of($query->select(['merchant_incomes.*'])->orderBy('created_at', 'desc'))
                ->addIndexColumn()
                ->addColumn('display_date', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('customer', function($row){
                    if ($row->transaction->user) {
                        return $row->transaction->user->name;
                    }
                    return 'N/A';
                })
                ->addColumn('card_number', function($row){
                    if ($row->transaction->card) {
                        return $row->transaction->card->card_number;
                    }
                    return 'N/A';
                })
                ->editColumn('type', function($row){
                    return ucfirst($row->type);
                })
                ->editColumn('amount', function($row){
                    return '<span class="text-success fw-medium">+ Rs. '.number_format($row->amount, 2).'</span>';
                })
                ->rawColumns(['amount'])
                ->make(true);
        }

        return view('modules.transactions.merchant_index');
    }

    /**
     * Get merchant withdrawal history.
     */
    public function merchantWithdrawals(Request $request)
    {
        $user = auth()->user();
        $merchantIds = array_merge([$user->id], $user->merchants->pluck('id')->toArray());
        
        if ($request->ajax()) {
            $data = MerchantWithdrawal::whereIn('merchant_id', $merchantIds)
                ->select(['merchant_withdrawals.*'])
                ->orderBy('created_at', 'desc');

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('display_date', function($row){
                    return formatDate($row->created_at);
                })
                ->editColumn('status', function($row){
                    $class = $row->status == 'completed' ? 'success' : ($row->status == 'pending' ? 'warning' : 'danger');
                    return '<span class="badge bg-label-'.$class.'">'.ucfirst($row->status).'</span>';
                })
                ->editColumn('amount', function($row){
                    return '<span class="text-danger fw-medium">- Rs. '.number_format($row->amount, 2).'</span>';
                })
                ->rawColumns(['status', 'amount'])
                ->make(true);
        }

        return view('modules.transactions.merchant_withdrawals');
    }

    /**
     * Get balance logs for the current user or a specific user.
     */
    public function logs(Request $request, $userId = null)
    {
        $userId = $request->get('user_id', $userId);

        // If super-admin and no userId provided, show all
        if (!$userId && auth()->user()->hasRole('super-admin')) {
            $userId = 'all';
        } else {
            $userId = $userId ?? auth()->id();
        }
        
        // If requesting another user's logs, must be super-admin
        if ($userId !== 'all' && $userId != auth()->id() && !auth()->user()->hasRole('super-admin')) {
            abort(403);
        }

        if ($request->ajax()) {
            $queryIn = BalanceIn::with(['user', 'card'])->where('status', 'completed')->latest();
            $queryOut = BalanceOut::with(['user', 'card', 'merchant'])->latest();

            if ($userId !== 'all') {
                $targetUser = User::find($userId);
                $userCardIds = $targetUser ? $targetUser->cards()->withTrashed()->pluck('id')->toArray() : [];
                
                $queryIn->where(function($q) use ($userId, $userCardIds) {
                    $q->where('user_id', $userId);
                    if (!empty($userCardIds)) {
                        $q->orWhereIn('card_id', $userCardIds);
                    }
                });
                $queryOut->where(function($q) use ($userId, $userCardIds) {
                    $q->where('user_id', $userId);
                    if (!empty($userCardIds)) {
                        $q->orWhereIn('card_id', $userCardIds);
                    }
                });
            }

            // Apply filters
            if ($request->filled('activity')) {
                $activity = $request->activity;
                $queryIn->where('type', $activity);
                $queryOut->where('type', $activity);
            }

            $type = $request->get('type');
            $ins = collect();
            $outs = collect();

            if (!$type || $type === 'in') {
                $ins = $queryIn->get();
            }

            if (!$type || $type === 'out') {
                $outs = $queryOut->get();
            }

            $allLogs = [];

            foreach ($ins as $item) {
                $allLogs[] = [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'card_id' => $item->card_id,
                    'card_number' => $item->card ? $item->card->card_number : 'N/A',
                    'customer' => $item->user ? $item->user->name : ($item->card ? 'Card: ' . $item->card->card_number : 'N/A'),
                    'amount' => (float)$item->amount,
                    'type' => $item->type,
                    'log_type' => 'in',
                    'remarks' => $item->remarks,
                    'created_at' => $item->created_at->toDateTimeString(),
                    'reference_id' => null,
                    'reference_type' => null
                ];
            }

            foreach ($outs as $item) {
                $allLogs[] = [
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'card_id' => $item->card_id,
                    'card_number' => $item->card ? $item->card->card_number : 'N/A',
                    'customer' => $item->user ? $item->user->name : ($item->card ? 'Card: ' . $item->card->card_number : 'N/A'),
                    'amount' => (float)$item->amount,
                    'type' => $item->type,
                    'log_type' => 'out',
                    'remarks' => $item->remarks,
                    'created_at' => $item->created_at->toDateTimeString(),
                    'reference_id' => $item->reference_id,
                    'reference_type' => $item->reference_type
                ];
            }

            // Sort by created_at desc
            usort($allLogs, function($a, $b) {
                return \Carbon\Carbon::parse($b['created_at'])->timestamp <=> \Carbon\Carbon::parse($a['created_at'])->timestamp;
            });

            return DataTables::of(collect($allLogs))
                ->addIndexColumn()
                ->addColumn('display_date', function($row){
                    return formatDate($row['created_at']);
                })
                ->addColumn('direction', function($row){
                    $class = $row['log_type'] == 'in' ? 'success' : 'danger';
                    return '<span class="badge bg-label-'.$class.'">'.strtoupper($row['log_type']).'</span>';
                })
                ->editColumn('type', function($row){
                    $type = str_replace('_', ' ', ucfirst($row['type']));
                    if ($row['log_type'] === 'out' && $row['reference_id']) {
                        $refName = (strpos((string)$row['reference_type'], 'Bus') !== false) ? 'Bus' : 'Parking';
                        return $type . " (" . $refName . ")";
                    }
                    return $type;
                })
                ->addColumn('card_info', function($row){
                    if ($row['card_number'] === 'N/A') return '<span class="text-muted">N/A</span>';
                    return '<span class="fw-medium">'.$row['card_number'].'</span>';
                })
                ->editColumn('amount', function($row){
                    $prefix = $row['log_type'] == 'in' ? '+' : '-';
                    $color = $row['log_type'] == 'in' ? 'success' : 'danger';
                    return '<span class="text-'.$color.' fw-medium">'.$prefix.' Rs. '.number_format($row['amount'], 2).'</span>';
                })
                ->rawColumns(['direction', 'amount', 'card_info'])
                ->make(true);
        }

        $users = User::all(['id', 'name', 'email']);

        return view('modules.transactions.index', compact('userId', 'users'));
    }

    /**
     * Khalti payment initiation.
     */
    public function khaltiPayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10', // Khalti min is usually 10 NPR (1000 paisa)
        ]);

        $user = auth()->user();

        if ($user->is_tourist) {
            return back()->with('error', 'Khalti is only available for local users. Please use Stripe.');
        }

        $amountInPaisa = $request->amount * 100;
        $purchaseOrderNo = 'TRANS_' . time() . '_' . $user->id;

        $response = Http::withHeaders([
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
            
            // Create a pending balance in record
            BalanceIn::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'type' => 'khalti',
                'remarks' => 'Khalti Topup Initiation',
                'transaction_id' => $data['pidx'],
                'status' => 'pending',
                'gateway_name' => 'khalti',
                'payload' => $data,
            ]);

            return redirect($data['payment_url']);
        }

        return back()->with('error', 'Failed to initiate Khalti payment. ' . $response->body());
    }

    /**
     * Khalti payment verification.
     */
    public function khaltiVerify(Request $request)
    {
        $pidx = $request->pidx;
        
        $response = Http::withHeaders([
            'Authorization' => 'Key ' . config('services.khalti.secret_key'),
            'Content-Type' => 'application/json',
        ])->post('https://a.khalti.com/api/v2/epayment/lookup/', [
            'pidx' => $pidx,
        ]);

        if ($response->successful()) {
            $data = $response->json();
            
            if ($data['status'] === 'Completed') {
                $balanceIn = BalanceIn::where('transaction_id', $pidx)->first();
                if ($balanceIn && $balanceIn->status !== 'completed') {
                    $balanceIn->update([
                        'status' => 'completed',
                        'remarks' => 'Khalti Topup Successful. TXN ID: ' . ($data['transaction_id'] ?? $pidx),
                        'payload' => array_merge($balanceIn->payload ?? [], $data),
                    ]);

                    logActivity('balance_topup', 'Balance topped up via Khalti', [
                        'amount' => $balanceIn->amount,
                        'transaction_id' => $data['transaction_id'] ?? $pidx,
                        'gateway' => 'khalti'
                    ]);

                    return redirect()->route('dashboard')->with('success', 'Balance topped up successfully!');
                }
            }
        }

        return redirect()->route('dashboard')->with('error', 'Payment verification failed or was cancelled.');
    }

    /**
     * Stripe payment initiation.
     */
    public function stripePayment(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1', 
        ]);

        $user = auth()->user();

        if (!$user->hasRole('customers')) {
            return back()->with('error', 'Only customers can topup their balance.');
        }

        if (!$user->is_tourist) {
            return back()->with('error', 'Stripe is only available for tourists. Please use Khalti.');
        }

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));

        $session = \Stripe\Checkout\Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => config('services.stripe.currency', 'usd'), // Usually USD for international tourists
                    'product_data' => [
                        'name' => 'Hitee Balance Topup',
                    ],
                    'unit_amount' => $request->amount * 100, // Amount in cents
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'billing_address_collection' => 'required',
            'payment_intent_data' => [
                'description' => 'Hitee Platform Balance Topup - User ID: ' . $user->id,
                'metadata' => [
                    'user_id' => $user->id,
                    'type' => 'balance_topup'
                ],
                'shipping' => [
                    'name' => $user->name,
                    'address' => [
                        'line1' => 'International Customer',
                        'city' => 'International',
                        'state' => 'International',
                        'postal_code' => '00000',
                        'country' => 'US', 
                    ],
                ],
            ],
            'success_url' => route('stripe.verify') . '?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => route('dashboard'),
            'client_reference_id' => $user->id,
            'customer_email' => $user->email,
        ]);

        // Create a pending balance in record
        BalanceIn::create([
            'user_id' => $user->id,
            'amount' => $request->amount, // We'll treat 1 USD = 1 Point for simplicity, or add conversion logic
            'type' => 'stripe',
            'remarks' => 'Stripe Topup Initiation',
            'transaction_id' => $session->id,
            'status' => 'pending',
            'gateway_name' => 'stripe',
            'payload' => $session->toArray(),
        ]);

        return redirect($session->url);
    }

    /**
     * Stripe payment verification.
     */
    public function stripeVerify(Request $request)
    {
        $sessionId = $request->session_id;

        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
        $session = \Stripe\Checkout\Session::retrieve($sessionId);

        if ($session->payment_status === 'paid') {
            $balanceIn = BalanceIn::where('transaction_id', $sessionId)->first();
            if ($balanceIn && $balanceIn->status !== 'completed') {
                $balanceIn->update([
                    'status' => 'completed',
                    'remarks' => 'Stripe Topup Successful. Session ID: ' . $sessionId,
                    'payload' => array_merge($balanceIn->payload ?? [], $session->toArray()),
                ]);

                logActivity('balance_topup', 'Balance topped up via Stripe', [
                    'amount' => $balanceIn->amount,
                    'transaction_id' => $sessionId,
                    'gateway' => 'stripe'
                ]);

                return redirect()->route('dashboard')->with('success', 'Balance topped up successfully!');
            }
        }

        return redirect()->route('dashboard')->with('error', 'Stripe payment verification failed.');
    }
}
