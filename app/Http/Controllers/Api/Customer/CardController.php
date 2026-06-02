<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Card;
use Illuminate\Http\Request;

/**
 * @group CustomerApi
 * @subgroup Card
 */
class CardController extends Controller
{
    /**
     * Link a physical or virtual card to the authenticated user.
     */
    public function link(Request $request)
    {
        $request->validate([
            'card_number' => 'required_without:hwid|string',
            'hwid'        => 'required_without:card_number|string',
        ]);

        $user = $request->user();

        // Check if user already has a card
        if ($user->cards()->exists()) {
            return apiResponse(false, 'You already have a card linked to your account.', '', 400);
        }

        $query = Card::query();
        
        if ($request->card_number) {
            $query->where('card_number', $request->card_number);
        } else {
            $query->where('hwid', $request->hwid);
        }

        $card = $query->first();

        if (!$card) {
            return apiResponse(false, 'Card not found', '', 404);
        }

        if ($card->user_id && $card->user_id != $request->user()->id) {
            return apiResponse(false, 'This card is already linked to another user', '', 400);
        }

        if ($card->user_id == $request->user()->id) {
            return apiResponse(true, 'Card is already linked to your account', [
                'card_number' => $card->card_number,
                'status'      => $card->status,
            ]);
        }

        // Link the card
        $card->update([
            'user_id' => $request->user()->id,
            'is_currently_active' => true // Optionally make it active
        ]);

        // Ensure other cards of this user are deactivated if this is the only active one
        $request->user()->cards()->where('id', '!=', $card->id)->update(['is_currently_active' => false]);

        logActivity('card_linked', 'User linked a card: ' . $card->card_number, [], $request->user()->id);

        return apiResponse(true, 'Card linked successfully', [
            'card_number' => $card->card_number,
            'status'      => $card->status,
        ]);
    }

    /**
     * Unlink a card from the authenticated user.
     */
    public function unlink(Request $request, $id)
    {
        $card = $request->user()->cards()->findOrFail($id);

        $card->update([
            'user_id' => null,
            'is_currently_active' => false
        ]);

        logActivity('card_unlinked', 'User unlinked a card: ' . $card->card_number, [], $request->user()->id);

        return apiResponse(true, 'Card unlinked successfully');
    }
}
