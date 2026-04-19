@extends('layouts.app')

@section('title', 'Bus Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="py-3 mb-0"><span class="text-muted fw-light">Bus /</span> {{ $bus->name }}</h4>
        <a href="{{ route('buses.index') }}" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="row">
        <!-- Bus Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar avatar-xl m-auto mb-3">
                            <img src="{{ $bus->featured_image_url }}" alt="Bus" class="rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">
                        </div>
                        <h5>{{ $bus->name }}</h5>
                        <span class="badge {{ $bus->status === 'active' ? 'bg-label-success' : 'bg-label-secondary' }}">{{ __('messages.' . $bus->status) }}</span>
                    </div>
                    <div class="info-container">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <span class="fw-medium me-2">Bus Number:</span>
                                <span>{{ $bus->bus_number }}</span>
                            </li>
                            @if(!auth()->user()->hasRole('customers'))
                            <li class="mb-3">
                                <span class="fw-medium me-2">Hardware ID:</span>
                                <span>{{ $bus->hwid }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Merchant:</span>
                                <span>{{ $bus->merchant->name ?? 'N/A' }}</span>
                            </li>
                            @endif
                            <li class="mb-3">
                                <span class="fw-medium me-2">Route:</span>
                                <span>{{ $bus->route->name ?? 'Not Assigned' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Fare Info Card -->
            @if($bus->activeFare)
            <div class="card mb-4 border-primary shadow-none">
                <div class="card-header bg-primary text-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="text-white mb-0">Active Fare</h6>
                    <i class="bx bx-info-circle"></i>
                </div>
                <div class="card-body pt-3">
                    <h6 class="mb-1">{{ $bus->activeFare->name }}</h6>
                    <p class="small text-muted mb-0">Effective From: {{ $bus->activeFare->effective_from ? formatDate($bus->activeFare->effective_from) : 'Immediate' }}</p>
                    <button class="btn btn-sm btn-outline-primary mt-3 w-100" data-bs-toggle="modal" data-bs-target="#viewFareMatrixModal">
                        <i class="bx bx-table me-1"></i> View Pricing Matrix
                    </button>
                </div>
            </div>
            @endif

            <!-- Gallery Card -->
            @php $gallery = $bus->media()->where('collection_name', 'gallery')->get(); @endphp
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

            <!-- Income Card (Admin/Merchant Only) -->
            @if(!auth()->user()->hasRole('customers'))
            <div class="card bg-success text-white mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1">Lifetime Income</h6>
                            <h4 class="text-white mb-0">Rs. {{ number_format($bus->totalIncome(), 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-white text-success"><i class="bx bx-trending-up"></i></span>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>

        <!-- Map and Route -->
        <div class="col-md-8">
            <div class="card mb-4">
                <h5 class="card-header d-flex align-items-center">
                    <i class="bx bx-map-pin me-2 text-primary"></i>
                    {{ $bus->route ? 'Route Plan: ' . $bus->route->name : 'Asset Location' }}
                </h5>
                <div class="card-body">
                    <div id="bus-map" style="height: 400px; border-radius: 8px; border: 1px solid #eee;"></div>
                </div>
            </div>

            <!-- Route Stops List -->
            @if($bus->route && $bus->route->stops->count() > 0)
            <div class="card mb-4">
                <h5 class="card-header">Route Stops Sequence</h5>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @foreach($bus->route->stops as $index => $stop)
                            <div class="list-group-item d-flex align-items-center border-0 px-0 py-3">
                                <div class="me-3 position-relative">
                                    <span class="badge rounded-pill bg-label-primary px-2 py-1">{{ $index + 1 }}</span>
                                    @if(!$loop->last)
                                        <div class="position-absolute start-50 translate-middle-x bg-primary opacity-25" style="width: 2px; height: 30px; top: 100%;"></div>
                                    @endif
                                </div>
                                <div>
                                    <h6 class="mb-0">{{ $stop->stop_name }}</h6>
                                    @if($stop->latitude && $stop->longitude)
                                        <small class="text-muted">{{ $stop->latitude }}, {{ $stop->longitude }}</small>
                                    @endif
                                </div>
                                <div class="ms-auto">
                                    <button class="btn btn-icon btn-sm btn-outline-primary rounded-pill" onclick="focusStop({{ $stop->latitude }}, {{ $stop->longitude }}, '{{ $stop->stop_name }}')">
                                        <i class="bx bx-map-alt"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <!-- Income History (Admin/Merchant Only) -->
            @if(!auth()->user()->hasRole('customers'))
            <div class="card">
                <h5 class="card-header">Transaction History</h5>
                <div class="card-body">
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover bus-income-table w-100">
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
            @endif
        </div>
    </div>
</div>
@endsection

@push('modals')
@if($bus->activeFare)
<!-- Fare Matrix Modal -->
<div class="modal fade" id="viewFareMatrixModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pricing Matrix: {{ $bus->activeFare->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm text-center">
                        <thead class="table-light">
                            <tr>
                                <th>From \ To</th>
                                @foreach($bus->activeFare->route->stops as $stop)
                                    <th>{{ $stop->stop_name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($bus->activeFare->route->stops as $fromStop)
                                <tr>
                                    <th class="bg-light text-start">{{ $fromStop->stop_name }}</th>
                                    @foreach($bus->activeFare->route->stops as $toStop)
                                        <td>
                                            @php
                                                $matrix = $bus->activeFare->matrices->where('from_stop_id', $fromStop->id)->where('to_stop_id', $toStop->id)->first();
                                            @endphp
                                            @if($fromStop->id == $toStop->id)
                                                <span class="text-muted">-</span>
                                            @else
                                                <span class="fw-medium">Rs. {{ $matrix ? number_format($matrix->amount, 2) : '0.00' }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endpush

@push('page-js')
<script type="module">
    $(function () {
        const bus = @json($bus);
        const routeStops = @json($bus->route ? $bus->route->stops : []);

        const map = L.map('bus-map').setView([bus.latitude || 27.7172, bus.longitude || 85.3240], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        if (routeStops.length > 0) {
            const latlngs = routeStops.map(s => [s.latitude, s.longitude]);
            L.polyline(latlngs, {color: '#696cff', weight: 5, opacity: 0.6, dashArray: '10, 10'}).addTo(map);
            
            routeStops.forEach((s, i) => {
                L.circleMarker([s.latitude, s.longitude], {
                    radius: 6, 
                    color: '#696cff', 
                    fillColor: 'white', 
                    fillOpacity: 1,
                    weight: 2
                }).addTo(map).bindPopup(`<strong>Stop ${i+1}:</strong> ${s.stop_name}`);
            });
        }

        if (bus.latitude && bus.longitude) {
            const busIcon = L.divIcon({
                html: '<i class="bx bx-bus bg-primary text-white p-1 rounded-circle shadow" style="font-size: 24px; border: 2px solid white;"></i>',
                className: 'custom-div-icon',
                iconSize: [30, 30],
                iconAnchor: [15, 15]
            });
            L.marker([bus.latitude, bus.longitude], {icon: busIcon, zIndexOffset: 1000}).addTo(map).bindPopup('<strong>Current Location</strong>');
        }

        @if(!auth()->user()->hasRole('customers'))
        $('.bus-income-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('merchant.income') }}",
                data: function(d) {
                    d.reference_id = "{{ $bus->id }}";
                    d.reference_type = "App\\Models\\Bus";
                }
            },
            columns: [
                {data: 'created_at', name: 'created_at'},
                {data: 'customer', name: 'customer'},
                {data: 'amount', name: 'amount'},
            ],
            order: [[0, 'desc']]
        });
        @endif

        window.focusStop = function(lat, lng, name) {
            map.setView([lat, lng], 16);
            L.popup().setLatLng([lat, lng]).setContent(`<strong>${name}</strong>`).openOn(map);
            // Smooth scroll to map
            document.getElementById('bus-map').scrollIntoView({ behavior: 'smooth', block: 'center' });
        };
    });
</script>
@endpush
