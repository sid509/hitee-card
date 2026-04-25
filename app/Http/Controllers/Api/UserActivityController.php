<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BalanceIn;
use App\Models\BalanceOut;
use App\Models\Ride;
use App\Models\Tap;
use Illuminate\Http\Request;

class UserActivityController extends Controller
{
    /**
     * GET /api/transactions
     *
     * Returns a unified, paginated ledger of the authenticated user's transactions.
     *
     * @queryParam type string (optional) Filter by type: topup or deduction. Example: topup
     * @queryParam category string (optional) Filter by category: topup, khalti, parking, travel. Example: parking
     * @queryParam status string (optional) Filter by status (topups only): pending, completed, failed. Example: completed
     * @queryParam date_from date (optional) Filter from date (Y-m-d). Example: 2026-01-01
     * @queryParam date_to date (optional) Filter to date (Y-m-d). Example: 2026-12-31
     * @queryParam per_page integer (optional) Results per page. Defaults to 15. Example: 20
     * @queryParam page integer (optional) Page number. Example: 1
     */
    public function transactions(Request $request)
    {
        $request->validate([
            'type'      => 'nullable|string|in:topup,deduction',
            'category'  => 'nullable|string|in:topup,khalti,parking,travel',
            'status'    => 'nullable|string|in:pending,completed,failed',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date',
            'per_page'  => 'nullable|integer|min:1|max:100',
            'page'      => 'nullable|integer|min:1',
        ]);

        $user     = $request->user();
        $type     = $request->get('type');      // 'topup' | 'deduction' | null (both)
        $category = $request->get('category'); // 'topup', 'khalti', 'parking', 'travel'
        $perPage  = (int) $request->get('per_page', 15);
        $perPage  = max(1, min($perPage, 100)); // clamp 1–100

        // Build both queries
        $inQuery  = $user->balanceIns()->getQuery();
        $outQuery = $user->balanceOuts()->getQuery();

        // Category filtering
        if ($category) {
            if ($category === 'topup') {
                $inQuery->where('type', 'manual');
                $outQuery->whereRaw('1=0'); // Force empty
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

        // Date range
        if ($request->filled('date_from')) {
            $inQuery->whereDate('created_at', '>=', $request->date_from);
            $outQuery->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $inQuery->whereDate('created_at', '<=', $request->date_to);
            $outQuery->whereDate('created_at', '<=', $request->date_to);
        }

        // Status (only relevant for topups)
        if ($request->filled('status') && $type !== 'deduction') {
            $inQuery->where('status', $request->status);
        }

        // Fetch and merge
        $ins  = ($type === 'deduction') ? collect() : $inQuery->latest()->get()->map(fn($tx) => $this->mapTopup($tx));
        $outs = ($type === 'topup') ? collect() : $outQuery->latest()->get()->map(fn($tx) => $this->mapDeduction($tx));

        $merged  = $ins->concat($outs)->sortByDesc('date')->values();
        $page    = (int) $request->get('page', 1);
        $total   = $merged->count();
        $items   = $merged->slice(($page - 1) * $perPage, $perPage)->values();

        return apiResponse(true, 'Transactions fetched successfully', $items, 200, [
            'disclaimer' => '1 Rs = 1 Hitee Point (pts). top-up amounts reflect real money paid.',
        ], [
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * GET /api/taps
     *
     * Returns the authenticated user's tap (card scan) logs, paginated.
     *
     * @queryParam type string Filter by tap type: in or out. Example: in
     * @queryParam date_from date Filter from date. Example: 2026-01-01
     * @queryParam date_to date Filter to date. Example: 2026-12-31
     * @queryParam per_page integer Results per page. Defaults to 15. Example: 20
     */
    public function taps(Request $request)
    {
        $user    = $request->user();
        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $query = $user->taps()
            ->with(['card:id,card_number', 'merchant:id,name'])
            ->latest();

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $taps = $query->paginate($perPage);

        $mapped = collect($taps->items())->map(fn($tap) => [
            'id'             => $tap->id,
            'type'           => $tap->type, // 'in' | 'out'
            'location'       => $tap->resolved_location_name,
            'latitude'       => $tap->latitude,
            'longitude'      => $tap->longitude,
            'card_number'    => $tap->card?->card_number,
            'merchant_name'  => $tap->merchant?->name,
            'date'           => $tap->created_at?->toISOString(),
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'Tap logs fetched successfully',
            'meta'    => [],
            'paginate' => [
                'total'        => $taps->total(),
                'per_page'     => $taps->perPage(),
                'current_page' => $taps->currentPage(),
                'last_page'    => $taps->lastPage(),
            ],
            'content' => $mapped,
        ]);
    }

    /**
     * GET /api/rides
     *
     * Returns the authenticated user's ride history, paginated.
     * Fare amounts are in pts.
     *
     * @queryParam status string Filter by status: ongoing, completed. Example: completed
     * @queryParam date_from date Filter from date. Example: 2026-01-01
     * @queryParam date_to date Filter to date. Example: 2026-12-31
     * @queryParam per_page integer Results per page. Defaults to 15. Example: 20
     */
    public function rides(Request $request)
    {
        $user    = $request->user();
        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        $query = $user->rides()
            ->with([
                'card:id,card_number',
                'tapIn:id,resolved_location_name,created_at',
                'tapOut:id,resolved_location_name,created_at',
                'merchant:id,name',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $rides = $query->paginate($perPage);

        $mapped = collect($rides->items())->map(fn($ride) => [
            'id'           => $ride->id,
            'status'       => $ride->status,
            'fare_pts'     => (float) $ride->fare_amount, // pts — not Rs.
            'card_number'  => $ride->card?->card_number,
            'merchant'     => $ride->merchant?->name,
            'tap_in'       => $ride->tapIn ? [
                'location' => $ride->tapIn->resolved_location_name,
                'time'     => $ride->tapIn->created_at?->toISOString(),
            ] : null,
            'tap_out'      => $ride->tapOut ? [
                'location' => $ride->tapOut->resolved_location_name,
                'time'     => $ride->tapOut->created_at?->toISOString(),
            ] : null,
            'started_at'   => $ride->created_at?->toISOString(),
        ]);

        return apiResponse(true, 'Rides fetched successfully', $mapped, 200, [
            'disclaimer' => 'Fare is charged in Hitee Points (pts). 1 Rs = 1 pt.'
        ], [
            'total'        => $rides->total(),
            'per_page'     => $rides->perPage(),
            'current_page' => $rides->currentPage(),
            'last_page'    => $rides->lastPage(),
        ]);
    }

    // ──────────────────────────────────────────────
    // Private mappers
    // ──────────────────────────────────────────────

    private function mapTopup(BalanceIn $tx): array
    {
        return [
            'id'             => $tx->id,
            'direction'      => 'credit',
            'type'           => $tx->type,          // manual, khalti, etc.
            'pts_credited'   => (float) $tx->amount,
            'amount_rs'      => (float) $tx->amount, // Real money → surfaced as Rs. only here
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
            'type'       => $tx->type,           // fare_deduction, parking, etc.
            'pts_spent'  => (float) $tx->amount,
            'remarks'    => $tx->remarks,
            'date'       => $tx->created_at?->toISOString(),
        ];
    }
}
