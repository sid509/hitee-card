<div class="mb-3">
    <label class="form-label" for="name">Parking Name</label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
        value="{{ old('name', $parking->name) }}" required />
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="location">Location</label>
    <input type="text" class="form-control @error('location') is-invalid @enderror" id="location" name="location" 
        value="{{ old('location', $parking->location) }}" required />
    @error('location') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

@if(auth()->user()->hasRole('super-admin'))
<div class="mb-3">
    <label class="form-label" for="merchant_id">Merchant</label>
    <select name="merchant_id" id="merchant_id" class="form-select @error('merchant_id') is-invalid @enderror">
        <option value="">Select Merchant</option>
        @foreach($merchants as $merchant)
            <option value="{{ $merchant->id }}" {{ old('merchant_id', $parking->merchant_id) == $merchant->id ? 'selected' : '' }}>
                {{ $merchant->name }}
            </option>
        @endforeach
    </select>
    @error('merchant_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>
@endif

<div class="mb-3">
    <label class="form-label" for="status">Status</label>
    <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
        <option value="opened" {{ old('status', $parking->status) == 'opened' ? 'selected' : '' }}>Opened</option>
        <option value="closed" {{ old('status', $parking->status) == 'closed' ? 'selected' : '' }}>Closed</option>
    </select>
    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2">Save Parking</button>
    <a href="{{ route('parkings.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
