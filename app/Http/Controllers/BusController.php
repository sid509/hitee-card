<?php

namespace App\Http\Controllers;

use App\Models\Bus;
use App\Models\User;
use App\Http\Requests\StoreBusRequest;
use App\Http\Requests\UpdateBusRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class BusController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     * Handles DataTable AJAX requests and initial page load.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Bus::with(['merchant', 'route']);
            
            // Limit buses to merchant's own if they are a merchant
            if (auth()->user()->hasRole('merchant')) {
                $query->where('merchant_id', auth()->id());
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('name', function($row) {
                    return '<div class="d-flex align-items-center">
                                <img src="' . $row->featured_image_url . '" alt="Bus" class="rounded-circle me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                <span>' . $row->name . '</span>
                            </div>';
                })
                ->addColumn('route_name', fn($row) => $row->route->name ?? 'N/A')
                ->editColumn('status', function($row) {
                    $class = $row->status === 'active' ? 'bg-label-success' : 'bg-label-secondary';
                    return '<span class="badge ' . $class . '">' . __('messages.' . $row->status) . '</span>';
                })
                ->addColumn('action', function($row){
                    $canEdit = auth()->user()->hasRole('super-admin', 'merchant');
                    $canDelete = auth()->user()->hasRole('super-admin');
                    
                    $actions = '';
                    // View Button
                    $actions .= '<a href="'.route('buses.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';
                    
                    // Toggle Status
                    if ($canEdit) {
                        $icon = $row->status === 'active' ? 'bx-block' : 'bx-check-circle';
                        $btnClass = $row->status === 'active' ? 'btn-warning' : 'btn-success';
                        $title = $row->status === 'active' ? 'Deactivate' : 'Activate';
                        
                        $actions .= '<form action="'.route('buses.toggle-status', $row->id).'" method="POST" style="display:inline-block">
                                        '.csrf_field().'
                                        <button type="submit" class="btn btn-icon btn-sm '.$btnClass.' me-1" title="'.$title.'"><i class="bx '.$icon.'"></i></button>
                                    </form>';
                    }

                    // Edit Button
                    if ($canEdit) {
                        $actions .= '<a href="'.route('buses.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    }
                    // Delete Button
                    if ($canDelete) {
                        $actions .= '<form action="'.route('buses.destroy', $row->id).'" method="POST" style="display:inline-block">
                                        '.csrf_field().'
                                        '.method_field('DELETE').'
                                        <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                    </form>';
                    }
                    return $actions;
                })
                ->rawColumns(['name', 'status', 'action'])
                ->make(true);
        }

        return view('modules.buses.index');
    }

    /**
     * Toggle the status of the bus.
     */
    public function toggleStatus(Bus $bus)
    {
        if (auth()->user()->hasRole('merchant') && $bus->merchant_id != auth()->id()) abort(403);
        if (!auth()->user()->hasRole('super-admin', 'merchant')) abort(403);

        $bus->status = $bus->status === 'active' ? 'inactive' : 'active';
        $bus->save();

        return redirect()->back()->with('success', 'Bus status updated successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Bus $bus)
    {
        // Merchant can only view their own
        if (auth()->user()->hasRole('merchant') && $bus->merchant_id != auth()->id()) abort(403);
        
        return view('modules.buses.show', compact('bus'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Only super-admins can create buses usually
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        
        $bus = new Bus();
        $merchants = User::whereHas('roles', function($q){ $q->where('slug', 'merchant'); })->get();
        return view('modules.buses.create', compact('bus', 'merchants'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * Uses StoreBusRequest for validation.
     */
    public function store(StoreBusRequest $request)
    {
        // Only super-admins can store buses
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $bus = Bus::create($request->validated());

        // Handle Featured Image
        if ($request->hasFile('featured_image')) {
            $bus->addMedia($request->file('featured_image'), 'featured');
        }

        // Handle Gallery Images
        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $file) {
                $bus->addMedia($file, 'gallery');
            }
        }

        return redirect()->route('buses.index')->with('success', 'Bus created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bus $bus)
    {
        // Ensure merchant only edits their own bus
        if (auth()->user()->hasRole('merchant') && $bus->merchant_id != auth()->id()) abort(403);
        if (!auth()->user()->hasRole('super-admin', 'merchant')) abort(403);
        
        $merchants = User::whereHas('roles', function($q){ $q->where('slug', 'merchant'); })->get();
        return view('modules.buses.edit', compact('bus', 'merchants'));
    }

    /**
     * Update the specified resource in storage.
     * 
     * Uses UpdateBusRequest for validation.
     */
    public function update(UpdateBusRequest $request, Bus $bus)
    {
        // Ensure merchant only updates their own bus
        if (auth()->user()->hasRole('merchant') && $bus->merchant_id != auth()->id()) abort(403);
        
        $data = [
            'name' => $request->name,
            'status' => $request->status,
        ];

        // Super-admin can update sensitive fields
        if (auth()->user()->hasRole('super-admin')) {
            $data['bus_number'] = $request->bus_number;
            $data['hwid'] = $request->hwid;
            $data['merchant_id'] = $request->merchant_id;
        }

        $bus->update($data);

        // Handle Featured Image
        if ($request->hasFile('featured_image')) {
            $bus->clearMediaCollection('featured');
            $bus->addMedia($request->file('featured_image'), 'featured');
        }

        // Handle Gallery Images
        if ($request->hasFile('gallery_images')) {
            foreach ($request->file('gallery_images') as $file) {
                $bus->addMedia($file, 'gallery');
            }
        }

        return redirect()->route('buses.index')->with('success', 'Bus updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bus $bus)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        
        $bus->delete();
        return redirect()->route('buses.index')->with('success', 'Bus deleted successfully.');
    }
}
