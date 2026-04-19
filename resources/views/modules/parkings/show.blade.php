@extends('layouts.app')

@section('title', 'Parking Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="py-3 mb-0"><span class="text-muted fw-light">Parking /</span> {{ $parking->name }}</h4>
        <a href="{{ route('parkings.index') }}" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="row">
        <!-- Parking Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar avatar-xl m-auto mb-3">
                            <span class="avatar-initial rounded-circle bg-label-info"><i class="bx bxs-parking bx-lg"></i></span>
                        </div>
                        <h5>{{ $parking->name }}</h5>
                        <span class="badge {{ $parking->status === 'opened' ? 'bg-label-success' : 'bg-label-secondary' }}">{{ strtoupper($parking->status) }}</span>
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
                            <li class="mb-3">
                                <span class="fw-medium me-2 text-primary">First Hour Fee:</span>
                                <span class="fw-bold">Rs. {{ number_format($parking->first_hour_fee, 2) }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2 text-primary">Onwards Fee:</span>
                                <span class="fw-bold">Rs. {{ number_format($parking->onwards_hour_fee, 2) }} /hr</span>
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
                                    <img src="{{ asset('storage/' . $attr->icon) }}" alt="{{ $attr->name }}" height="20" width="20" class="me-2">
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
            <div class="card bg-success text-white">
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
                    <i class="bx bxs-map me-2 text-info"></i>
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
                html: '<i class="bx bxs-parking bg-info text-white p-1 rounded-circle shadow" style="font-size: 24px; border: 2px solid white;"></i>',
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
