<?php

namespace App\Http\Controllers;

use App\Models\CardApplication;
use App\Models\Card;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CardApplicationController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = CardApplication::with('user');

            if (auth()->user()->hasRole('customers')) {
                $query->where('user_id', auth()->id());
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->editColumn('created_at', function($row){
                    return formatDate($row->created_at);
                })
                ->editColumn('type', function($row) {
                    return ucfirst($row->type);
                })
                ->editColumn('status', function($row) {
                    $class = [
                        'pending' => 'bg-label-warning',
                        'approved' => 'bg-label-success',
                        'rejected' => 'bg-label-danger'
                    ];
                    return '<span class="badge ' . $class[$row->status] . '">' . ucfirst($row->status) . '</span>';
                })
                ->addColumn('action', function($row) {
                    $actions = '<a href="' . route('card-applications.show', $row->id) . '" class="btn btn-icon btn-sm btn-dark me-1" title="View Details"><i class="bx bx-show"></i></a>';
                    if (auth()->user()->hasRole('super-admin') && $row->status === 'pending') {
                        $actions .= '<a href="' . route('cards.create', ['application_id' => $row->id]) . '" class="btn btn-icon btn-sm btn-primary" title="Issue Card"><i class="bx bx-credit-card"></i></a>';
                    }
                    return $actions;
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('modules.card_applications.index');
    }

    public function create()
    {
        return view('modules.card_applications.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:personalized,non-personalized',
            'full_name' => 'required_if:type,personalized|string|max:255',
            'id_type' => 'required_if:type,personalized|string|max:255',
            'id_number' => 'required_if:type,personalized|string|max:255',
            'kyc_document' => 'required_if:type,personalized|image|max:2048',
        ]);

        $application = CardApplication::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'kyc_data' => $request->type === 'personalized' ? [
                'full_name' => $request->full_name,
                'id_type' => $request->id_type,
                'id_number' => $request->id_number,
            ] : null,
            'status' => 'pending'
        ]);

        if ($request->hasFile('kyc_document')) {
            $application->addMedia($request->file('kyc_document'), 'kyc_document');
        }

        logActivity('card_application', "User applied for a {$request->type} card", ['application_id' => $application->id]);

        return redirect()->route('card-applications.index')->with('success', 'Card application submitted successfully.');
    }

    public function show(CardApplication $cardApplication)
    {
        if (auth()->user()->hasRole('customers') && $cardApplication->user_id !== auth()->id()) abort(403);

        $cardApplication->load('user');
        return view('modules.card_applications.show', compact('cardApplication'));
    }

    public function update(Request $request, CardApplication $cardApplication)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_remarks' => 'nullable|string|max:1000',
            'card_number' => 'required_if:status,approved|string|exists:cards,card_number',
        ]);

        $data = [
            'status' => $request->status,
            'admin_remarks' => $request->admin_remarks,
            'processed_at' => now(),
        ];

        if ($request->status === 'approved') {
            $card = Card::where('card_number', $request->card_number)->first();
            
            // Check if card is already assigned
            if ($card->user_id) {
                return back()->with('error', 'This card is already assigned to another user.');
            }

            $data['card_id'] = $card->id;
            
            // Link card to user and set as active
            Card::where('user_id', $cardApplication->user_id)->update(['is_currently_active' => false]);
            $card->update([
                'user_id' => $cardApplication->user_id,
                'is_currently_active' => true,
                'status' => 'active'
            ]);
        }

        $cardApplication->update($data);

        logActivity('card_application_processed', "Card application {$request->status}", [
            'application_id' => $cardApplication->id,
            'status' => $request->status
        ]);

        return redirect()->route('card-applications.index')->with('success', 'Application processed successfully.');
    }
}
