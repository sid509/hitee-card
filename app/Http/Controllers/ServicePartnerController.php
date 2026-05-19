<?php

namespace App\Http\Controllers;

use App\Models\ServicePartner;
use App\Models\User;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\DB;

class ServicePartnerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = ServicePartner::with('merchant')->latest();
            
            // Limit to own service partners if merchant
            if (auth()->user()->hasRole('merchant')) {
                $query->where('merchant_id', auth()->id());
            } elseif (auth()->user()->hasRole('staff')) {
                $user = auth()->user();
                $query->whereIn('merchant_id', $user->merchants->pluck('id'));
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('status', function($row) {
                    $class = $row->status === 'active' ? 'bg-label-success' : 'bg-label-secondary';
                    return '<span class="badge ' . $class . '">' . ucfirst($row->status) . '</span>';
                })
                ->addColumn('action', function($row){
                    $canEdit = auth()->user()->hasRole('super-admin', 'merchant', 'staff');
                    $canDelete = auth()->user()->hasRole('super-admin');
                    
                    $actions = '';
                    $actions .= '<a href="'.route('service-partners.show', $row->id).'" class="btn btn-icon btn-sm btn-dark me-1" title="View"><i class="bx bx-show"></i></a>';

                    if ($canEdit) {
                        $actions .= '<a href="'.route('service-partners.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    }
                    if ($canDelete) {
                        $actions .= '<form action="'.route('service-partners.destroy', $row->id).'" method="POST" style="display:inline-block">
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

        return view('modules.service_partners.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $servicePartner = new ServicePartner();
        $merchants = User::whereHas('roles', function($q){ $q->where('slug', 'merchant'); })->get();
        return view('modules.service_partners.create', compact('servicePartner', 'merchants'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'merchant_id' => 'required|exists:users,id',
            'service_type' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        ServicePartner::create($request->all());

        return redirect()->route('service-partners.index')->with('success', 'Service Partner created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(ServicePartner $servicePartner)
    {
        $user = auth()->user();
        if ($user->hasRole('merchant') && $servicePartner->merchant_id != $user->id) abort(403);
        
        $servicePartner->load(['merchant', 'discounts.subscriptionModel']);
        return view('modules.service_partners.show', compact('servicePartner'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ServicePartner $servicePartner)
    {
        $user = auth()->user();
        if ($user->hasRole('merchant') && $servicePartner->merchant_id != $user->id) abort(403);
        
        $merchants = User::whereHas('roles', function($q){ $q->where('slug', 'merchant'); })->get();
        return view('modules.service_partners.edit', compact('servicePartner', 'merchants'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ServicePartner $servicePartner)
    {
        $user = auth()->user();
        if ($user->hasRole('merchant') && $servicePartner->merchant_id != $user->id) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'service_type' => 'required|string',
            'status' => 'required|in:active,inactive',
        ]);

        $servicePartner->update($request->all());

        return redirect()->route('service-partners.index')->with('success', 'Service Partner updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ServicePartner $servicePartner)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        
        $servicePartner->delete();
        return redirect()->route('service-partners.index')->with('success', 'Service Partner deleted successfully.');
    }

    /**
     * Store a new discount rule for the partner.
     */
    public function storeDiscount(Request $request, ServicePartner $servicePartner)
    {
        $user = auth()->user();
        if ($user->hasRole('merchant') && $servicePartner->merchant_id != $user->id) abort(403);

        $request->validate([
            'subscription_model_id' => 'nullable|exists:subscription_models,id',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_spend' => 'nullable|numeric|min:0',
            'description' => 'nullable|string|max:255',
        ]);

        $servicePartner->discounts()->create($request->all());

        return redirect()->back()->with('success', 'Discount rule added successfully.');
    }

    /**
     * Remove a discount rule.
     */
    public function destroyDiscount(ServicePartner $servicePartner, \App\Models\SubscriptionDiscount $discount)
    {
        $user = auth()->user();
        if ($user->hasRole('merchant') && $servicePartner->merchant_id != $user->id) abort(403);
        if ($discount->service_partner_id != $servicePartner->id) abort(404);

        $discount->delete();

        return redirect()->back()->with('success', 'Discount rule removed successfully.');
    }
}
