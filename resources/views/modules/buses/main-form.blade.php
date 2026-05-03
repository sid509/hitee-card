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
    <label class="form-label" for="total_capacity">Total Capacity</label>
    <input type="number" class="form-control @error('total_capacity') is-invalid @enderror" id="total_capacity" name="total_capacity" 
        value="{{ old('total_capacity', $bus->total_capacity) }}" required min="0" />
    @error('total_capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
    <select name="merchant_id" id="merchant_id" class="form-select select2-ajax-merchant @error('merchant_id') is-invalid @enderror">
        <option value="">Select Merchant</option>
        @if(old('merchant_id', $bus->merchant_id))
            @php $selectedMerchant = \App\Models\User::find(old('merchant_id', $bus->merchant_id)); @endphp
            @if($selectedMerchant)
                <option value="{{ $selectedMerchant->id }}" selected>{{ $selectedMerchant->name }} ({{ $selectedMerchant->email }})</option>
            @endif
        @endif
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

<div class="mb-3">
    <label class="form-label" for="route_id">Route</label>
    <select name="route_id" id="route_id" class="form-select select2 @error('route_id') is-invalid @enderror">
        <option value="">Select Route</option>
        @foreach($routes as $route)
            <option value="{{ $route->id }}" {{ old('route_id', $bus->route_id) == $route->id ? 'selected' : '' }}>
                {{ $route->name }}
            </option>
        @endforeach
    </select>
    @error('route_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="featured_image">Featured Image</label>
    @if($bus->featured_image_url)
        <div class="mb-2">
            <img src="{{ $bus->featured_image_url }}" alt="Featured" class="rounded" style="width: 100px; height: 100px; object-fit: cover;">
        </div>
    @endif
    <input type="file" class="form-control @error('featured_image') is-invalid @enderror" id="featured_image" name="featured_image" accept="image/*" />
    @error('featured_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="gallery_images">Gallery Images</label>
    <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach($bus->media()->where('collection_name', 'gallery')->get() as $image)
            <img src="{{ $image->url }}" alt="Gallery" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
        @endforeach
    </div>
    <input type="file" class="form-control @error('gallery_images') is-invalid @enderror" id="gallery_images" name="gallery_images[]" multiple accept="image/*" />
    @error('gallery_images') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2">Save Bus</button>
    <a href="{{ route('buses.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
