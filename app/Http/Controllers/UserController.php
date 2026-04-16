<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     * Handles DataTable AJAX requests and initial page load.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $data = User::with('roles')->select(['id', 'name', 'email', 'created_at']);
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('role_names', function($row){
                    return $row->roles->map(function($role){
                        return '<span class="badge bg-label-primary">'.$role->name.'</span>';
                    })->implode(' ');
                })
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('action', function($row){
                    $actions = '';
                    // Edit Button
                    $actions .= '<a href="'.route('users.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    // View Button
                    $actions .= '<a href="'.route('users.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';
                    
                    // Impersonate Button for super-admins
                    if (auth()->user()->canImpersonate() && $row->id !== auth()->id()) {
                        $actions .= '<a href="'.route('impersonate', $row->id).'" class="btn btn-icon btn-sm btn-warning me-1" title="Impersonate"><i class="bx bx-user-check"></i></a>';
                    }

                    // Delete Button
                    $actions .= '<form action="'.route('users.destroy', $row->id).'" method="POST" style="display:inline-block">
                                    '.csrf_field().'
                                    '.method_field('DELETE').'
                                    <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                </form>';
                    return $actions;
                })
                ->rawColumns(['action', 'role_names'])
                ->make(true);
        }

        return view('modules.users.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $user = new User();
        $roles = Role::all();
        return view('modules.users.create', compact('user', 'roles'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * Uses StoreUserRequest for validation.
     */
    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'status' => 'active',
        ]);

        if ($request->has('roles')) {
            $user->roles()->sync($request->roles);
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user)
    {
        return view('modules.users.show', compact('user'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user)
    {
        $roles = Role::all();
        return view('modules.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     * 
     * Uses UpdateUserRequest for validation.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);
        $user->roles()->sync($request->roles ?? []);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        // Safety check: Don't delete self
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'You cannot delete your own account.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
