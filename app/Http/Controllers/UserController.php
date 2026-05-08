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
            $data = User::with(['roles', 'activeCard'])
                ->select(['id', 'name', 'email', 'phone_number', 'status', 'created_at'])
                ->latest();

            // Filter by Role
            if ($request->filled('role')) {
                $data->whereHas('roles', function($q) use ($request) {
                    $q->where('slug', $request->role);
                });
            }

            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('checkbox', function($row){
                    if ($row->id === auth()->id()) return '';
                    return '<input type="checkbox" class="form-check-input row-checkbox" value="'.$row->id.'">';
                })
                ->addColumn('user_info', function($row){
                    return '<div>
                                <span class="fw-medium">'.$row->name.'</span><br>
                                <small class="text-muted" style="font-size: 0.75rem; font-style: italic;">'.$row->email.'</small>
                            </div>';
                })
                ->addColumn('card_info', function($row){
                    if (!$row->activeCard) return '<span class="text-muted small">No Active Card</span>';
                    return '<div>
                                <span class="fw-medium">'.$row->activeCard->card_number.'</span><br>
                                <small class="text-muted" style="font-size: 0.75rem; font-style: italic;">HW: '.$row->activeCard->hwid.'</small>
                            </div>';
                })
                ->addColumn('role_icons', function($row){
                    return $row->roles->map(function($role){
                        $icon = 'bx-user';
                        $color = 'primary';
                        if ($role->slug === 'super-admin') { $icon = 'bx-shield-quarter'; $color = 'danger'; }
                        elseif ($role->slug === 'merchant') { $icon = 'bx-store-alt'; $color = 'info'; }
                        elseif ($role->slug === 'staff') { $icon = 'bx-group'; $color = 'warning'; }

                        return '<span class="badge badge-center rounded-pill bg-label-'.$color.'" data-bs-toggle="tooltip" data-bs-placement="top" title="'.$role->name.'"><i class="bx '.$icon.'"></i></span>';
                    })->implode(' ');
                })
                ->addColumn('balance', function($row){
                    $balance = $row->balance();
                    $class = $balance < 0 ? 'bg-label-danger' : '';
                    return '<span class="badge '.$class.'">Rs. '.number_format($balance, 2).'</span>';
                })
                ->editColumn('status', function($row){
                    if ($row->id === auth()->id()) {
                        return '<i class="bx bx-check-circle text-success fs-4"></i>';
                    }

                    $s = (int)$row->status;
                    if ($s === -1) {
                        return '<a href="javascript:void(0);" class="approve-user-btn" data-id="'.$row->id.'" title="Click to Approve">
                                    <i class="bx bx-help-circle text-warning fs-4"></i>
                                </a>';
                    }

                    $icon = $s === 1 ? 'bx-check-circle text-success' : 'bx-x-circle text-danger';
                    $title = $s === 1 ? 'Active - Click to Deactivate' : 'Inactive - Click to Activate';
                    
                    return '<a href="javascript:void(0);" class="toggle-user-status" data-id="'.$row->id.'" title="'.$title.'">
                                <i class="bx '.$icon.' fs-4"></i>
                            </a>';
                })
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->addColumn('action', function($row){
                    $actions = '<div class="d-flex">';

                    // View Button
                    $actions .= '<a href="'.route('users.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';

                    // Edit Button
                    $actions .= '<a href="'.route('users.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';

                    // Balance Management Button
                    if (auth()->user()->hasRole('super-admin')) {
                        $activeCardId = $row->activeCard?->id;
                        $actions .= '<button type="button" class="btn btn-icon btn-sm btn-success me-1 manage-balance-btn" 
                                        data-id="'.$row->id.'" 
                                        data-name="'.$row->name.'" 
                                        data-balance="'.$row->balance().'" 
                                        data-card-id="'.$activeCardId.'" 
                                        title="Manage Balance">
                                        <i class="bx bx-wallet"></i>
                                     </button>';
                    }

                    // Impersonate Button for super-admins
                    if (auth()->user()->canImpersonate() && $row->id !== auth()->id()) {
                        $actions .= '<a href="'.route('impersonate', $row->id).'" class="btn btn-icon btn-sm btn-info me-1" title="Impersonate Account"><i class="bx bx-log-in-circle"></i></a>';
                    }

                    // Delete Button
                    if ($row->id !== auth()->id()) {
                        $actions .= '<form action="'.route('users.destroy', $row->id).'" method="POST" style="display:inline-block">
                                        '.csrf_field().'
                                        '.method_field('DELETE').'
                                        <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                    </form>';
                    }

                    $actions .= '</div>';
                    return $actions;
                })
                ->rawColumns(['action', 'role_icons', 'balance', 'checkbox', 'user_info', 'card_info', 'status'])
                ->make(true);
        }

        return view('modules.users.index', [
            'merchants' => User::whereHas('roles', fn($q) => $q->whereIn('slug', ['merchant', 'staff']))->get(),
            'roles' => Role::all()
        ]);
    }

    /**
     * Bulk toggle user status
     */
    public function bulkToggleStatus(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
            'status' => 'required|in:active,inactive'
        ]);

        $ids = array_filter($request->ids, fn($id) => $id != auth()->id());

        User::whereIn('id', $ids)->update(['status' => $request->status]);

        return response()->json([
            'status' => true,
            'message' => count($ids) . ' users updated to ' . $request->status . '.'
        ]);
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
            'is_tourist' => $request->boolean('is_tourist'),
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
        $notifications = $user->notifications()->with('template')->latest()->paginate(10);
        return view('modules.users.show', compact('user', 'notifications'));
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
            'phone_number' => $request->phone_number,
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

        if ($user->cards()->exists() || $user->rides()->exists() || $user->taps()->exists()) {
             return redirect()->route('users.index')->with('error', 'Cannot delete user because they have associated card or transaction history.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Toggle user status (Super Admin only)
     */
    public function toggleStatus(User $user)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        if ($user->id === auth()->id()) {
            return response()->json(['status' => false, 'message' => 'You cannot disable your own account.']);
        }

        // If status is -1 (pending), we don't toggle, we expect explicit approval.
        // Toggling only happens between 0 (inactive) and 1 (active).
        $newStatus = $user->status == User::STATUS_ACTIVE ? User::STATUS_INACTIVE : User::STATUS_ACTIVE;
        $user->update(['status' => $newStatus]);

        $statusText = $newStatus == User::STATUS_ACTIVE ? 'active' : 'inactive';

        logActivity('user_status_toggle', "User {$user->email} status changed to {$statusText}", [
            'target_user_id' => $user->id,
            'new_status' => $newStatus
        ]);

        return response()->json([
            'status' => true,
            'message' => "User account is now {$statusText}.",
            'new_status' => $newStatus
        ]);
    }

    /**
     * Approve user (Super Admin only)
     */
    public function approve(User $user)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        if (!$user->email_verified_at) {
            return response()->json(['status' => false, 'message' => 'User email is not verified yet.']);
        }

        $user->update(['status' => User::STATUS_ACTIVE]);

        logActivity('user_approved', "User {$user->email} approved by admin", [
            'target_user_id' => $user->id
        ]);

        return response()->json([
            'status' => true,
            'message' => "User {$user->name} has been approved and activated."
        ]);
    }
}
