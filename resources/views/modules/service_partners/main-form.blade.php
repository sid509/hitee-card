<div class="mb-3">
    <label class="form-label" for="name">Partner Name</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-store"></i></span>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
            placeholder="E.g. Himalayan Java" value="{{ old('name', $servicePartner->name) }}" required />
    </div>
    @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

@if(auth()->user()->hasRole('super-admin'))
<div class="mb-3">
    <label class="form-label" for="merchant_id">Merchant Owner</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-user"></i></span>
        <select name="merchant_id" id="merchant_id" class="form-select @error('merchant_id') is-invalid @enderror" required>
            <option value="">Select Merchant</option>
            @foreach($merchants as $merchant)
                <option value="{{ $merchant->id }}" {{ old('merchant_id', $servicePartner->merchant_id) == $merchant->id ? 'selected' : '' }}>
                    {{ $merchant->name }} ({{ $merchant->email }})
                </option>
            @endforeach
        </select>
    </div>
    @error('merchant_id') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>
@else
    <input type="hidden" name="merchant_id" value="{{ auth()->id() }}">
@endif

<div class="mb-3">
    <label class="form-label" for="service_type">Service Category</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-category"></i></span>
        <input type="text" class="form-control @error('service_type') is-invalid @enderror" id="service_type" name="service_type" 
            placeholder="E.g. Coffee Shop, Restaurant" value="{{ old('service_type', $servicePartner->service_type) }}" required />
    </div>
    @error('service_type') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="latitude">Latitude</label>
        <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="bx bx-current-location"></i></span>
            <input type="text" class="form-control @error('latitude') is-invalid @enderror" id="latitude" name="latitude" 
                placeholder="27.123456" value="{{ old('latitude', $servicePartner->latitude) }}" />
        </div>
        @error('latitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="longitude">Longitude</label>
        <div class="input-group input-group-merge">
            <span class="input-group-text"><i class="bx bx-current-location"></i></span>
            <input type="text" class="form-control @error('longitude') is-invalid @enderror" id="longitude" name="longitude" 
                placeholder="85.123456" value="{{ old('longitude', $servicePartner->longitude) }}" />
        </div>
        @error('longitude') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="status">Status</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-check-shield"></i></span>
        <select name="status" id="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="active" {{ old('status', $servicePartner->status) == 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ old('status', $servicePartner->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </div>
    @error('status') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2"><i class="bx bx-save me-1"></i> {{ $servicePartner->exists ? 'Update' : 'Save' }} Partner</button>
    <a href="{{ route('service-partners.index') }}" class="btn btn-label-secondary">Cancel</a>
</div>
