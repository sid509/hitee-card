<?php

namespace App\Http\Controllers;

use App\Models\ParkingAttribute;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Storage;

class ParkingAttributeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        if ($request->ajax()) {
            $data = ParkingAttribute::latest();
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('icon', function($row){
                    return '<img src="'.$row->icon_url.'" alt="'.$row->name.'" height="24" width="24" class="me-2">';
                })
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('action', function($row){
                    $actions = '<a href="'.route('parking-attributes.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    $actions .= '<form action="'.route('parking-attributes.destroy', $row->id).'" method="POST" style="display:inline-block">
                                    '.csrf_field().'
                                    '.method_field('DELETE').'
                                    <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                </form>';
                    return $actions;
                })
                ->rawColumns(['icon', 'action'])
                ->make(true);
        }

        return view('modules.parking_attributes.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $attribute = new ParkingAttribute();
        return view('modules.parking_attributes.create', compact('attribute'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'name' => 'required|string|max:255|unique:parking_attributes',
            'icon' => 'nullable|file|mimes:svg|max:1024',
        ]);

        $data = $request->only('name');

        if ($request->hasFile('icon')) {
            $path = $request->file('icon')->store('parking_attributes', 'public');
            $data['icon'] = $path;
        }

        ParkingAttribute::create($data);

        return redirect()->route('parking-attributes.index')->with('success', 'Attribute created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ParkingAttribute $parkingAttribute)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        return view('modules.parking_attributes.edit', ['attribute' => $parkingAttribute]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ParkingAttribute $parkingAttribute)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'name' => 'required|string|max:255|unique:parking_attributes,name,' . $parkingAttribute->id,
            'icon' => 'nullable|file|mimes:svg|max:1024',
        ]);

        $data = $request->only('name');

        if ($request->hasFile('icon')) {
            // Delete old icon
            if ($parkingAttribute->icon) {
                Storage::disk('public')->delete($parkingAttribute->icon);
            }
            $path = $request->file('icon')->store('parking_attributes', 'public');
            $data['icon'] = $path;
        }

        $parkingAttribute->update($data);

        return redirect()->route('parking-attributes.index')->with('success', 'Attribute updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ParkingAttribute $parkingAttribute)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        if ($parkingAttribute->parkings()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete attribute because it is assigned to one or more parkings.');
        }

        if ($parkingAttribute->icon) {
            Storage::disk('public')->delete($parkingAttribute->icon);
        }

        $parkingAttribute->delete();

        return redirect()->route('parking-attributes.index')->with('success', 'Attribute deleted successfully.');
    }
}
