<div class="mb-3">
    <label class="form-label" for="card_number">Card Number</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-credit-card"></i></span>
        <input type="text" class="form-control @error('card_number') is-invalid @enderror" id="card_number" name="card_number" 
            value="{{ old('card_number', $card->card_number) }}" required />
    </div>
    @error('card_number') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="hwid">HWID</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-hash"></i></span>
        <input type="text" class="form-control @error('hwid') is-invalid @enderror" id="hwid" name="hwid" 
            value="{{ old('hwid', $card->hwid) }}" required />
    </div>
    @error('hwid') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="user_id">User (Customer)</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-user"></i></span>
        <select name="user_id" id="user_id" class="form-select select2-users @error('user_id') is-invalid @enderror">
            <option value="">Select Customer</option>
            @if(old('user_id', $card->user_id))
                @php $selectedUser = \App\Models\User::find(old('user_id', $card->user_id)); @endphp
                @if($selectedUser)
                    <option value="{{ $selectedUser->id }}" selected>{{ $selectedUser->name }} ({{ $selectedUser->email }})</option>
                @endif
            @elseif(request('user_id'))
                @php $selectedUser = \App\Models\User::find(request('user_id')); @endphp
                @if($selectedUser)
                    <option value="{{ $selectedUser->id }}" selected>{{ $selectedUser->name }} ({{ $selectedUser->email }})</option>
                @endif
            @endif
        </select>
    </div>
    @error('user_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="subscription_models">Subscription Models</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-purchase-tag"></i></span>
        <select name="subscription_models[]" id="subscription_models" class="form-select select2-subscriptions @error('subscription_models') is-invalid @enderror" multiple>
            @foreach(\App\Models\SubscriptionModel::where('is_active', true)->get() as $model)
                <option value="{{ $model->id }}" 
                    {{ (is_array(old('subscription_models', $card->subscriptionModels->pluck('id')->toArray())) && in_array($model->id, old('subscription_models', $card->subscriptionModels->pluck('id')->toArray()))) ? 'selected' : '' }}>
                    {{ $model->name }} (Rs. {{ $model->price }})
                </option>
            @endforeach
        </select>
    </div>
    <div class="form-text">Select one or more subscription models for this card.</div>
    @error('subscription_models') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="status">Status</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-info-circle"></i></span>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="active" {{ old('status', $card->status) == 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $card->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
            <option value="blocked" {{ old('status', $card->status) == 'blocked' ? 'selected' : '' }}>Blocked</option>
        </select>
    </div>
    @error('status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <div class="form-check">
        <input type="hidden" name="is_currently_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_currently_active" value="1" id="is_currently_active" 
            {{ old('is_currently_active', $card->is_currently_active) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_currently_active">
            Is Current Active Card for this User?
        </label>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2"><i class="bx bx-save me-1"></i> Save Card</button>
    <a href="{{ route('cards.index') }}" class="btn btn-label-secondary">Cancel</a>
</div>
