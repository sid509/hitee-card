<?php

namespace App\Http\Controllers;

use App\Models\Stop;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Log;

class StopController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Stop::query();
            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('action', function($row){
                    $btn = '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-secondary me-1 view-stop-map" data-lat="'.$row->latitude.'" data-lon="'.$row->longitude.'" data-name="'.$row->name.'"><i class="bx bx-map"></i></a>';
                    $btn .= '<a href="'.route('stops.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1"><i class="bx bx-edit-alt"></i></a>';
                    $btn .= '<form action="'.route('stops.destroy', $row->id).'" method="POST" style="display:inline-block">
                                '.csrf_field().'
                                '.method_field('DELETE').'
                                <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn"><i class="bx bx-trash"></i></button>
                            </form>';
                    return $btn;
                })
                ->addColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('modules.stops.index');
    }

    public function create()
    {
        return view('modules.stops.create', ['stop' => new Stop()]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:stops,name',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'description' => 'nullable|string'
        ]);

        Stop::create($request->all());

        return redirect()->route('stops.index')->with('success', 'Stop created successfully.');
    }

    public function edit(Stop $stop)
    {
        return view('modules.stops.edit', compact('stop'));
    }

    public function update(Request $request, Stop $stop)
    {
        $request->validate([
            'name' => 'required|unique:stops,name,' . $stop->id,
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'description' => 'nullable|string'
        ]);

        $stop->update($request->all());

        return redirect()->route('stops.index')->with('success', 'Stop updated successfully.');
    }

    public function destroy(Stop $stop)
    {
        if ($stop->routeStops()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete stop because it is part of one or more routes.');
        }

        $stop->delete();
        return redirect()->route('stops.index')->with('success', 'Stop deleted successfully.');
    }
}
