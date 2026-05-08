<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteStop;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Route::with(['stops'])->latest();
            
            if (auth()->user()->hasRole('merchant')) {
                $query->whereHas('buses', function($q) {
                    $q->where('merchant_id', auth()->id());
                });
            } elseif (auth()->user()->hasRole('staff')) {
                $user = auth()->user();
                $merchantIds = $user->merchants->pluck('id');
                $query->whereHas('buses', function($q) use ($merchantIds, $user) {
                    $q->whereIn('merchant_id', $merchantIds)
                      ->orWhereHas('assignedStaff', function($sq) use ($user) {
                          $sq->where('user_id', $user->id);
                      });
                });
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('bus_info', function($row) {
                    $user = auth()->user();
                    $busesQuery = $row->buses();
                    
                    if ($user->hasRole('merchant')) {
                        $busesQuery->where('merchant_id', $user->id);
                    } elseif ($user->hasRole('staff')) {
                        $merchantIds = $user->merchants->pluck('id');
                        $busesQuery->where(function($q) use ($merchantIds, $user) {
                            $q->whereIn('merchant_id', $merchantIds)
                              ->orWhereHas('assignedStaff', function($sq) use ($user) {
                                  $sq->where('user_id', $user->id);
                              });
                        });
                    }
                    
                    $buses = $busesQuery->get();
                    $count = $buses->count();
                    $names = $buses->pluck('bus_number')->implode(', ');
                    
                    if ($count > 0) {
                        return '<span class="badge bg-label-info cursor-help d-inline-flex align-items-center" 
                                    data-bs-toggle="tooltip" 
                                    data-bs-placement="top" 
                                    title="' . $names . '">
                                    <i class="bx bx-bus me-1"></i>' . $count . ' ' . ($count == 1 ? 'Bus' : 'Buses') . '
                                </span>';
                    }
                    return '<span class="text-muted small">No buses</span>';
                })
                ->addColumn('stops_count', function($row) {
                    $count = $row->stops->count();
                    return '<span class="badge bg-label-info d-inline-flex align-items-center"><i class="bx bx-map-pin me-1"></i>' . $count . ' Stops</span>';
                })
                ->addColumn('action', function($row) {
                    $actions = '<div class="d-flex justify-content-center">';
                    $actions .= '<a href="'.route('routes.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';
                    if (auth()->user()->hasRole('super-admin')) {
                        $actions .= '<a href="'.route('routes.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    }
                    if (auth()->user()->hasRole('super-admin')) {
                        $actions .= '<form action="'.route('routes.destroy', $row->id).'" method="POST" style="display:inline-block">
                                        '.csrf_field().'
                                        '.method_field('DELETE').'
                                        <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                    </form>';
                    }
                    $actions .= '</div>';
                    return $actions;
                })
                ->addColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->rawColumns(['bus_info', 'stops_count', 'action'])
                ->make(true);
        }

        return view('modules.routes.index');
    }

    public function create()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        return view('modules.routes.create', ['route' => new Route()]);
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'stops' => 'required|array|min:2',
            'stops.*.id' => 'required|exists:stops,id',
        ]);

        DB::transaction(function() use ($request) {
            $route = Route::create([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            foreach ($request->stops as $index => $stopData) {
                $stop = \App\Models\Stop::find($stopData['id']);
                RouteStop::create([
                    'route_id' => $route->id,
                    'stop_id' => $stop->id,
                    'stop_name' => $stop->name,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'order' => $index,
                ]);
            }
        });

        return redirect()->route('routes.index')->with('success', 'Route created successfully.');
    }

    public function show(Route $route)
    {
        $route->load(['stops.stop', 'buses.merchant']);
        return view('modules.routes.show', compact('route'));
    }

    public function edit(Route $route)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $route->load(['stops.stop']);
        return view('modules.routes.edit', compact('route'));
    }

    public function update(Request $request, Route $route)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'stops' => 'required|array|min:2',
            'stops.*.id' => 'required|exists:stops,id',
        ]);

        DB::transaction(function() use ($request, $route) {
            $route->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            $route->stops()->delete();

            foreach ($request->stops as $index => $stopData) {
                $stop = \App\Models\Stop::find($stopData['id']);
                RouteStop::create([
                    'route_id' => $route->id,
                    'stop_id' => $stop->id,
                    'stop_name' => $stop->name,
                    'latitude' => $stop->latitude,
                    'longitude' => $stop->longitude,
                    'order' => $index,
                ]);
            }
        });

        return redirect()->route('routes.index')->with('success', 'Route updated successfully.');
    }

    public function destroy(Route $route)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        if ($route->buses()->exists() || $route->fares()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete route because it is assigned to one or more buses or has fare plans.');
        }

        $route->delete();
        return redirect()->route('routes.index')->with('success', 'Route deleted successfully.');
    }
}
