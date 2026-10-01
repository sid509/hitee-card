<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Models\Card;
use App\Models\Ride;
use App\Models\Tap;
use App\Models\User;
use Carbon\Carbon;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CardController extends Controller
{
    /**
     * Card registry — the system-of-record listing (formerly Card Management).
     */
    public function index(Request $request)
    {
        if (auth()->user()->hasRole('customers') && !$request->ajax()) {
            $card = auth()->user()->cards()->where('is_currently_active', true)->first();
            if ($card) {
                return redirect()->route('cards.show', $card->id);
            }
        }

        if ($request->ajax()) {
            $query = DB::table('cards')
                ->leftJoin('customers', 'cards.customer_id', '=', 'customers.id')
                ->leftJoin('users', 'cards.user_id', '=', 'users.id')
                ->select(
                    'cards.id',
                    'cards.card_uid',
                    'cards.card_number',
                    'cards.status',
                    'cards.environment',
                    'cards.key_profile_version',
                    'cards.installed_key_profile_version',
                    'cards.production_eligible',
                    'cards.is_physical',
                    'cards.is_currently_active',
                    'cards.issued_at',
                    'cards.activated_at',
                    'cards.blocked_at',
                    'cards.created_at',
                    'customers.full_name as customer_name',
                    'users.name as user_name',
                )
                ->latest('cards.created_at');

            if (auth()->user()->hasRole('customers')) {
                $query->where('cards.user_id', auth()->id());
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_badge', function ($row) {
                    $colors = [
                        CardStatus::NEW->value => 'bg-label-dark',
                        CardStatus::REGISTERED->value => 'bg-label-secondary',
                        CardStatus::INITIALIZED->value => 'bg-label-info',
                        CardStatus::ISSUED->value => 'bg-label-primary',
                        CardStatus::ACTIVE->value => 'bg-label-success',
                        CardStatus::INACTIVE->value => 'bg-label-secondary',
                        CardStatus::BLOCKED->value => 'bg-label-danger',
                        CardStatus::REPLACED->value => 'bg-label-warning',
                    ];
                    $class = $colors[$row->status] ?? 'bg-label-secondary';

                    return '<span class="badge '.$class.'">'.e($row->status).'</span>';
                })
                ->addColumn('env_badge', function ($row) {
                    $class = $row->environment === 'PRODUCTION' ? 'bg-label-success' : 'bg-label-warning';

                    return '<span class="badge '.$class.'">'.e($row->environment).'</span>';
                })
                ->addColumn('profile_info', function ($row) {
                    $installed = $row->installed_key_profile_version ?: '—';

                    return '<small>Expected: '.e($row->key_profile_version ?? '—').'<br>Installed: '.e($installed).'</small>';
                })
                ->editColumn('created_at', function ($row) {
                    return Carbon::parse($row->created_at)->format('M d, Y H:i');
                })
                ->editColumn('issued_at', function ($row) {
                    return $row->issued_at ? Carbon::parse($row->issued_at)->format('M d, Y') : '—';
                })
                ->addColumn('action', function ($row) {
                    $actions = '<a href="'.route('cards.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';

                    if (auth()->user()->hasRole('super-admin')) {
                        $isActive = $row->status === CardStatus::ACTIVE->value;
                        $btnClass = $isActive ? 'btn-success' : 'btn-secondary';
                        $btnIcon = $isActive ? 'bx-check-circle' : 'bx-block';
                        $btnTitle = $isActive ? 'Deactivate' : 'Activate';

                        $actions .= '<button type="button" class="btn btn-icon btn-sm '.$btnClass.' me-1 toggle-card-status" data-id="'.$row->id.'" title="'.$btnTitle.'"><i class="bx '.$btnIcon.'"></i></button>';
                        $actions .= '<a href="'.route('cards.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                        $actions .= '<form action="'.route('cards.destroy', $row->id).'" method="POST" style="display:inline-block">'
                            .csrf_field().method_field('DELETE')
                            .'<button type="submit" class="btn btn-icon btn-sm btn-danger delete-card-btn" title="Delete"><i class="bx bx-trash"></i></button></form>';
                    }

                    return $actions;
                })
                ->rawColumns(['status_badge', 'env_badge', 'profile_info', 'action'])
                ->make(true);
        }

        $stats = [
            'total' => DB::table('cards')->count(),
            'active' => DB::table('cards')->where('status', CardStatus::ACTIVE->value)->count(),
            'issued' => DB::table('cards')->where('status', CardStatus::ISSUED->value)->count(),
            'registered' => DB::table('cards')->where('status', CardStatus::REGISTERED->value)->count(),
            'blocked' => DB::table('cards')->where('status', CardStatus::BLOCKED->value)->count(),
            'initialized' => DB::table('cards')->where('status', CardStatus::INITIALIZED->value)->count(),
            'production' => DB::table('cards')->where('environment', 'PRODUCTION')->count(),
            'lab' => DB::table('cards')->where('environment', 'LAB')->count(),
        ];

        return view('modules.cards.index', compact('stats'));
    }

    /**
     * Card detail — lifecycle, operations, wallet ledger, trips, rides.
     */
    public function show(Card $card)
    {
        $this->authorize('view', $card);

        $customer = $card->customer_id
            ? DB::table('customers')->where('id', $card->customer_id)->first()
            : null;

        $issuanceOps = DB::table('card_issuance_operations')
            ->where('card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $initOps = DB::table('card_initialization_operations')
            ->where('card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $lifecycleEvents = DB::table('card_lifecycle_events')
            ->where('card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $recharges = DB::table('wallet_recharge_operations')
            ->where('card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $debits = DB::table('wallet_debit_operations')
            ->where('card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $reversals = DB::table('wallet_recharge_reversal_operations')
            ->where('card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Replacement operations reference cards via old_card_id/new_card_id —
        // the table has no card_id column.
        $replacementOps = DB::table('card_replacement_operations')
            ->where('old_card_id', $card->id)
            ->orWhere('new_card_id', $card->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $validatorTrips = DB::table('validator_trips')
            ->where('card_uid', $card->card_uid)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Legacy wallet/usage context — taps, rides, balance ledger.
        $card->load(['user', 'subscriptionModels.discounts.servicePartner']);
        $recentTaps = Tap::where('card_id', $card->id)->with('reference')->latest()->limit(10)->get();
        $recentRides = Ride::where('card_id', $card->id)->with('reference')->latest()->limit(10)->get();

        $travelCount = $card->rides()->where('status', 'completed')->count();
        $parkingTaps = $card->taps()->where('reference_type', 'App\Models\Parking')->orderBy('created_at', 'asc')->get();

        $totalParkingMinutes = 0;
        $tempInTap = null;
        foreach ($parkingTaps as $tap) {
            if ($tap->type === 'in') {
                $tempInTap = $tap;
            } elseif ($tap->type === 'out' && $tempInTap) {
                $totalParkingMinutes += $tap->created_at->diffInMinutes($tempInTap->created_at);
                $tempInTap = null;
            }
        }

        $ins = \App\Models\BalanceIn::where('card_id', $card->id)->where('status', 'completed')->get()->map(function ($item) {
            $item->log_type = 'in';
            return $item;
        });
        $outs = \App\Models\BalanceOut::where('card_id', $card->id)->get()->map(function ($item) {
            $item->log_type = 'out';
            return $item;
        });
        $balanceLogs = $ins->concat($outs)->sortByDesc('created_at');

        return view('modules.cards.show', compact(
            'card',
            'customer',
            'issuanceOps',
            'initOps',
            'lifecycleEvents',
            'recharges',
            'debits',
            'reversals',
            'replacementOps',
            'validatorTrips',
            'recentTaps',
            'recentRides',
            'balanceLogs',
            'travelCount',
            'totalParkingMinutes',
        ));
    }

    public function customers(Request $request)
    {
        if ($request->ajax()) {
            $query = DB::table('customers')->latest('created_at');

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('created_at', function ($row) {
                    return Carbon::parse($row->created_at)->format('M d, Y H:i');
                })
                ->addColumn('cards_count', function ($row) {
                    return DB::table('cards')->where('customer_id', $row->id)->count();
                })
                ->addColumn('action', function ($row) {
                    return '<a href="'.route('cards.customers.show', $row->id).'" class="btn btn-sm btn-outline-primary"><i class="bx bx-show me-1"></i> View</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('modules.cards.customers');
    }

    public function showCustomer(string $id)
    {
        $customer = DB::table('customers')->where('id', $id)->first();
        if (! $customer) {
            abort(404, 'Customer not found');
        }

        $cards = DB::table('cards')->where('customer_id', $id)->orderBy('created_at', 'desc')->get();

        return view('modules.cards.customer-show', compact('customer', 'cards'));
    }

    public function validators(Request $request)
    {
        if ($request->ajax()) {
            $query = DB::table('validator_devices')->latest('created_at');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_badge', function ($row) {
                    $class = $row->status === 'ACTIVE' ? 'bg-label-success' : 'bg-label-danger';

                    return '<span class="badge '.$class.'">'.e($row->status).'</span>';
                })
                ->editColumn('last_heartbeat_at', function ($row) {
                    return $row->last_heartbeat_at ? Carbon::parse($row->last_heartbeat_at)->diffForHumans() : 'Never';
                })
                ->editColumn('created_at', function ($row) {
                    return Carbon::parse($row->created_at)->format('M d, Y H:i');
                })
                ->addColumn('trips_count', function ($row) {
                    return DB::table('validator_trips')->where('device_id', $row->id)->count();
                })
                ->addColumn('action', function ($row) {
                    return '<a href="'.route('cards.validators.qr', $row->id).'" '
                        .'class="btn btn-sm btn-outline-primary" title="View provisioning QR code">'
                        .'<i class="bx bx-qr me-1"></i>QR</a>';
                })
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return view('modules.cards.validators');
    }

    /**
     * Show a single validator device with its provisioning QR code.
     */
    public function showValidator(string $id)
    {
        $device = DB::table('validator_devices')->where('id', $id)->first();
        if (! $device) {
            abort(404, 'Validator device not found');
        }

        $qr = $this->buildValidatorQrSvg($device);
        $payload = $this->buildValidatorQrPayload($device);

        $tripsCount = DB::table('validator_trips')->where('device_id', $device->id)->count();

        return view('modules.cards.validator-qr', compact('device', 'qr', 'payload', 'tripsCount'));
    }

    /**
     * Return the raw provisioning QR payload as JSON (machine-readable endpoint).
     */
    public function validatorQrPayload(string $id)
    {
        $device = DB::table('validator_devices')->where('id', $id)->first();
        if (! $device) {
            abort(404, 'Validator device not found');
        }

        return response()->json($this->buildValidatorQrPayload($device));
    }

    /**
     * Build the QR payload that the validator's QrDeviceConfig.parse() accepts.
     * Format matches hitee-validator/lib/features/device_setup/qr_scanner_page.dart.
     */
    private function buildValidatorQrPayload(object $device): array
    {
        return [
            'deviceId' => (string) $device->device_id,
            'vehicleId' => (string) ($device->vehicle_id ?? ''),
            'routeId' => (string) ($device->route_id ?? ''),
            'apiUrl' => $this->validatorApiUrl(),
        ];
    }

    private function validatorApiUrl(): string
    {
        $configured = config('card_management.validator.qr_api_url');
        if (! empty($configured)) {
            return rtrim((string) $configured, '/');
        }

        return rtrim(config('app.url'), '/').'/api/v1';
    }

    private function buildValidatorQrSvg(object $device): string
    {
        $payload = json_encode(
            $this->buildValidatorQrPayload($device),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $qrCode = new QrCode(
            data: $payload,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 320,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $result = (new SvgWriter)->write(
            $qrCode,
            null,
            null,
            [SvgWriter::WRITER_OPTION_EXCLUDE_XML_DECLARATION => true],
        );

        return $result->getString();
    }

    public function settlement(Request $request)
    {
        if ($request->ajax()) {
            $query = DB::table('settlement_batches')->latest('created_at');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_badge', function ($row) {
                    $colors = [
                        'OPEN' => 'bg-label-info',
                        'CALCULATED' => 'bg-label-warning',
                        'CLOSED' => 'bg-label-success',
                        'PAID' => 'bg-label-primary',
                    ];
                    $class = $colors[$row->status] ?? 'bg-label-secondary';

                    return '<span class="badge '.$class.'">'.e($row->status).'</span>';
                })
                ->editColumn('settlement_date', function ($row) {
                    return Carbon::parse($row->settlement_date)->format('M d, Y');
                })
                ->editColumn('created_at', function ($row) {
                    return Carbon::parse($row->created_at)->format('M d, Y H:i');
                })
                ->addColumn('entries_count', function ($row) {
                    return DB::table('settlement_entries')->where('batch_id', $row->id)->count();
                })
                ->rawColumns(['status_badge'])
                ->make(true);
        }

        return view('modules.cards.settlement');
    }

    /**
     * Show the form for issuing/registering a card.
     */
    public function create(Request $request)
    {
        $this->authorize('create', Card::class);

        $card = new Card();
        $application = null;

        if ($request->filled('application_id')) {
            $application = \App\Models\CardApplication::find($request->application_id);
            if ($application) {
                $card->user_id = $application->user_id;
            }
        }

        $users = User::whereHas('roles', function ($q) {
            $q->where('slug', 'customers');
        })->get();

        return view('modules.cards.create', compact('card', 'users', 'application'));
    }

    /**
     * Store a manually created card (admin enrollment without a reader).
     */
    public function store(StoreCardRequest $request)
    {
        $this->authorize('create', Card::class);

        $user = User::find($request->user_id);
        if ($user && $user->cards()->exists()) {
            return back()->withInput()->with('error', 'This user already has a card linked to their account.');
        }

        $data = $request->validated();
        $data['status'] = self::mapLegacyStatus($data['status'] ?? null);

        // If this card is being set as active for a user, deactivate their other cards
        if ($request->is_currently_active && $request->user_id) {
            Card::where('user_id', $request->user_id)->update(['is_currently_active' => false]);
        }

        $card = Card::create($data);

        if ($request->has('subscription_models')) {
            $card->subscriptionModels()->sync($request->subscription_models);
        }

        // Process application if exists
        if ($request->filled('application_id')) {
            $application = \App\Models\CardApplication::find($request->application_id);
            if ($application) {
                $application->update([
                    'status' => 'approved',
                    'card_id' => $card->id,
                    'processed_at' => now(),
                    'admin_remarks' => 'Card issued via management dashboard: '.$request->get('remarks', ''),
                ]);

                logActivity('card_application_processed', 'Card application approved and issued', [
                    'application_id' => $application->id,
                    'card_number' => $card->card_number,
                ]);
            }
        }

        return redirect()->route('cards.index')->with('success', 'Card issued successfully.');
    }

    public function edit(Card $card)
    {
        $this->authorize('update', $card);
        $users = User::whereHas('roles', function ($q) {
            $q->where('slug', 'customers');
        })->get();

        return view('modules.cards.edit', compact('card', 'users'));
    }

    public function update(UpdateCardRequest $request, Card $card)
    {
        $this->authorize('update', $card);

        if ($request->user_id && $request->user_id != $card->user_id) {
            $user = User::find($request->user_id);
            if ($user && $user->cards()->where('id', '!=', $card->id)->exists()) {
                return back()->withInput()->with('error', 'The target user already has a card linked.');
            }
        }

        // If this card is being set as active for a user, deactivate their other cards
        if ($request->is_currently_active && $request->user_id) {
            Card::where('user_id', $request->user_id)->where('id', '!=', $card->id)->update(['is_currently_active' => false]);
        }

        $data = $request->validated();
        $data['status'] = self::mapLegacyStatus($data['status'] ?? null);
        unset($data['subscription_models']);

        $card->update($data);

        if ($request->has('subscription_models')) {
            $card->subscriptionModels()->sync($request->subscription_models);
        }

        return redirect()->route('cards.index')->with('success', 'Card updated successfully.');
    }

    public function destroy(Card $card)
    {
        $this->authorize('delete', $card);

        if ($card->rides()->exists() || $card->taps()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete card because it has usage history.');
        }

        $card->delete();

        return redirect()->route('cards.index')->with('success', 'Card deleted successfully.');
    }

    /**
     * Bulk toggle card status (Super Admin only).
     */
    public function bulkToggleStatus(Request $request)
    {
        $this->authorize('create', Card::class);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:cards,id',
            'status' => 'required|in:active,inactive,ACTIVE,INACTIVE',
        ]);

        $status = self::mapLegacyStatus($request->status);
        Card::whereIn('id', $request->ids)->update(['status' => $status]);

        return response()->json([
            'status' => true,
            'message' => count($request->ids).' cards updated to '.strtolower($status).'.',
        ]);
    }

    /**
     * Toggle a single card between ACTIVE and INACTIVE (Super Admin only).
     */
    public function toggleStatus(Card $card)
    {
        $this->authorize('update', $card);

        $newStatus = $card->status === CardStatus::ACTIVE->value
            ? CardStatus::INACTIVE->value
            : CardStatus::ACTIVE->value;
        $card->update(['status' => $newStatus]);

        logActivity('card_status_toggle', "Card {$card->card_number} status changed to {$newStatus}", [
            'card_id' => $card->id,
            'new_status' => $newStatus,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Card is now '.strtolower($newStatus).'.',
            'new_status' => $newStatus,
        ]);
    }

    /**
     * Request a change for the card (Customer only).
     */
    public function requestChange(Request $request, Card $card)
    {
        $this->authorize('requestChange', $card);

        $request->validate([
            'type' => 'required|in:upgrade,enable,disable',
            'message' => 'nullable|string|max:1000',
        ]);

        $typeLabel = 'Unknown';
        if ($request->type === 'upgrade') {
            $typeLabel = 'Card Upgrade';
        } elseif ($request->type === 'enable') {
            $typeLabel = 'Card Activation';
        } elseif ($request->type === 'disable') {
            $typeLabel = 'Card Deactivation';
        }

        \App\Models\SupportRequest::create([
            'user_id' => auth()->id(),
            'subject' => "Card Change Request: {$typeLabel}",
            'message' => "Request for [{$typeLabel}] for Card: {$card->card_number}. ".($request->message ?? ''),
            'status' => 'open',
        ]);

        logActivity('card_request', "User requested card {$request->type}", [
            'card_id' => $card->id,
            'card_number' => $card->card_number,
            'request_type' => $request->type,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Your request has been submitted successfully.',
        ]);
    }

    /**
     * Map legacy lowercase status values from the admin forms onto the
     * card lifecycle vocabulary (NEW/REGISTERED/…/ACTIVE/BLOCKED/…).
     */
    private static function mapLegacyStatus(?string $status): string
    {
        return CardStatus::fromLegacy($status)->value;
    }
}
