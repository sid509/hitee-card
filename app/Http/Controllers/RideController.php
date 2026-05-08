<?php

namespace App\Http\Controllers;

use App\Models\Ride;
use App\Models\Tap;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class RideController extends Controller
{
    /**
     * Display all reconciled rides (Admin/Merchant).
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Ride::with(['user', 'card', 'reference', 'merchant', 'tapIn', 'tapOut'])->latest();

            // Filters
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }
            if ($request->filled('asset_type')) {
                $type = $request->asset_type == 'bus' ? 'App\Models\Bus' : 'App\Models\Parking';
                $query->where('reference_type', $type);
            }
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if (auth()->user()->hasRole('customers')) {
                $query->where('user_id', auth()->id());
            } elseif (auth()->user()->hasRole('merchant')) {
                $query->where('merchant_id', auth()->id());
            } elseif (auth()->user()->hasRole('staff')) {
                $user = auth()->user();
                $merchantIds = $user->merchants->pluck('id');
                $assignedBusIds = $user->assignedBuses->pluck('id');
                $assignedParkingIds = $user->assignedParkings->pluck('id');
                
                $query->where(function($q) use ($merchantIds, $assignedBusIds, $assignedParkingIds) {
                    $q->whereIn('merchant_id', $merchantIds)
                      ->orWhere(function($sq) use ($assignedBusIds) {
                          $sq->where('reference_type', 'App\Models\Bus')->whereIn('reference_id', $assignedBusIds);
                      })->orWhere(function($sq) use ($assignedParkingIds) {
                          $sq->where('reference_type', 'App\Models\Parking')->whereIn('reference_id', $assignedParkingIds);
                      });
                });
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('status', function($row) {
                    $class = $row->status == 'completed' ? 'success' : ($row->status == 'ongoing' ? 'primary' : 'danger');
                    return '<span class="badge bg-label-'.$class.'">'.ucfirst($row->status).'</span>';
                })
                ->addColumn('display_date', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('user_card', function($row) {
                    $userName = $row->user ? $row->user->name : 'N/A';
                    $cardNo = $row->card ? $row->card->card_number : 'No Card';
                    return '<div><span class="fw-medium">'.$userName.'</span><br><small class="text-muted" style="font-size: 0.75rem; font-style: italic;">'.$cardNo.'</small></div>';
                })
                ->addColumn('asset_info', function($row) {
                    if (!$row->reference) return 'N/A';
                    $name = $row->reference_type == 'App\Models\Bus' ? ($row->reference->bus_number ?? $row->reference->name) : $row->reference->name;
                    $type = $row->reference_type == 'App\Models\Bus' ? 'Bus' : 'Parking';
                    $route = $row->reference_type == 'App\Models\Bus' ? 'buses.show' : 'parkings.show';
                    $link = route($route, $row->reference_id);
                    return '<div><a href="'.$link.'" class="fw-medium">'.$name.'</a><br><small class="text-muted" style="font-size: 0.75rem; font-style: italic;">'.$type.'</small></div>';
                })
                ->editColumn('fare_amount', function($row) {
                    return 'Rs. ' . number_format($row->fare_amount, 2);
                })
                ->addColumn('tap_in_time', function($row) {
                    $time = formatDate($row->tapIn?->created_at);
                    if (!$row->tapIn) return '-';
                    $location = ($row->reference_type === 'App\Models\Bus') ? ($row->tapIn->resolved_location_name ?? 'Unknown') : '-';
                    return '<div>'.$time.'<br><small class="text-muted">'.$location.'</small></div>';
                })
                ->addColumn('tap_out_time', function($row) {
                    if (!$row->tapOut) return '---';
                    $time = formatDate($row->tapOut->created_at);
                    $location = ($row->reference_type === 'App\Models\Bus') ? ($row->tapOut->resolved_location_name ?? 'Unknown') : '-';
                    return '<div>'.$time.'<br><small class="text-muted">'.$location.'</small></div>';
                })
                ->addColumn('action', function($row) {
                    if (!$row->tapIn) return '';
                    return '<button class="btn btn-icon btn-sm btn-info view-ride-map" 
                                data-start-lat="'.$row->tapIn->latitude.'" 
                                data-start-lon="'.$row->tapIn->longitude.'"
                                data-start-name="'.($row->tapIn->resolved_location_name ?? 'Unknown').'"
                                data-end-lat="'.($row->tapOut ? $row->tapOut->latitude : '').'"
                                data-end-lon="'.($row->tapOut ? $row->tapOut->longitude : '').'"
                                data-end-name="'.($row->tapOut ? $row->tapOut->resolved_location_name : 'Journey Ongoing').'">
                                <i class="bx bx-map"></i>
                            </button>';
                })
                ->rawColumns(['status', 'action', 'asset_info', 'user_card', 'tap_in_time', 'tap_out_time'])
                ->make(true);
        }

        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        return view('modules.rides.index', compact('merchants'));
    }

    /**
     * Display raw tap ledger (Admin/Merchant).
     */
    public function tapLedger(Request $request)
    {
        if ($request->ajax()) {
            $query = Tap::with(['user', 'card', 'reference'])->latest();

            // Filters
            if ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->filled('merchant_id')) {
                $query->where('merchant_id', $request->merchant_id);
            }
            if ($request->filled('asset_type')) {
                $type = $request->asset_type == 'bus' ? 'App\Models\Bus' : 'App\Models\Parking';
                $query->where('reference_type', $type);
            }
            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if (auth()->user()->hasRole('customers')) {
                $query->where('user_id', auth()->id());
            } elseif (auth()->user()->hasRole('merchant')) {
                $query->where('merchant_id', auth()->id());
            } elseif (auth()->user()->hasRole('staff')) {
                $user = auth()->user();
                $merchantIds = $user->merchants->pluck('id');
                $assignedBusIds = $user->assignedBuses->pluck('id');
                $assignedParkingIds = $user->assignedParkings->pluck('id');
                
                $query->where(function($q) use ($merchantIds, $assignedBusIds, $assignedParkingIds) {
                    $q->whereIn('merchant_id', $merchantIds)
                      ->orWhere(function($sq) use ($assignedBusIds) {
                          $sq->where('reference_type', 'App\Models\Bus')->whereIn('reference_id', $assignedBusIds);
                      })->orWhere(function($sq) use ($assignedParkingIds) {
                          $sq->where('reference_type', 'App\Models\Parking')->whereIn('reference_id', $assignedParkingIds);
                      });
                });
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('type', function($row) {
                    $class = $row->type == 'in' ? 'success' : 'danger';
                    return '<span class="badge bg-'.$class.'">TAP '.strtoupper($row->type).'</span>';
                })
                ->addColumn('user_card', function($row) {
                    $userName = $row->user ? $row->user->name : 'System';
                    $cardNo = $row->card ? $row->card->card_number : 'N/A';
                    return '<div><span class="fw-medium">'.$userName.'</span><br><small class="text-muted" style="font-size: 0.75rem; font-style: italic;">'.$cardNo.'</small></div>';
                })
                ->addColumn('asset_info', function($row) {
                    if (!$row->reference) return 'N/A';
                    $name = $row->reference_type == 'App\Models\Bus' ? ($row->reference->bus_number ?? $row->reference->name) : $row->reference->name;
                    $type = $row->reference_type == 'App\Models\Bus' ? 'Bus' : 'Parking';
                    $route = $row->reference_type == 'App\Models\Bus' ? 'buses.show' : 'parkings.show';
                    $link = route($route, $row->reference_id);
                    return '<div><a href="'.$link.'" class="fw-medium">'.$name.'</a><br><small class="text-muted" style="font-size: 0.75rem; font-style: italic;">'.$type.'</small></div>';
                })
                ->addColumn('display_date', function($row) {
                    return formatDate($row->created_at);
                })
                ->addColumn('action', function($row) {
                    return '<button class="btn btn-icon btn-sm btn-primary view-tap-map" 
                                data-lat="'.$row->latitude.'" 
                                data-lon="'.$row->longitude.'" 
                                data-name="'.($row->resolved_location_name ?? 'Current Location').'">
                                <i class="bx bx-map-alt"></i>
                            </button>';
                })
                ->rawColumns(['action', 'type', 'asset_info', 'user_card'])
                ->make(true);
        }

        $merchants = User::whereHas('roles', fn($q) => $q->where('slug', 'merchant'))->get();
        return view('modules.rides.taps', compact('merchants'));
    }

    /**
     * User's own ride history.
     */
    public function myRides(Request $request)
    {
        if ($request->ajax()) {
            $query = Ride::with(['reference', 'merchant', 'tapIn', 'tapOut'])
                ->where('user_id', auth()->id())
                ->latest();

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('status', function($row) {
                    $class = $row->status == 'completed' ? 'success' : 'primary';
                    return '<span class="badge bg-label-'.$class.'">'.ucfirst($row->status).'</span>';
                })
                ->addColumn('asset_info', function($row) {
                    if (!$row->reference) return 'N/A';
                    $name = $row->reference_type == 'App\Models\Bus' ? ($row->reference->bus_number ?? $row->reference->name) : $row->reference->name;
                    $type = $row->reference_type == 'App\Models\Bus' ? 'Bus' : 'Parking';
                    $route = $row->reference_type == 'App\Models\Bus' ? 'buses.show' : 'parkings.show';
                    $link = route($route, $row->reference_id);
                    return '<div><a href="'.$link.'" class="fw-medium">'.$name.'</a><br><small class="text-muted" style="font-size: 0.75rem; font-style: italic;">'.$type.'</small></div>';
                })
                ->editColumn('fare_amount', function($row) {
                    return 'Rs. ' . number_format($row->fare_amount, 2);
                })
                ->addColumn('display_date', function($row) {
                    return formatDate($row->created_at);
                })
                ->addColumn('tap_in_time', function($row) {
                    $time = formatDate($row->tapIn?->created_at);
                    if (!$row->tapIn) return '-';
                    $location = ($row->reference_type === 'App\Models\Bus') ? ($row->tapIn->resolved_location_name ?? 'Unknown') : '-';
                    return '<div>'.$time.'<br><small class="text-muted">'.$location.'</small></div>';
                })
                ->addColumn('tap_out_time', function($row) {
                    if (!$row->tapOut) return '---';
                    $time = formatDate($row->tapOut->created_at);
                    $location = ($row->reference_type === 'App\Models\Bus') ? ($row->tapOut->resolved_location_name ?? 'Unknown') : '-';
                    return '<div>'.$time.'<br><small class="text-muted">'.$location.'</small></div>';
                })
                ->addColumn('action', function($row) {
                    if (!$row->tapIn) return '';
                    return '<button class="btn btn-icon btn-sm btn-info view-ride-map" 
                                data-start-lat="'.$row->tapIn->latitude.'" 
                                data-start-lon="'.$row->tapIn->longitude.'"
                                data-start-name="'.($row->tapIn->resolved_location_name ?? 'Unknown').'"
                                data-end-lat="'.($row->tapOut ? $row->tapOut->latitude : '').'"
                                data-end-lon="'.($row->tapOut ? $row->tapOut->longitude : '').'"
                                data-end-name="'.($row->tapOut ? $row->tapOut->resolved_location_name : 'Journey Ongoing').'">
                                <i class="bx bx-map"></i>
                            </button>';
                })
                ->rawColumns(['action', 'status', 'asset_info', 'tap_in_time', 'tap_out_time'])
                ->make(true);
        }

        return view('modules.rides.my_rides');
    }

    /**
     * Simulate a tap for testing via Web UI.
     */
    public function simulateTap(Request $request)
    {
        $user = auth()->user();
        $card = $user->activeCard;

        if (!$card) {
            return response()->json(['status' => false, 'message' => 'You do not have an active card.']);
        }

        // Forward to API controller logic
        $tapApi = new \App\Http\Controllers\Api\TapController();
        
        $ongoingRide = Ride::where('card_id', $card->id)->where('status', 'ongoing')->first();

        // Prepare simulation data
        $simData = [
            'card_number' => $card->card_number,
            'lat' => 27.7172,
            'lon' => 85.3240,
        ];

        if ($ongoingRide) {
            // Use same asset for tap out
            $hwId = $ongoingRide->reference_type === \App\Models\Bus::class 
                ? \App\Models\Bus::find($ongoingRide->reference_id)->hwid 
                : $ongoingRide->reference_id;
            $simData['hw_id'] = (string)$hwId;
        } else {
            // Pick a random bus for tap in
            $bus = \App\Models\Bus::inRandomOrder()->first();
            if ($bus) {
                $simData['hw_id'] = (string)$bus->hwid;
            } else {
                // If no buses, try a parking lot
                $parking = \App\Models\Parking::inRandomOrder()->first();
                if (!$parking) return response()->json(['status' => false, 'message' => 'No assets (bus/parking) available for simulation.']);
                $simData['hw_id'] = (string)$parking->id;
            }
        }

        $request->merge($simData);
        
        $response = $tapApi->processTap($request);
        return $response;
    }
}
