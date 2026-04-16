<div class="mb-3">
    <label class="form-label" for="card_number">Card Number</label>
    <input type="text" class="form-control @error('card_number') is-invalid @enderror" id="card_number" name="card_number" 
        value="{{ old('card_number', $card->card_number) }}" required />
    @error('card_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="hwid">HWID</label>
    <input type="text" class="form-control @error('hwid') is-invalid @enderror" id="hwid" name="hwid" 
        value="{{ old('hwid', $card->hwid) }}" required />
    @error('hwid') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="user_id">User (Customer)</label>
    <select name="user_id" id="user_id" class="form-select @error('user_id') is-invalid @enderror">
        <option value="">Select Customer</option>
        @foreach($users as $user)
            <option value="{{ $user->id }}" {{ old('user_id', $card->user_id) == $user->id ? 'selected' : '' }}>
                {{ $user->name }} ({{ $user->email }})
            </option>
        @endforeach
    </select>
    @error('user_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="status">Status</label>
    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
        <option value="active" {{ old('status', $card->status) == 'active' ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ old('status', $card->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
        <option value="blocked" {{ old('status', $card->status) == 'blocked' ? 'selected' : '' }}>Blocked</option>
    </select>
    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
    <button type="submit" class="btn btn-primary me-2">Save Card</button>
    <a href="{{ route('cards.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
