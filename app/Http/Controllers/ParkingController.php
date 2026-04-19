<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\User;
use App\Models\ParkingAttribute;
use App\Http\Requests\StoreParkingRequest;
use App\Http\Requests\UpdateParkingRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class ParkingController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     * Handles DataTable AJAX requests and initial page load.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Parking::with('merchant');
            
            // Limit to own parkings if merchant
            if (auth()->user()->hasRole('merchant')) {
                $query->where('merchant_id', auth()->id());
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('first_hour_fee', function($row) {
                    return 'Rs. ' . number_format($row->first_hour_fee, 2);
                })
                ->editColumn('status', function($row) {
                    $class = $row->status === 'opened' ? 'bg-label-success' : 'bg-label-secondary';
                    return '<span class="badge ' . $class . '">' . __('messages.' . $row->status) . '</span>';
                })
                ->addColumn('action', function($row){
                    $canEdit = auth()->user()->hasRole('super-admin', 'merchant');
                    $canDelete = auth()->user()->hasRole('super-admin');
                    
                    $actions = '';
                    // View Button
                    $actions .= '<a href="'.route('parkings.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';

                    // Toggle Status
                    if ($canEdit) {
                        $icon = $row->status === 'opened' ? 'bx-lock-alt' : 'bx-lock-open-alt';
                        $btnClass = $row->status === 'opened' ? 'btn-warning' : 'btn-success';
                        $title = $row->status === 'opened' ? 'Close Parking' : 'Open Parking';
                        
                        $actions .= '<form action="'.route('parkings.toggle-status', $row->id).'" method="POST" style="display:inline-block">
                                        '.csrf_field().'
                                        <button type="submit" class="btn btn-icon btn-sm '.$btnClass.' me-1" title="'.$title.'"><i class="bx '.$icon.'"></i></button>
                                    </form>';
                    }

                    // Edit Button
                    if ($canEdit) {
                        $actions .= '<a href="'.route('parkings.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    }
                    // Delete Button
                    if ($canDelete) {
                        $actions .= '<form action="'.route('parkings.destroy', $row->id).'" method="POST" style="display:inline-block">
                                        '.csrf_field().'
                                        '.method_field('DELETE').'
                                        <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                    </form>';
                    }
                    return $actions;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('modules.parkings.index');
    }

    /**
     * Toggle the status of the parking.
     */
    public function toggleStatus(Parking $parking)
    {
        if (auth()->user()->hasRole('merchant') && $parking->merchant_id != auth()->id()) abort(403);
        if (!auth()->user()->hasRole('super-admin', 'merchant')) abort(403);

        $parking->status = $parking->status === 'opened' ? 'closed' : 'opened';
        $parking->save();

        return redirect()->back()->with('success', 'Parking status updated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Parking $parking)
    {
        // Merchant can only view their own
        if (auth()->user()->hasRole('merchant') && $parking->merchant_id != auth()->id()) abort(403);
        
        $parking->load(['attributes', 'merchant']);
        return view('modules.parkings.show', compact('parking'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $parking = new Parking();
        $merchants = User::whereHas('roles', function($q){ $q->where('slug', 'merchant'); })->get();
        $allAttributes = ParkingAttribute::all();
        return view('modules.parkings.create', compact('parking', 'merchants', 'allAttributes'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * Uses StoreParkingRequest for validation.
     */
    public function store(StoreParkingRequest $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        DB::transaction(function() use ($request) {
            $parking = Parking::create($request->all());
            
            if ($request->has('attributes')) {
                $parking->attributes()->sync($request->input('attributes'));
            }
        });

        return redirect()->route('parkings.index')->with('success', 'Parking created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Parking $parking)
    {
        // Merchant can only edit their own
        if (auth()->user()->hasRole('merchant') && $parking->merchant_id != auth()->id()) abort(403);
        if (!auth()->user()->hasRole('super-admin', 'merchant')) abort(403);
        
        $merchants = User::whereHas('roles', function($q){ $q->where('slug', 'merchant'); })->get();
        $allAttributes = ParkingAttribute::all();
        $parking->load('attributes');
        
        return view('modules.parkings.edit', compact('parking', 'merchants', 'allAttributes'));
    }

    /**
     * Update the specified resource in storage.
     * 
     * Uses UpdateParkingRequest for validation.
     */
    public function update(UpdateParkingRequest $request, Parking $parking)
    {
        if (auth()->user()->hasRole('merchant') && $parking->merchant_id != auth()->id()) abort(403);
        
        DB::transaction(function() use ($request, $parking) {
            $parking->update($request->all());
            
            if ($request->has('attributes')) {
                $parking->attributes()->sync($request->input('attributes'));
            } else {
                $parking->attributes()->detach();
            }
        });

        return redirect()->route('parkings.index')->with('success', 'Parking updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Parking $parking)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $parking->delete();
        return redirect()->route('parkings.index')->with('success', 'Parking deleted successfully.');
    }
}
