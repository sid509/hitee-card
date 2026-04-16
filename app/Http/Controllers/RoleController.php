<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     * Handles DataTable AJAX requests and initial page load.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = Role::select(['id', 'name', 'slug', 'created_at']);
            return DataTables::of($data)
                ->addIndexColumn()
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('action', function($row){
                    // Edit Button
                    $actions = '<a href="'.route('roles.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    // Delete Button
                    $actions .= '<form action="'.route('roles.destroy', $row->id).'" method="POST" style="display:inline-block">
                                    '.csrf_field().'
                                    '.method_field('DELETE').'
                                    <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                </form>';
                    return $actions;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('modules.roles.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $role = new Role();
        $permissions = Permission::all();
        return view('modules.roles.create', compact('role', 'permissions'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * Uses StoreRoleRequest for validation.
     */
    public function store(StoreRoleRequest $request)
    {
        $role = Role::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', 'Role created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        $permissions = Permission::all();
        return view('modules.roles.edit', compact('role', 'permissions'));
    }

    /**
     * Update the specified resource in storage.
     * 
     * Uses UpdateRoleRequest for validation.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        // Prevent editing system roles slugs usually, only name
        $role->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        if ($request->has('permissions')) {
            $role->permissions()->sync($request->permissions);
        }

        return redirect()->route('roles.index')->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        // Safety check for system roles
        if (in_array($role->slug, ['super-admin', 'merchant', 'customers'])) {
            return redirect()->route('roles.index')->with('error', 'Cannot delete system roles.');
        }
        $role->delete();
        return redirect()->route('roles.index')->with('success', 'Role deleted successfully.');
    }
}
