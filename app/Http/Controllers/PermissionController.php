<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Http\Requests\StorePermissionRequest;
use App\Http\Requests\UpdatePermissionRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     * Handles DataTable AJAX requests and initial page load.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Permission::select(['id', 'name', 'slug', 'created_at']);
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('action', function($row){
                    // Edit Button
                    $actions = '<a href="'.route('permissions.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    // Delete Button
                    $actions .= '<form action="'.route('permissions.destroy', $row->id).'" method="POST" style="display:inline-block">
                                    '.csrf_field().'
                                    '.method_field('DELETE').'
                                    <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                </form>';
                    return $actions;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('modules.permissions.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $permission = new Permission();
        return view('modules.permissions.create', compact('permission'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * Uses StorePermissionRequest for validation.
     */
    public function store(StorePermissionRequest $request)
    {
        Permission::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return redirect()->route('permissions.index')->with('success', 'Permission created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Permission $permission)
    {
        return view('modules.permissions.edit', compact('permission'));
    }

    /**
     * Update the specified resource in storage.
     * 
     * Uses UpdatePermissionRequest for validation.
     */
    public function update(UpdatePermissionRequest $request, Permission $permission)
    {
        $permission->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return redirect()->route('permissions.index')->with('success', 'Permission updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Permission $permission)
    {
        if ($permission->roles()->exists()) {
            return redirect()->route('permissions.index')->with('error', 'Cannot delete permission because it is assigned to one or more roles.');
        }

        $permission->delete();
        return redirect()->route('permissions.index')->with('success', 'Permission deleted successfully.');
    }
}
