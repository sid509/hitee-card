<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validation for updating an existing card
 */
class UpdateCardRequest extends BaseRequest
{
    public function rules(): array
    {
        $card = $this->route('card');
        $cardId = $card ? $card->id : null;

        return [
            'card_number' => [
                'required',
                'string',
                Rule::unique('cards')->ignore($cardId),
            ],
            'hwid' => [
                'required',
                'string',
                Rule::unique('cards')->ignore($cardId),
            ],
            'user_id' => 'nullable|exists:users,id',
            'subscription_models' => 'nullable|array',
            'subscription_models.*' => 'exists:subscription_models,id',
            'status' => 'required|in:active,inactive,blocked',
            'is_currently_active' => 'boolean'
        ];
    }
}
