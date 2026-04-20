@extends('layouts.app')

@section('title', 'Add Stop')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Stops /</span> Add New
</h4>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Map</h5>
            </div>
            <div class="card-body">
                <div id="stop-map" style="height: 450px; border-radius: 8px;"></div>
                <small class="text-muted">Click on the map or drag the marker to set the stop location.</small>
            </div>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Stop Details</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('stops.store') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="name">Stop Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" placeholder="E.g. Koteshwor" value="{{ old('name') }}" required />
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="latitude">Latitude</label>
                            <input type="text" class="form-control @error('latitude') is-invalid @enderror" id="latitude" name="latitude" placeholder="27.123456" value="{{ old('latitude') }}" required />
                            @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="longitude">Longitude</label>
                            <input type="text" class="form-control @error('longitude') is-invalid @enderror" id="longitude" name="longitude" placeholder="85.123456" value="{{ old('longitude') }}" required />
                            @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" placeholder="Optional details...">{{ old('description') }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Save Stop</button>
                    <a href="{{ route('stops.index') }}" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');

    const map = L.map('stop-map').setView([27.7172, 85.3240], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    let marker;

    function updateMarker(lat, lng) {
        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', function (e) {
                const latlng = e.target.getLatLng();
                latInput.value = latlng.lat.toFixed(8);
                lngInput.value = latlng.lng.toFixed(8);
            });
        } else {
            marker.setLatLng([lat, lng]);
        }
        map.panTo([lat, lng]);
        latInput.value = lat.toFixed(8);
        lngInput.value = lng.toFixed(8);
    }

    map.on('click', function(e) {
        updateMarker(e.latlng.lat, e.latlng.lng);
    });

    // Initial marker if values exist
    if (latInput.value && lngInput.value) {
        updateMarker(parseFloat(latInput.value), parseFloat(lngInput.value));
    }
});
</script>
