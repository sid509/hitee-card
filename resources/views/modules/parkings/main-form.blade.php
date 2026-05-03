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

<div class="card mb-4 border shadow-none">
    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
        <h6 class="mb-0">Parking Fee Tiers</h6>
        <button type="button" class="btn btn-xs btn-primary" id="add-fee-tier">
            <i class="bx bx-plus me-1"></i> Add Tier
        </button>
    </div>
    <div class="card-body pt-3" id="fee-tiers-container">
        @php 
            $fees = old('fees', $parking->fees->count() > 0 ? $parking->fees->toArray() : [['title' => '1st Hour', 'subtitle' => 'Entry fee', 'price_rs' => 0, 'price_pts' => 0]]);
        @endphp
        
        @foreach($fees as $index => $fee)
            <div class="fee-tier-row mb-3 pb-3 border-bottom position-relative">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Title (e.g. 1st Hour)</label>
                        <input type="text" name="fees[{{ $index }}][title]" class="form-control form-control-sm" value="{{ $fee['title'] }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small">Subtitle (Optional)</label>
                        <input type="text" name="fees[{{ $index }}][subtitle]" class="form-control form-control-sm" value="{{ $fee['subtitle'] ?? '' }}">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Price (Rs.)</label>
                        <input type="number" step="0.01" name="fees[{{ $index }}][price_rs]" class="form-control form-control-sm" value="{{ $fee['price_rs'] }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small">Price (Pts)</label>
                        <input type="number" step="0.01" name="fees[{{ $index }}][price_pts]" class="form-control form-control-sm" value="{{ $fee['price_pts'] }}" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-fee-tier w-100" {{ count($fees) <= 1 ? 'disabled' : '' }}>
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    @error('fees') <div class="px-3 pb-2 text-danger small">{{ $message }}</div> @enderror
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

        // Dynamic Fee Tiers Logic
        let tierIndex = {{ count($fees) }};

        $('#add-fee-tier').on('click', function() {
            const newRow = `
                <div class="fee-tier-row mb-3 pb-3 border-bottom position-relative">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label small">Title (e.g. 1st Hour)</label>
                            <input type="text" name="fees[${tierIndex}][title]" class="form-control form-control-sm" placeholder="e.g. 2nd Hour" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Subtitle (Optional)</label>
                            <input type="text" name="fees[${tierIndex}][subtitle]" class="form-control form-control-sm" placeholder="e.g. Standard rate">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Price (Rs.)</label>
                            <input type="number" step="0.01" name="fees[${tierIndex}][price_rs]" class="form-control form-control-sm" value="0" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Price (Pts)</label>
                            <input type="number" step="0.01" name="fees[${tierIndex}][price_pts]" class="form-control form-control-sm" value="0" required>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-fee-tier w-100">
                                <i class="bx bx-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            $('#fee-tiers-container').append(newRow);
            tierIndex++;
            updateRemoveButtons();
        });

        $(document).on('click', '.remove-fee-tier', function() {
            if ($('.fee-tier-row').length > 1) {
                $(this).closest('.fee-tier-row').remove();
                updateRemoveButtons();
            }
        });

        function updateRemoveButtons() {
            const rows = $('.fee-tier-row');
            if (rows.length <= 1) {
                rows.find('.remove-fee-tier').prop('disabled', true);
            } else {
                rows.find('.remove-fee-tier').prop('disabled', false);
            }
        }
    });
</script>
@endpush
