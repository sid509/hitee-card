@extends('layouts.app')

@section('title', 'Route Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">Route /</span> {{ $route->name }}
        </h4>
        @if(auth()->user()->hasRole('super-admin', 'merchant'))
            <a href="{{ route('routes.edit', $route->id) }}" class="btn btn-primary">
                <i class="bx bx-edit-alt me-1"></i> Edit Route
            </a>
        @endif
    </div>

    <div class="row">
        <!-- Sidebar Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="user-avatar-section mb-4">
                        <div class="d-flex align-items-center flex-column">
                            <div class="avatar avatar-xl bg-label-primary rounded p-3 mb-3">
                                <i class="bx bx-git-commit fs-1"></i>
                            </div>
                            <div class="user-info text-center">
                                <h5>{{ $route->name }}</h5>
                                <span class="badge bg-label-secondary">Transit Route</span>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted text-uppercase mb-3">Details</p>
                    <div class="info-container">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <span class="fw-medium me-2">Merchant:</span>
                                <span>{{ $route->merchant->name ?? 'System' }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2 text-primary">Total Stops:</span>
                                <span class="fw-bold">{{ $route->stops->count() }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2 text-primary">Active Buses:</span>
                                <span class="fw-bold">{{ $route->buses->count() }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Assigned Buses -->
            <div class="card mb-4">
                <h6 class="card-header"><i class="bx bx-bus me-2 text-primary"></i> Buses on this route</h6>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($route->buses as $bus)
                            <a href="{{ route('buses.show', $bus->id) }}" class="list-group-item list-group-item-action d-flex align-items-center px-4 py-3">
                                <i class="bx bx-bus fs-3 text-primary me-3"></i>
                                <div>
                                    <div class="fw-medium">{{ $bus->bus_number }}</div>
                                    <small class="text-muted">{{ $bus->name }}</small>
                                </div>
                            </a>
                        @empty
                            <div class="p-4 text-center text-muted small">No buses assigned to this route.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content (Map & Stops) -->
        <div class="col-md-8">
            <div class="card mb-4">
                <h5 class="card-header"><i class="bx bx-map me-2 text-primary"></i> Route Path Map</h5>
                <div class="card-body">
                    <div id="route-map" style="height: 400px; border-radius: 8px; border: 1px solid #eee;"></div>
                </div>
            </div>

            <!-- Route Stops List -->
            <div class="card">
                <h5 class="card-header">Stop Sequence</h5>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        @foreach($route->stops as $index => $stop)
                            <div class="list-group-item d-flex align-items-center border-0 px-0 py-3 hover-light rounded px-3">
                                <div class="me-3 position-relative">
                                    <span class="badge rounded-pill bg-label-primary px-2 py-1">{{ $index + 1 }}</span>
                                    @if(!$loop->last)
                                        <div class="position-absolute start-50 translate-middle-x bg-primary opacity-25" style="width: 2px; height: 30px; top: 100%;"></div>
                                    @endif
                                </div>
                                <div>
                                    <h6 class="mb-0">{{ $stop->stop_name }}</h6>
                                    <small class="text-muted">{{ $stop->latitude }}, {{ $stop->longitude }}</small>
                                </div>
                                <div class="ms-auto">
                                    <button class="btn btn-icon btn-sm btn-outline-primary rounded-pill focus-on-stop" 
                                            data-lat="{{ $stop->latitude }}" 
                                            data-lng="{{ $stop->longitude }}"
                                            data-name="{{ $stop->stop_name }}">
                                        <i class="bx bx-map-alt"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function() {
        const stops = @json($route->stops);
        const map = L.map('route-map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        if (stops.length > 0) {
            const pathCoords = [];
            const markers = [];

            stops.forEach((stop, index) => {
                const coord = [stop.latitude, stop.longitude];
                pathCoords.push(coord);
                
                const marker = L.marker(coord).addTo(map).bindPopup(`<strong>${index + 1}. ${stop.stop_name}</strong>`);
                markers.push(marker);
            });

            const polyline = L.polyline(pathCoords, {color: 'var(--bs-primary)', weight: 4, opacity: 0.6, dashArray: '1, 10'}).addTo(map);
            map.fitBounds(polyline.getBounds(), {padding: [50, 50]});

            $('.focus-on-stop').on('click', function() {
                const lat = $(this).data('lat');
                const lng = $(this).data('lng');
                const name = $(this).data('name');
                map.setView([lat, lng], 16);
                L.popup().setLatLng([lat, lng]).setContent(`<strong>${name}</strong>`).openOn(map);
                document.getElementById('route-map').scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        } else {
            map.setView([27.7172, 85.3240], 13);
        }
    });
</script>
@endpush
