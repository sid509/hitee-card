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

<div class="mb-3">
    <label class="form-label" for="total_capacity">Total Capacity</label>
    <input type="number" class="form-control @error('total_capacity') is-invalid @enderror" id="total_capacity" name="total_capacity" 
        value="{{ old('total_capacity', $parking->total_capacity) }}" required min="0" />
    @error('total_capacity') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

<div class="row">
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label" for="first_hour_fee">First Hour Fee (Rs.)</label>
            <input type="number" step="0.01" class="form-control @error('first_hour_fee') is-invalid @enderror" 
                id="first_hour_fee" name="first_hour_fee" value="{{ old('first_hour_fee', $parking->first_hour_fee) }}" required />
            @error('first_hour_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="mb-3">
            <label class="form-label" for="onwards_hour_fee">Onwards Hour Fee (Rs./hr)</label>
            <input type="number" step="0.01" class="form-control @error('onwards_hour_fee') is-invalid @enderror" 
                id="onwards_hour_fee" name="onwards_hour_fee" value="{{ old('onwards_hour_fee', $parking->onwards_hour_fee) }}" required />
            @error('onwards_hour_fee') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
</div>

<div class="mb-3">
    <label class="form-label" for="attributes">Facilities & Attributes</label>
    <select name="attributes[]" id="attributes" class="form-select select2 @error('attributes') is-invalid @enderror" multiple>
        @foreach($allAttributes as $attribute)
            <option value="{{ $attribute->id }}" {{ (is_array(old('attributes')) && in_array($attribute->id, old('attributes'))) || ($parking->attributes->contains($attribute->id)) ? 'selected' : '' }}>
                {{ $attribute->name }}
            </option>
        @endforeach
    </select>
    @error('attributes') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="featured_image">Featured Image</label>
    @if($parking->featured_image_url)
        <div class="mb-2">
            <img src="{{ $parking->featured_image_url }}" alt="Featured" class="rounded" style="width: 100px; height: 100px; object-fit: cover;">
        </div>
    @endif
    <input type="file" class="form-control @error('featured_image') is-invalid @enderror" id="featured_image" name="featured_image" accept="image/*" />
    @error('featured_image') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="gallery_images">Gallery Images</label>
    <div class="d-flex flex-wrap gap-2 mb-2">
        @foreach($parking->media()->where('collection_name', 'gallery')->get() as $image)
            <img src="{{ $image->url }}" alt="Gallery" class="rounded" style="width: 60px; height: 60px; object-fit: cover;">
        @endforeach
    </div>
    <input type="file" class="form-control @error('gallery_images') is-invalid @enderror" id="gallery_images" name="gallery_images[]" multiple accept="image/*" />
    @error('gallery_images') <div class="invalid-feedback">{{ $message }}</div> @enderror
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2">Save Parking</button>
    <a href="{{ route('parkings.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>

@push('page-js')
<script type="module">
    $(function() {
        if ($('.select2').length) {
            $('.select2').select2({
                placeholder: 'Select Facilities',
                width: '100%'
            });
        }
    });
</script>
@endpush
