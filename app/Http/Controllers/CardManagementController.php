<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;

class CardManagementController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = DB::table('cm_cards')
                ->leftJoin('cm_customers', 'cm_cards.customer_id', '=', 'cm_customers.id')
                ->select(
                    'cm_cards.id',
                    'cm_cards.uid',
                    'cm_cards.card_number',
                    'cm_cards.status',
                    'cm_cards.environment',
                    'cm_cards.key_profile_version',
                    'cm_cards.installed_key_profile_version',
                    'cm_cards.production_eligible',
                    'cm_cards.issued_at',
                    'cm_cards.activated_at',
                    'cm_cards.blocked_at',
                    'cm_cards.created_at',
                    'cm_customers.full_name as customer_name',
                )
                ->latest('cm_cards.created_at');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('status_badge', function ($row) {
                    $colors = [
                        'REGISTERED' => 'bg-label-secondary',
                        'INITIALIZED' => 'bg-label-info',
                        'ISSUED' => 'bg-label-primary',
                        'ACTIVE' => 'bg-label-success',
                        'BLOCKED' => 'bg-label-danger',
                        'REPLACED' => 'bg-label-warning',
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
                    return '<a href="'.route('card-management.show', $row->id).'" class="btn btn-sm btn-outline-primary"><i class="bx bx-show me-1"></i> View</a>';
                })
                ->rawColumns(['status_badge', 'env_badge', 'profile_info', 'action'])
                ->make(true);
        }

        $stats = [
            'total' => DB::table('cm_cards')->count(),
            'active' => DB::table('cm_cards')->where('status', 'ACTIVE')->count(),
            'issued' => DB::table('cm_cards')->where('status', 'ISSUED')->count(),
            'registered' => DB::table('cm_cards')->where('status', 'REGISTERED')->count(),
            'blocked' => DB::table('cm_cards')->where('status', 'BLOCKED')->count(),
            'initialized' => DB::table('cm_cards')->where('status', 'INITIALIZED')->count(),
            'production' => DB::table('cm_cards')->where('environment', 'PRODUCTION')->count(),
            'lab' => DB::table('cm_cards')->where('environment', 'LAB')->count(),
        ];

        return view('modules.card-management.index', compact('stats'));
    }

    public function show(string $id)
    {
        $card = DB::table('cm_cards')->where('id', $id)->first();
        if (! $card) {
            abort(404, 'Card not found');
        }

        $customer = $card->customer_id
            ? DB::table('cm_customers')->where('id', $card->customer_id)->first()
            : null;

        $issuanceOps = DB::table('cm_card_issuance_operations')
            ->where('card_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $initOps = DB::table('cm_card_initialization_operations')
            ->where('card_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $lifecycleEvents = DB::table('cm_card_lifecycle_events')
            ->where('card_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $recharges = DB::table('cm_wallet_recharge_operations')
            ->where('card_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $debits = DB::table('cm_wallet_debit_operations')
            ->where('card_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $reversals = DB::table('cm_wallet_recharge_reversal_operations')
            ->where('card_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        // Replacement operations reference cards via old_card_id/new_card_id —
        // the table has no card_id column.
        $replacementOps = DB::table('cm_card_replacement_operations')
            ->where('old_card_id', $id)
            ->orWhere('new_card_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        $validatorTrips = DB::table('cm_validator_trips')
            ->where('card_uid', $card->uid)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('modules.card-management.show', compact(
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
        ));
    }

    public function customers(Request $request)
    {
        if ($request->ajax()) {
            $query = DB::table('cm_customers')->latest('created_at');

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('created_at', function ($row) {
                    return Carbon::parse($row->created_at)->format('M d, Y H:i');
                })
                ->addColumn('cards_count', function ($row) {
                    return DB::table('cm_cards')->where('customer_id', $row->id)->count();
                })
                ->addColumn('action', function ($row) {
                    return '<a href="'.route('card-management.customers.show', $row->id).'" class="btn btn-sm btn-outline-primary"><i class="bx bx-show me-1"></i> View</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('modules.card-management.customers');
    }

    public function showCustomer(string $id)
    {
        $customer = DB::table('cm_customers')->where('id', $id)->first();
        if (! $customer) {
            abort(404, 'Customer not found');
        }

        $cards = DB::table('cm_cards')->where('customer_id', $id)->orderBy('created_at', 'desc')->get();

        return view('modules.card-management.customer-show', compact('customer', 'cards'));
    }

    public function validators(Request $request)
    {
        if ($request->ajax()) {
            $query = DB::table('cm_validator_devices')->latest('created_at');

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
                    return DB::table('cm_validator_trips')->where('device_id', $row->id)->count();
                })
                ->addColumn('action', function ($row) {
                    return '<a href="'.route('card-management.validators.qr', $row->id).'" '
                        .'class="btn btn-sm btn-outline-primary" title="View provisioning QR code">'
                        .'<i class="bx bx-qr me-1"></i>QR</a>';
                })
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return view('modules.card-management.validators');
    }

    /**
     * Show a single validator device with its provisioning QR code.
     */
    public function showValidator(string $id)
    {
        $device = DB::table('cm_validator_devices')->where('id', $id)->first();
        if (! $device) {
            abort(404, 'Validator device not found');
        }

        $qr = $this->buildValidatorQrSvg($device);
        $payload = $this->buildValidatorQrPayload($device);

        $tripsCount = DB::table('cm_validator_trips')->where('device_id', $device->id)->count();

        return view('modules.card-management.validator-qr', compact('device', 'qr', 'payload', 'tripsCount'));
    }

    /**
     * Return the raw provisioning QR payload as JSON (machine-readable endpoint).
     */
    public function validatorQrPayload(string $id)
    {
        $device = DB::table('cm_validator_devices')->where('id', $id)->first();
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
            $query = DB::table('cm_settlement_batches')->latest('created_at');

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
                    return DB::table('cm_settlement_entries')->where('batch_id', $row->id)->count();
                })
                ->rawColumns(['status_badge'])
                ->make(true);
        }

        return view('modules.card-management.settlement');
    }
}
