<?php

namespace App\Http\Controllers;

use App\Models\Card;
use App\Models\User;
use App\Http\Requests\StoreCardRequest;
use App\Http\Requests\UpdateCardRequest;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CardController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     * Handles DataTable AJAX requests and initial page load.
     */
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $query = Card::with('user');
            
            // Limit to own cards if customer
            if (auth()->user()->hasRole('customers')) {
                $query->where('user_id', auth()->id());
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('is_active_badge', function($row){
                    return $row->is_currently_active 
                        ? '<span class="badge bg-label-success">Active</span>' 
                        : '<span class="badge bg-label-secondary">Inactive</span>';
                })
                ->addColumn('action', function($row){
                    $actions = '';
                    // Super Admin actions
                    if (auth()->user()->hasRole('super-admin')) {
                        // Edit Button
                        $actions .= '<a href="'.route('cards.edit', $row->id).'" class="btn btn-icon btn-sm btn-primary me-1" title="Edit"><i class="bx bx-edit-alt"></i></a>';
                        // Delete Button
                        $actions .= '<form action="'.route('cards.destroy', $row->id).'" method="POST" style="display:inline-block">
                                        '.csrf_field().'
                                        '.method_field('DELETE').'
                                        <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn" title="Delete"><i class="bx bx-trash"></i></button>
                                    </form>';
                    } elseif (auth()->user()->hasRole('customers')) {
                        // Customer action
                        $canEnable = !$row->is_currently_active || $row->status !== 'active';
                        $canDisable = $row->is_currently_active && $row->status === 'active';
                        $canUpgrade = $row->status === 'active' && $row->is_currently_active;

                        if ($canEnable || $canDisable || $canUpgrade) {
                            $actions .= '<button class="btn btn-sm btn-outline-primary btn-request-change" 
                                            data-id="'.$row->id.'" 
                                            data-card-number="'.$row->card_number.'"
                                            data-can-enable="'.($canEnable ? '1' : '0').'"
                                            data-can-disable="'.($canDisable ? '1' : '0').'"
                                            data-can-upgrade="'.($canUpgrade ? '1' : '0').'">
                                            Request Change
                                         </button>';
                        } else {
                            $actions .= '<span class="text-muted small">No actions available</span>';
                        }
                    }
                    return $actions;
                })
                ->rawColumns(['action', 'is_active_badge'])
                ->make(true);
        }

        return view('modules.cards.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $card = new Card();
        $users = User::whereHas('roles', function($q){ $q->where('slug', 'customers'); })->get();
        return view('modules.cards.create', compact('card', 'users'));
    }

    /**
     * Store a newly created resource in storage.
     * 
     * Uses StoreCardRequest for validation.
     */
    public function store(StoreCardRequest $request)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        $data = $request->all();
        // If this card is being set as active for a user, deactivate their other cards
        if ($request->is_currently_active && $request->user_id) {
            Card::where('user_id', $request->user_id)->update(['is_currently_active' => false]);
        }

        Card::create($data);

        return redirect()->route('cards.index')->with('success', 'Card issued successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Card $card)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $users = User::whereHas('roles', function($q){ $q->where('slug', 'customers'); })->get();
        return view('modules.cards.edit', compact('card', 'users'));
    }

    /**
     * Update the specified resource in storage.
     * 
     * Uses UpdateCardRequest for validation.
     */
    public function update(UpdateCardRequest $request, Card $card)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);

        // If this card is being set as active for a user, deactivate their other cards
        if ($request->is_currently_active && $request->user_id) {
            Card::where('user_id', $request->user_id)->where('id', '!=', $card->id)->update(['is_currently_active' => false]);
        }

        $card->update($request->all());

        return redirect()->route('cards.index')->with('success', 'Card updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Card $card)
    {
        if (!auth()->user()->hasRole('super-admin')) abort(403);
        $card->delete();
        return redirect()->route('cards.index')->with('success', 'Card deleted successfully.');
    }

    /**
     * Request a change for the card (Customer only)
     */
    public function requestChange(Request $request, Card $card)
    {
        // Ensure customer only requests for their own card
        if (auth()->user()->hasRole('customers') && $card->user_id != auth()->id()) abort(403);

        $request->validate([
            'type' => 'required|in:upgrade,enable,disable',
            'message' => 'nullable|string|max:1000'
        ]);

        $typeLabel = 'Unknown';
        if ($request->type === 'upgrade') $typeLabel = 'Card Upgrade';
        elseif ($request->type === 'enable') $typeLabel = 'Card Activation';
        elseif ($request->type === 'disable') $typeLabel = 'Card Deactivation';
        
        \App\Models\SupportRequest::create([
            'user_id' => auth()->id(),
            'subject' => "Card Change Request: {$typeLabel}",
            'message' => "Request for [{$typeLabel}] for Card: {$card->card_number}. " . ($request->message ?? ''),
            'status' => 'open'
        ]);

        logActivity('card_request', "User requested card {$request->type}", [
            'card_id' => $card->id,
            'card_number' => $card->card_number,
            'request_type' => $request->type
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Your request has been submitted successfully.'
        ]);
    }
}
