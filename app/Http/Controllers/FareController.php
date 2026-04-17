<?php

namespace App\Http\Controllers;

use App\Models\Fare;
use App\Models\FareMatrix;
use App\Models\Route;
use App\Models\Bus;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class FareController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Fare::with(['merchant', 'route']);
            
            if (auth()->user()->hasRole('merchant')) {
                $query->where('merchant_id', auth()->id());
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('status', function($row) {
                    $class = $row->status == 'approved' ? 'success' : ($row->status == 'proposed' ? 'warning' : 'danger');
                    return '<span class="badge bg-label-'.$class.'">'.ucfirst($row->status).'</span>';
                })
                ->addColumn('action', function($row) {
                    $actions = '<a href="'.route('fares.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';
                    
                    if (auth()->user()->hasRole('super-admin')) {
                        if ($row->status == 'proposed') {
                            $actions .= '<button type="button" class="btn btn-icon btn-sm btn-success me-1 approve-fare" data-id="'.$row->id.'" title="Approve"><i class="bx bx-check"></i></button>';
                        }
                        $actions .= '<button type="button" class="btn btn-icon btn-sm btn-info me-1 assign-bus" data-id="'.$row->id.'" data-route-id="'.$row->route_id.'" title="Assign to Bus"><i class="bx bx-bus"></i></button>';
                    }
                    
                    return $actions;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('modules.fares.index');
    }

    public function create()
    {
        $routes = Route::query();
        if (auth()->user()->hasRole('merchant')) {
            $routes->where('merchant_id', auth()->id());
        }
        return view('modules.fares.create', [
            'fare' => new Fare(),
            'routes' => $routes->get()
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'route_id' => 'required|exists:routes,id',
            'name' => 'required|string|max:255',
            'matrix' => 'required|array',
        ]);

        DB::transaction(function() use ($request) {
            $fare = Fare::create([
                'merchant_id' => auth()->user()->hasRole('super-admin') ? $request->merchant_id : auth()->id(),
                'route_id' => $request->route_id,
                'name' => $request->name,
                'status' => 'proposed',
            ]);

            foreach ($request->matrix as $fromStopId => $toStops) {
                foreach ($toStops as $toStopId => $amount) {
                    if ($amount !== null && $amount !== '') {
                        FareMatrix::create([
                            'fare_id' => $fare->id,
                            'from_stop_id' => $fromStopId,
                            'to_stop_id' => $toStopId,
                            'amount' => $amount,
                        ]);
                    }
                }
            }
        });

        return redirect()->route('fares.index')->with('success', 'Fare proposal submitted successfully.');
    }

    public function show(Fare $fare)
    {
        $fare->load(['route.stops', 'matrices']);
        return view('modules.fares.show', compact('fare'));
    }

    public function approve(Request $request, Fare $fare)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $fare->update(['status' => 'approved', 'effective_from' => now()]);
        return response()->json(['message' => 'Fare approved successfully.']);
    }

    public function assignBus(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        
        $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'fare_id' => 'required|exists:fares,id',
        ]);

        $fare = Fare::findOrFail($request->fare_id);
        $bus = Bus::findOrFail($request->bus_id);

        $bus->update([
            'route_id' => $fare->route_id,
            'active_fare_id' => $fare->id,
        ]);

        return response()->json(['message' => 'Fare assigned to bus successfully.']);
    }

    public function getMatrixForm(Request $request)
    {
        $route = Route::with('stops')->findOrFail($request->route_id);
        return view('modules.fares._matrix_form', compact('route'))->render();
    }
}
