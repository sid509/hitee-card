<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use App\Models\Bus;
use App\Models\Parking;
use Illuminate\Http\Request;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use Illuminate\Support\Facades\Hash;
use Yajra\DataTables\Facades\DataTables;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $user = auth()->user();
            
            if ($user->hasRole('super-admin')) {
                // Admin sees all users with 'staff' role
                $data = User::whereHas('roles', fn($q) => $q->where('slug', 'staff'))
                    ->with(['assignedBuses', 'assignedParkings'])
                    ->select(['users.id', 'users.name', 'users.email', 'users.status']);
            } else {
                // Merchant sees only their linked staff
                $data = $user->staff()
                    ->with(['assignedBuses', 'assignedParkings'])
                    ->select(['users.id', 'users.name', 'users.email', 'users.status']);
            }
            
            return DataTables::of($data)
                ->addIndexColumn()
                ->addColumn('staff_info', function($row){
                    return '<div>
                                <span class="fw-medium">'.$row->name.'</span><br>
                                <small class="text-muted" style="font-size: 0.75rem; font-style: italic;">'.$row->email.'</small>
                            </div>';
                })
                ->addColumn('assignments', function($row){
                    $busCount = $row->assignedBuses->count();
                    $parkingCount = $row->assignedParkings->count();
                    $total = $busCount + $parkingCount;
                    
                    if ($total === 0) return '<span class="text-muted small">No assignments</span>';

                    $details = [];
                    foreach($row->assignedBuses as $b) $details[] = "Bus: " . $b->bus_number;
                    foreach($row->assignedParkings as $p) $details[] = "Parking: " . $p->name;
                    $tooltip = implode(', ', $details);

                    return '<span class="badge bg-label-info cursor-help d-inline-flex align-items-center" data-bs-toggle="tooltip" data-bs-placement="top" title="' . $tooltip . '">
                                <i class="bx bx-task me-1"></i>' . $total . ' Assignments
                            </span>';
                })
                ->addColumn('action', function($row){
                    $actions = '<div class="d-flex">';
                    $actions .= '<a href="'.route('staff.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit/Assign"><i class="bx bx-edit-alt"></i></a>';
                    $actions .= '<form action="'.route('staff.detach', $row->id).'" method="POST" style="display:inline-block">
                                    '.csrf_field().'
                                    <button type="submit" class="btn btn-icon btn-sm btn-danger detach-btn" title="Detach Staff"><i class="bx bx-user-minus"></i></button>
                                </form>';
                    $actions .= '</div>';
                    return $actions;
                })
                ->rawColumns(['action', 'staff_info', 'assignments'])
                ->make(true);
        }

        return view('modules.staff.index');
    }

    public function create()
    {
        $buses = auth()->user()->buses;
        $parkings = auth()->user()->parkings;
        return view('modules.staff.create', compact('buses', 'parkings'));
    }

    public function store(StoreStaffRequest $request)
    {
        $merchant = auth()->user();
        $staffRole = Role::where('slug', 'staff')->first();
        $customerRole = Role::where('slug', 'customers')->first();
        
        // If searching for existing user
        if ($request->filled('user_id')) {
            $user = User::findOrFail($request->user_id);
            // Ensure they have both roles
            $user->roles()->syncWithoutDetaching([$staffRole->id, $customerRole->id]);
        } else {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'active',
            ]);
            
            $user->roles()->syncWithoutDetaching([$staffRole->id, $customerRole->id]);
        }

        $merchant->staff()->syncWithoutDetaching([$user->id]);
        
        // Handle assignments
        $user->assignedBuses()->sync($request->buses ?? []);
        $user->assignedParkings()->sync($request->parkings ?? []);

        return redirect()->route('staff.index')->with('success', 'Staff added successfully.');
    }

    public function edit(User $staff)
    {
        $merchant = auth()->user();
        if (!$merchant->staff->contains($staff->id)) abort(403);

        $buses = $merchant->buses;
        $parkings = $merchant->parkings;
        
        $assignedBuses = $staff->assignedBuses->pluck('id')->toArray();
        $assignedParkings = $staff->assignedParkings->pluck('id')->toArray();

        return view('modules.staff.edit', compact('staff', 'buses', 'parkings', 'assignedBuses', 'assignedParkings'));
    }

    public function update(UpdateStaffRequest $request, User $staff)
    {
        $merchant = auth()->user();
        if (!$merchant->staff->contains($staff->id)) abort(403);

        $staff->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $staff->update(['password' => Hash::make($request->password)]);
        }

        // Sync assignments (restricted to merchant's own buses/parkings)
        $staff->assignedBuses()->sync($request->buses ?? []);
        $staff->assignedParkings()->sync($request->parkings ?? []);

        return redirect()->route('staff.index')->with('success', 'Staff updated successfully.');
    }

    public function detach(User $staff)
    {
        $merchant = auth()->user();
        $merchant->staff()->detach($staff->id);
        
        // Also remove assignments related to this merchant's buses/parkings
        $staff->assignedBuses()->detach($merchant->buses->pluck('id'));
        $staff->assignedParkings()->detach($merchant->parkings->pluck('id'));

        return redirect()->route('staff.index')->with('success', 'Staff detached successfully.');
    }

    public function search(Request $request)
    {
        $search = $request->get('q');
        $merchantStaffIds = auth()->user()->staff()->pluck('users.id')->toArray();
        
        $users = User::whereHas('roles', fn($q) => $q->where('slug', 'staff'))
            ->where(function($q) use ($search) {
                $q->where('name', 'LIKE', "%$search%")
                  ->orWhere('email', 'LIKE', "%$search%");
            })
            ->whereNotIn('id', $merchantStaffIds)
            ->where('id', '!=', auth()->id())
            ->limit(10)
            ->get(['id', 'name', 'email']);

        return response()->json($users);
    }
}
