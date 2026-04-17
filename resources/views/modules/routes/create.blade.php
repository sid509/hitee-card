@extends('layouts.app')

@section('title', $route->id ? 'Edit Route' : 'Create Route')

@push('page-css')
<style>
    #route-map { height: 450px; border-radius: 8px; margin-bottom: 20px; }
    .stop-item { cursor: move; }
</style>
@endpush

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Routes /</span> {{ $route->id ? 'Edit' : 'Create' }}
</h4>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <h5 class="card-header">{{ $route->id ? 'Edit Route' : 'Add New Route' }}</h5>
            <div class="card-body">
                <form action="{{ $route->id ? route('routes.update', $route->id) : route('routes.store') }}" method="POST" id="routeForm">
                    @csrf
                    @if($route->id) @method('PUT') @endif
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Route Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $route->name) }}" placeholder="e.g. Kalanki - Ratnapark Ring Road" required>
                        </div>
                        @if(auth()->user()->hasRole('super-admin'))
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Merchant</label>
                            <select name="merchant_id" class="form-select select2-ajax-merchant" required>
                                @if($route->merchant_id)
                                    <option value="{{ $route->merchant_id }}" selected>{{ $route->merchant->name }}</option>
                                @endif
                            </select>
                        </div>
                        @endif
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="2">{{ old('description', $route->description) }}</textarea>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-8">
                            <h6>Route Points & Map</h6>
                            <p class="text-muted small">Click on the map to add stops in order. You can drag markers to refine locations.</p>
                            <div id="route-map"></div>
                        </div>
                        <div class="col-md-4">
                            <h6>Stops List</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Stop Name</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="stops-table-body">
                                        <!-- Dynamic rows -->
                                    </tbody>
                                </table>
                            </div>
                            <div id="no-stops-msg" class="alert alert-light text-center py-2">No stops added yet.</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">Save Route</button>
                        <a href="{{ route('routes.index') }}" class="btn btn-label-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function() {
        const map = L.map('route-map').setView([27.7172, 85.3240], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        const markers = [];
        const polyline = L.polyline([], {color: 'blue'}).addTo(map);
        let stopCount = 0;

        function updateMap() {
            const latlngs = markers.map(m => m.getLatLng());
            polyline.setLatLngs(latlngs);
            if (latlngs.length > 0) {
                // map.fitBounds(polyline.getBounds(), {padding: [50, 50]});
            }
            renderTable();
        }

        function addStop(lat, lng, name = '') {
            const index = markers.length;
            const marker = L.marker([lat, lng], {draggable: true}).addTo(map);
            
            const stopName = name || `Stop ${index + 1}`;
            marker.stopName = stopName;
            marker.bindPopup(`<strong>${marker.stopName}</strong><br><small>Click to edit name</small>`);
            
            marker.on('dragend', updateMap);
            marker.on('click', function() {
                const newName = prompt('Enter Stop Name:', this.stopName);
                if (newName && newName.trim() !== '') {
                    this.stopName = newName.trim();
                    this.setPopupContent(`<strong>${this.stopName}</strong><br><small>Click to edit name</small>`);
                    renderTable();
                }
            });

            markers.push(marker);
            updateMap();
        }

        function removeStop(index) {
            map.removeLayer(markers[index]);
            markers.splice(index, 1);
            updateMap();
        }

        function renderTable() {
            const tbody = $('#stops-table-body');
            tbody.empty();
            
            if (markers.length === 0) {
                $('#no-stops-msg').show();
            } else {
                $('#no-stops-msg').hide();
                markers.forEach((m, i) => {
                    const latlng = m.getLatLng();
                    tbody.append(`
                        <tr class="stop-item" data-index="${i}">
                            <td>${i + 1}</td>
                            <td>
                                <input type="hidden" name="stops[${i}][name]" id="input-name-${i}" value="${m.stopName}">
                                <input type="hidden" name="stops[${i}][lat]" value="${latlng.lat}">
                                <input type="hidden" name="stops[${i}][lng]" value="${latlng.lng}">
                                <span id="display-name-${i}">${m.stopName}</span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-icon btn-primary edit-stop-name" data-index="${i}"><i class="bx bx-edit"></i></button>
                                    <button type="button" class="btn btn-sm btn-icon btn-danger remove-stop" data-index="${i}"><i class="bx bx-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    `);
                });
            }
        }

        map.on('click', function(e) {
            addStop(e.latlng.lat, e.latlng.lng);
        });

        $(document).on('click', '.remove-stop', function() {
            removeStop($(this).data('index'));
        });

        $(document).on('click', '.edit-stop-name', function() {
            const index = $(this).data('index');
            const marker = markers[index];
            const newName = prompt('Enter new name for this stop:', marker.stopName);
            
            if (newName && newName.trim() !== '') {
                marker.stopName = newName.trim();
                marker.setPopupContent(`<strong>${marker.stopName}</strong><br><small>Click to edit name</small>`);
                renderTable();
            }
        });

        // Initialize for Edit
        @if($route->id)
            @foreach($route->stops as $stop)
                addStop({{ $stop->latitude }}, {{ $stop->longitude }}, "{{ $stop->stop_name }}");
            @endforeach
            const group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds(), {padding: [50, 50]});
        @endif

        // Merchant Search for Admin
        if ($('.select2-ajax-merchant').length) {
            $('.select2-ajax-merchant').select2({
                ajax: {
                    url: "{{ route('search.merchants') }}",
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term, page: params.page }),
                    processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                    cache: true
                },
                placeholder: 'Search Merchant...',
                minimumInputLength: 1,
                width: '100%'
            });
        }
    });
</script>
@endpush
