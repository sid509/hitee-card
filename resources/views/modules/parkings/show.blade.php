@extends('layouts.app')

@section('title', 'Parking Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="py-3 mb-0"><span class="text-muted fw-light">Parking /</span> {{ $parking->name }}</h4>
        <div class="d-flex gap-2">
            @if(auth()->user()->hasRole('super-admin', 'merchant'))
                <a href="{{ route('parkings.edit', $parking->id) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt me-1"></i> Edit Parking
                </a>
            @endif
            <a href="{{ route('parkings.index') }}" class="btn btn-secondary">Back to List</a>
        </div>
    </div>

    <div class="row">
        <!-- Parking Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="mb-4">
                        <div class="featured-image-container mb-3">
                            <img src="{{ $parking->featured_image_url }}" alt="Parking" class="img-fluid rounded w-100" style="max-height: 250px; object-fit: cover;">
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-1">{{ $parking->name }}</h4>
                                <span class="badge {{ $parking->status === 'opened' ? 'bg-label-success' : 'bg-label-secondary' }}">{{ __('messages.' . $parking->status) }}</span>
                            </div>
                            <div class="avatar avatar-md">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bxs-parking"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="info-container">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <span class="fw-medium me-2">Location:</span>
                                <span>{{ $parking->location }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Merchant:</span>
                                <span>{{ $parking->merchant->name ?? 'N/A' }}</span>
                            </li>
                            <li class="mt-4 mb-2">
                                <h6 class="text-primary border-bottom pb-2">Fee Structure</h6>
                            </li>
                            @forelse($parking->fees as $fee)
                            <li class="mb-3 d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="fw-medium d-block">{{ $fee->title }}</span>
                                    @if($fee->subtitle)
                                    <small class="text-muted">{{ $fee->subtitle }}</small>
                                    @endif
                                </div>
                                <div class="text-end">
                                    <span class="fw-bold d-block text-primary">Rs. {{ number_format($fee->price_rs, 2) }}</span>
                                    <small class="text-muted">{{ number_format($fee->price_pts, 0) }} pts</small>
                                </div>
                            </li>
                            @empty
                            <li class="text-muted small">No fees defined.</li>
                            @endforelse
                            <li class="mt-4 mb-3">
                                <span class="fw-medium me-2">Current Occupancy:</span>
                                <span class="badge {{ $parking->ongoing_rides_count >= $parking->total_capacity && $parking->total_capacity > 0 ? 'bg-label-danger' : 'bg-label-info' }}">
                                    {{ $parking->ongoing_rides_count }} / {{ $parking->total_capacity ?: 'N/A' }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header pb-2">
                    <h6 class="mb-0">Facilities & Attributes</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2">
                        @forelse($parking->attributes as $attr)
                            <div class="d-flex align-items-center bg-label-primary px-3 py-2 rounded">
                                @if($attr->icon)
                                    <img src="{{ $attr->icon_url }}" alt="{{ $attr->name }}" height="20" width="20" class="me-2">
                                @else
                                    <i class="bx bx-check-circle me-2"></i>
                                @endif
                                <span class="fw-medium small">{{ $attr->name }}</span>
                            </div>
                        @empty
                            <span class="text-muted small">No specific attributes listed.</span>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Income Card -->
            <!-- Gallery Card -->
            @php $gallery = $parking->media()->where('collection_name', 'gallery')->get(); @endphp
            @if($gallery->count() > 0)
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Gallery</h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        @foreach($gallery as $image)
                        <div class="col-4">
                            <a href="{{ $image->url }}" target="_blank">
                                <img src="{{ $image->url }}" alt="Gallery" class="img-fluid rounded shadow-sm" style="height: 80px; width: 100%; object-fit: cover;">
                            </a>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1">Total Lifetime Income</h6>
                            <h4 class="text-white mb-0">Rs. {{ number_format($parking->totalIncome(), 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-white text-success"><i class="bx bx-trending-up"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Map and History -->
        <div class="col-md-8">
            <div class="card mb-4">
                <h5 class="card-header d-flex align-items-center">
                    <i class="bx bx-map-pin me-2 text-primary"></i>
                    Location Visualization
                </h5>
                <div class="card-body">
                    <div id="parking-map" style="height: 350px; border-radius: 8px; border: 1px solid #eee;"></div>
                </div>
            </div>

            <div class="card">
                <h5 class="card-header">Parking Income History</h5>
                <div class="card-body">
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover parking-income-table w-100">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function () {
        const parking = @json($parking);

        const map = L.map('parking-map').setView([parking.latitude || 27.7172, parking.longitude || 85.3240], 16);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        if (parking.latitude && parking.longitude) {
            const parkingIcon = L.divIcon({
                html: '<i class="bx bxs-parking bg-primary text-white p-1 rounded-circle shadow" style="font-size: 24px; border: 2px solid white;"></i>',
                className: 'custom-div-icon',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            L.marker([parking.latitude, parking.longitude], {icon: parkingIcon}).addTo(map).bindPopup(`<strong>${parking.name}</strong>`);
        }

        $('.parking-income-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('merchant.income') }}",
                data: function(d) {
                    d.reference_id = "{{ $parking->id }}";
                    d.reference_type = "App\\Models\\Parking";
                }
            },
            columns: [
                {data: 'created_at', name: 'created_at'},
                {data: 'customer', name: 'customer'},
                {data: 'amount', name: 'amount'},
            ],
            order: [[0, 'desc']]
        });
    });
</script>
@endpush
