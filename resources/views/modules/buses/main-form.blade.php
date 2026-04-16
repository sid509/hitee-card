<div class="mb-3">
    <label class="form-label" for="name">Bus Name</label>
    <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
        value="{{ old('name', $bus->name) }}" required />
    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="bus_number">Bus Number</label>
    <input type="text" class="form-control @error('bus_number') is-invalid @enderror" id="bus_number" name="bus_number" 
        value="{{ old('bus_number', $bus->bus_number) }}" {{ auth()->user()->hasRole('super-admin') ? 'required' : 'readonly' }} />
    @error('bus_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="hwid">HWID</label>
    <input type="text" class="form-control @error('hwid') is-invalid @enderror" id="hwid" name="hwid" 
        value="{{ old('hwid', $bus->hwid) }}" {{ auth()->user()->hasRole('super-admin') ? 'required' : 'readonly' }} />
    @error('hwid') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

@if(auth()->user()->hasRole('super-admin'))
<div class="mb-3">
    <label class="form-label" for="merchant_id">Merchant</label>
    <select name="merchant_id" id="merchant_id" class="form-select @error('merchant_id') is-invalid @enderror">
        <option value="">Select Merchant</option>
        @foreach($merchants as $merchant)
            <option value="{{ $merchant->id }}" {{ old('merchant_id', $bus->merchant_id) == $merchant->id ? 'selected' : '' }}>
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
        <option value="active" {{ old('status', $bus->status) == 'active' ? 'selected' : '' }}>Active</option>
        <option value="inactive" {{ old('status', $bus->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
    </select>
    @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2">Save Bus</button>
    <a href="{{ route('buses.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
