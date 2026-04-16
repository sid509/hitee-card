<?php

namespace App\Http\Requests;

/**
 * Validation for issuing a new card
 */
class StoreCardRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'card_number' => 'required|string|unique:cards',
            'hwid' => 'required|string|unique:cards',
            'user_id' => 'nullable|exists:users,id',
            'status' => 'required|in:active,inactive,blocked',
            'is_currently_active' => 'boolean'
        ];
    }
}
