<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class RouteController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Route::with('merchant');
            
            if (auth()->user()->hasRole('merchant')) {
                $query->where('merchant_id', auth()->id());
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('stops_count', fn($row) => $row->stops()->count())
                ->addColumn('action', function($row) {
                    $actions = '<a href="'.route('routes.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';
                    if (auth()->user()->hasRole('super-admin', 'merchant')) {
                        $actions .= '<a href="'.route('routes.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    }
                    if (auth()->user()->hasRole('super-admin')) {
                        $actions .= '<form action="'.route('routes.destroy', $row->id).'" method="POST" style="display:inline-block">
                                        '.csrf_field().'
                                        '.method_field('DELETE').'
                                        <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                    </form>';
                    }
                    return $actions;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('modules.routes.index');
    }

    public function create()
    {
        return view('modules.routes.create', ['route' => new Route()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'stops' => 'required|array|min:2',
            'stops.*.name' => 'required|string',
            'stops.*.lat' => 'required|numeric',
            'stops.*.lng' => 'required|numeric',
        ]);

        DB::transaction(function() use ($request) {
            $route = Route::create([
                'merchant_id' => auth()->user()->hasRole('super-admin') ? $request->merchant_id : auth()->id(),
                'name' => $request->name,
                'description' => $request->description,
            ]);

            foreach ($request->stops as $index => $stop) {
                RouteStop::create([
                    'route_id' => $route->id,
                    'stop_name' => $stop['name'],
                    'latitude' => $stop['lat'],
                    'longitude' => $stop['lng'],
                    'order' => $index,
                ]);
            }
        });

        return redirect()->route('routes.index')->with('success', 'Route created successfully.');
    }

    public function show(Route $route)
    {
        $route->load(['stops', 'buses.merchant', 'merchant']);
        return view('modules.routes.show', compact('route'));
    }

    public function edit(Route $route)
    {
        if (auth()->user()->hasRole('merchant') && $route->merchant_id != auth()->id()) abort(403);
        $route->load('stops');
        return view('modules.routes.edit', compact('route'));
    }

    public function update(Request $request, Route $route)
    {
        if (auth()->user()->hasRole('merchant') && $route->merchant_id != auth()->id()) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'stops' => 'required|array|min:2',
        ]);

        DB::transaction(function() use ($request, $route) {
            $route->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            $route->stops()->delete();

            foreach ($request->stops as $index => $stop) {
                RouteStop::create([
                    'route_id' => $route->id,
                    'stop_name' => $stop['name'],
                    'latitude' => $stop['lat'],
                    'longitude' => $stop['lng'],
                    'order' => $index,
                ]);
            }
        });

        return redirect()->route('routes.index')->with('success', 'Route updated successfully.');
    }

    public function destroy(Route $route)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $route->delete();
        return redirect()->route('routes.index')->with('success', 'Route deleted successfully.');
    }
}
