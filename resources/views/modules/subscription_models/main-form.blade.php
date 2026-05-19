<div class="mb-3">
    <label class="form-label" for="name">Subscription Model Name</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-credit-card"></i></span>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
            placeholder="E.g. Personalized Transit Card" value="{{ old('name', $subscriptionModel->name) }}" required />
    </div>
    @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="category">Category</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-category"></i></span>
        <select name="category" id="category" class="form-select @error('category') is-invalid @enderror" required>
            <option value="transit" {{ old('category', $subscriptionModel->category) == 'transit' ? 'selected' : '' }}>Transit</option>
            <option value="dine_in" {{ old('category', $subscriptionModel->category) == 'dine_in' ? 'selected' : '' }}>Dine In</option>
        </select>
    </div>
    @error('category') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="price">Issuance Price (pts/Rs)</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-dollar"></i></span>
        <input type="number" step="0.01" class="form-control @error('price') is-invalid @enderror" id="price" name="price" 
            placeholder="0.00" value="{{ old('price', $subscriptionModel->price) }}" required />
    </div>
    @error('price') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label">Status</label>
    <div class="form-check mt-2">
        <input type="hidden" name="is_active" value="0">
        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" 
            {{ old('is_active', $subscriptionModel->exists ? $subscriptionModel->is_active : true) ? 'checked' : '' }}>
        <label class="form-check-label" for="is_active">
            Active and available for issuance
        </label>
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2"><i class="bx bx-save me-1"></i> {{ $subscriptionModel->exists ? 'Update' : 'Save' }} Subscription Model</button>
    <a href="{{ route('subscription-models.index') }}" class="btn btn-label-secondary">Cancel</a>
</div>
