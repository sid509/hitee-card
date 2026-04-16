<?php

namespace App\Http\Controllers;

use App\Models\Parking;
use App\Models\User;
use App\Http\Requests\StoreParkingRequest;
use App\Http\Requests\UpdateParkingRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

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
                ->addColumn('action', function($row){
                    $canEdit = auth()->user()->hasRole('super-admin', 'merchant');
                    $canDelete = auth()->user()->hasRole('super-admin');
                    
                    $actions = '';
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
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('modules.parkings.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $parking = new Parking();
        $merchants = User::whereHas('roles', function($q){ $q->where('slug', 'merchant'); })->get();
        return view('modules.parkings.create', compact('parking', 'merchants'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * Uses StoreParkingRequest for validation.
     */
    public function store(StoreParkingRequest $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        Parking::create($request->all());

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
        return view('modules.parkings.edit', compact('parking', 'merchants'));
    }

    /**
     * Update the specified resource in storage.
     * 
     * Uses UpdateParkingRequest for validation.
     */
    public function update(UpdateParkingRequest $request, Parking $parking)
    {
        if (auth()->user()->hasRole('merchant') && $parking->merchant_id != auth()->id()) abort(403);
        
        $parking->update($request->all());

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
