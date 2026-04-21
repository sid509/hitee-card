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
            'amount' => 'required|numeric|min:1',
            'type' => 'required|string|in:manual,cashback,penalty_reversal',
            'remarks' => 'nullable|string|max:255',
        ]);

        if (!auth()->user()->hasRole('super-admin')) {
            return back()->with('error', 'Unauthorized action.');
        }

        BalanceIn::create([
            'user_id' => $request->user_id,
            'amount' => $request->amount,
            'type' => $request->type,
            'remarks' => $request->remarks,
            'created_by' => auth()->id(),
            'status' => 'completed',
        ]);

        logActivity('balance_addition', 'Balance added manually', [
            'amount' => $request->amount,
            'target_user_id' => $request->user_id,
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
            'card_id' => 'required|exists:cards,id',
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
     * Merchant withdrawal via Khalti (Simulated for now, as Khalti Load/Payout API requires specific credentials).
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
            'remarks' => $request->remarks,
        ]);

        // In a real scenario, we would call Khalti Payout/Load API here
        // For simulation, we'll just mark it as completed
        $withdrawal->update([
            'status' => 'completed',
            'transaction_id' => 'KHLT_' . time() . '_' . $withdrawal->id,
        ]);

        logActivity('merchant_withdrawal', 'Merchant withdrawal completed', [
            'amount' => $request->amount,
            'transaction_id' => $withdrawal->transaction_id,
            'remarks' => $request->remarks
        ]);

        return back()->with('success', 'Withdrawal processed successfully to your Khalti wallet.');
    }

    /**
     * Get merchant income transactions.
     */
    public function merchantTransactions(Request $request)
    {
        $user = auth()->user();
        $merchantId = $user->id;
        
        if ($request->ajax()) {
            $query = MerchantIncome::with('transaction.user');

            // If super admin and a specific asset is requested, don't limit by current user id
            // This allows admin to see logs for buses/parkings they don't own
            if ($user->hasRole('super-admin') && $request->filled('reference_id') && $request->filled('reference_type')) {
                // No merchant_id filter needed for admin viewing specific asset
            } else {
                $query->where('merchant_id', $merchantId);
            }

            if ($request->filled('reference_id')) {
                $query->where('reference_id', $request->get('reference_id'));
            }

            if ($request->filled('reference_type')) {
                $query->where('reference_type', $request->get('reference_type'));
            }

            return DataTables::of($query->select(['merchant_incomes.*']))
                ->addIndexColumn()
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('customer', function($row){
                    return $row->transaction->user->name ?? 'N/A';
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
        $merchantId = auth()->id();
        
        if ($request->ajax()) {
            $data = MerchantWithdrawal::where('merchant_id', $merchantId)
                ->select(['merchant_withdrawals.*']);

            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('created_at', function($row){
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
            $queryIn = BalanceIn::where('status', 'completed')->select(['id', 'user_id', 'amount', 'type', 'remarks', 'created_at']);
            $queryOut = BalanceOut::select(['id', 'user_id', 'amount', 'type', 'remarks', 'created_at', 'merchant_id', 'reference_id', 'reference_type']);

            if ($userId !== 'all') {
                $queryIn->where('user_id', $userId);
                $queryOut->where('user_id', $userId);
            }

            // Apply Filters
            if ($request->filled('type')) {
                $type = $request->get('type');
                if ($type == 'in') {
                    $queryOut->whereRaw('1=0'); // Exclude outs
                } elseif ($type == 'out') {
                    $queryIn->whereRaw('1=0'); // Exclude ins
                }
            }

            if ($request->filled('activity')) {
                $activity = $request->get('activity');
                $queryIn->where('type', $activity);
                $queryOut->where('type', $activity);
            }

            $ins = $queryIn->get()->map(function($item) {
                $item->log_type = 'in';
                return $item;
            });

            $outs = $queryOut->get()->map(function($item) {
                $item->log_type = 'out';
                return $item;
            });

            $logs = $ins->concat($outs)->sortByDesc('created_at');

            return DataTables::of($logs)
                ->addIndexColumn()
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('direction', function($row){
                    $class = $row->log_type == 'in' ? 'success' : 'danger';
                    return '<span class="badge bg-label-'.$class.'">'.strtoupper($row->log_type).'</span>';
                })
                ->addColumn('customer', function($row) {
                    $user = \App\Models\User::find($row->user_id);
                    return $user ? $user->name : 'N/A';
                })
                ->editColumn('type', function($row){
                    $type = str_replace('_', ' ', ucfirst($row->type));
                    if ($row->log_type === 'out' && property_exists($row, 'reference_id') && $row->reference_id) {
                        $refName = $row->reference_type === 'App\Models\Bus' ? 'Bus' : 'Parking';
                        return $type . " (" . $refName . ")";
                    }
                    return $type;
                })
                ->editColumn('amount', function($row){
                    $prefix = $row->log_type == 'in' ? '+' : '-';
                    $color = $row->log_type == 'in' ? 'success' : 'danger';
                    return '<span class="text-'.$color.' fw-medium">'.$prefix.' Rs. '.number_format($row->amount, 2).'</span>';
                })
                ->rawColumns(['direction', 'amount', 'customer'])
                ->make(true);
        }

        return view('modules.transactions.index', compact('userId'));
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
}
