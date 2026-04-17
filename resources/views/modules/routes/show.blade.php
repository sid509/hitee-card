@extends('layouts.app')

@section('title', 'Route Details')

@push('page-css')
<style>
    #route-map-show { height: 450px; border-radius: 8px; margin-bottom: 20px; }
</style>
@endpush

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Routes /</span> {{ $route->name }}
</h4>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-4">
            <h5 class="card-header">Route Information</h5>
            <div class="card-body">
                <ul class="list-unstyled">
                    <li class="mb-3"><span class="fw-medium me-2">Name:</span> <span>{{ $route->name }}</span></li>
                    <li class="mb-3"><span class="fw-medium me-2">Merchant:</span> <span>{{ $route->merchant->name }}</span></li>
                    <li class="mb-3"><span class="fw-medium me-2">Stops:</span> <span>{{ $route->stops->count() }}</span></li>
                    <li class="mb-3"><span class="fw-medium me-2">Description:</span> <p>{{ $route->description ?? 'N/A' }}</p></li>
                </ul>
                <div class="d-flex justify-content-center">
                    <a href="{{ route('routes.edit', $route->id) }}" class="btn btn-primary me-2">Edit Route</a>
                    <a href="{{ route('routes.index') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>

        <div class="card">
            <h5 class="card-header">Stops Sequence</h5>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Order</th>
                                <th>Stop Name</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($route->stops as $stop)
                            <tr>
                                <td>{{ $stop->order + 1 }}</td>
                                <td>{{ $stop->stop_name }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <h5 class="card-header">Route Map Visualization</h5>
            <div class="card-body">
                <div id="route-map-show"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function() {
        const stops = @json($route->stops);
        if (stops.length === 0) return;

        const map = L.map('route-map-show');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        const latlngs = stops.map(s => [s.latitude, s.longitude]);
        const polyline = L.polyline(latlngs, {color: 'blue', weight: 4, opacity: 0.7}).addTo(map);

        stops.forEach((s, i) => {
            L.circleMarker([s.latitude, s.longitude], {
                radius: 6,
                fillColor: "white",
                color: "blue",
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map).bindPopup(`<strong>Stop ${i+1}:</strong> ${s.stop_name}`);
        });

        map.fitBounds(polyline.getBounds(), {padding: [50, 50]});
    });
</script>
@endpush
