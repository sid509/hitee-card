@extends('layouts.app')

@section('title', 'Edit Stop')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Stops /</span> Edit
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
                <form action="{{ route('stops.update', $stop->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="name">Stop Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $stop->name) }}" required />
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="latitude">Latitude</label>
                            <input type="text" class="form-control @error('latitude') is-invalid @enderror" id="latitude" name="latitude" value="{{ old('latitude', $stop->latitude) }}" required />
                            @error('latitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="longitude">Longitude</label>
                            <input type="text" class="form-control @error('longitude') is-invalid @enderror" id="longitude" name="longitude" value="{{ old('longitude', $stop->longitude) }}" required />
                            @error('longitude') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="description">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description">{{ old('description', $stop->description) }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-primary">Update Stop</button>
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

    const map = L.map('stop-map').setView([{{ old('latitude', $stop->latitude) }}, {{ old('longitude', $stop->longitude) }}], 15);
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

    // Initial marker
    updateMarker(parseFloat(latInput.value), parseFloat(lngInput.value));
});
</script>

