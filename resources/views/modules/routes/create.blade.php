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
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Route Name</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="bx bx-map-pin"></i></span>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $route->name) }}" placeholder="e.g. Kalanki - Ratnapark Ring Road" required>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control-text" rows="2">{{ old('description', $route->description) }}</textarea>
                        </div>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-8">
                            <h6>Route Points & Map</h6>
                            <p class="text-muted small">The polyline on the map will update as you add and reorder stops.</p>
                            <div id="route-map"></div>
                        </div>
                        <div class="col-md-4">
                            <h6>Stops List (Drag to Reorder)</h6>
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
                            <div class="mt-3">
                                <label class="form-label">Add Stop</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-search"></i></span>
                                    <select id="stop-search-select" class="form-select select2-ajax-stops"></select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2"><i class="bx bx-save me-1"></i> Save Route</button>
                        <a href="{{ route('routes.index') }}" class="btn btn-label-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@latest/Sortable.min.js"></script>
<script type="module">
    $(function() {
        const map = L.map('route-map').setView([27.7172, 85.3240], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        const markers = [];
        const polyline = L.polyline([], {color: 'blue'}).addTo(map);

        function updateMap() {
            const latlngs = markers.map(m => m.getLatLng());
            polyline.setLatLngs(latlngs);
            renderTable();
        }
        
        function addStop(lat, lng, name, id) {
            const marker = L.marker([lat, lng], {draggable: false}).addTo(map);
            marker.stopName = name;
            marker.stopId = id;
            marker.bindPopup(`<strong>${marker.stopName}</strong>`);
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
                    tbody.append(`
                        <tr class="stop-item" data-id="${m.stopId}">
                            <td class="py-2"><i class="bx bx-move-vertical me-2" style="cursor: grab;"></i>${i + 1}</td>
                            <td>
                                <input type="hidden" name="stops[${i}][id]" value="${m.stopId}">
                                ${m.stopName}
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-icon btn-danger remove-stop"><i class="bx bx-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                    `);
                });
            }
        }
        
        $('.select2-ajax-stops').select2({
            ajax: {
                url: "{{ route('search.stops') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term, page: params.page }),
                processResults: data => ({ results: data.results }),
                cache: true
            },
            placeholder: 'Search & Add Stop...',
            minimumInputLength: 1,
            width: '100%'
        }).on('select2:select', function(e) {
            const data = e.params.data;
            addStop(data.lat, data.lng, data.text, data.id);
            $(this).val(null).trigger('change');
        });

        $(document).on('click', '.remove-stop', function() {
            const stopIdToRemove = $(this).closest('.stop-item').data('id');
            const indexToRemove = markers.findIndex(m => m.stopId === stopIdToRemove);
            if (indexToRemove > -1) {
                removeStop(indexToRemove);
            }
        });

        const el = document.getElementById('stops-table-body');
        Sortable.create(el, {
            animation: 150,
            handle: '.bx-move-vertical',
            onEnd: function (evt) {
                const newOrder = Array.from(el.children).map(row => $(row).data('id'));
                markers.sort((a, b) => newOrder.indexOf(a.stopId) - newOrder.indexOf(b.stopId));
                renderTable();
                updateMap();
            }
        });

        @if($route->id)
            @foreach($route->stops as $stop)
                addStop({{ $stop->latitude }}, {{ $stop->longitude }}, "{{ $stop->stop_name }}", {{ $stop->stop_id }});
            @endforeach
            const group = new L.featureGroup(markers);
            if (markers.length > 0) {
                map.fitBounds(group.getBounds(), {padding: [50, 50]});
            }
        @endif

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
