<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionModel;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class SubscriptionModelController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        if ($request->ajax()) {
            $query = SubscriptionModel::query();
            
            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('price', function($row) {
                    return 'Rs. ' . number_format($row->price, 2);
                })
                ->editColumn('is_active', function($row) {
                    $class = $row->is_active ? 'bg-label-success' : 'bg-label-secondary';
                    $status = $row->is_active ? 'Active' : 'Inactive';
                    return '<span class="badge ' . $class . '">' . $status . '</span>';
                })
                ->addColumn('action', function($row){
                    $actions = '';
                    $actions .= '<a href="'.route('subscription-models.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                    $actions .= '<form action="'.route('subscription-models.destroy', $row->id).'" method="POST" style="display:inline-block">
                                    '.csrf_field().'
                                    '.method_field('DELETE').'
                                    <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                </form>';
                    return $actions;
                })
                ->rawColumns(['is_active', 'action'])
                ->make(true);
        }

        return view('modules.subscription_models.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $subscriptionModel = new SubscriptionModel();
        return view('modules.subscription_models.create', compact('subscriptionModel'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:transit,dine_in',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean'
        ]);

        SubscriptionModel::create($request->all());

        return redirect()->route('subscription-models.index')->with('success', 'Subscription Model created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SubscriptionModel $subscriptionModel)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        return view('modules.subscription_models.edit', compact('subscriptionModel'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SubscriptionModel $subscriptionModel)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'name' => 'required|string|max:255',
            'category' => 'required|in:transit,dine_in',
            'price' => 'required|numeric|min:0',
            'is_active' => 'boolean'
        ]);

        $subscriptionModel->update($request->all());

        return redirect()->route('subscription-models.index')->with('success', 'Subscription Model updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SubscriptionModel $subscriptionModel)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        
        if ($subscriptionModel->cards()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete subscription model because it is assigned to existing cards.');
        }

        $subscriptionModel->delete();
        return redirect()->route('subscription-models.index')->with('success', 'Subscription Model deleted successfully.');
    }
}
