@extends('layouts.app')

@section('title', 'Stop Management')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Fleet Management /</span> Stops
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Stops List</h5>
        <a href="{{ route('stops.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Add Stop
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <style>
                table.data-table {
                    table-layout: fixed !important;
                    width: 100% !important;
                    margin: 0 !important;
                }
                table.data-table th, table.data-table td {
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }
                table.data-table th:nth-child(1) { width: 50px; }
                table.data-table th:nth-child(2) { width: 250px; }
                table.data-table th:nth-child(3) { width: 150px; }
                table.data-table th:nth-child(4) { width: 150px; }
                table.data-table th:nth-child(5) { width: 180px; }
                table.data-table th:nth-child(6) { width: 150px; }

                .dark-style table.data-table td { border-color: rgba(255,255,255,0.05) !important; }
            </style>
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Stop Name</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
<div class="modal fade" id="stopMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stop-location-name">Stop Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div id="stop-map-container" style="height: 400px;"></div>
            </div>
            <div class="modal-footer">
                <small id="stop-coords" class="text-muted"></small>
                <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endpush

@push('page-js')
<script type="text/javascript">
  document.addEventListener('DOMContentLoaded', function () {
    var table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        responsive: false,
        autoWidth: false,
        
        order: [[4, 'desc']],
        ajax: "{{ route('stops.index') }}",
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'name', name: 'name'},
            {data: 'latitude', name: 'latitude'},
            {data: 'longitude', name: 'longitude'},
            {data: 'created_at', name: 'created_at'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });

    let stopMap;
    let stopMarker;
    const mapModalEl = document.getElementById('stopMapModal');
    const stopMapModal = new bootstrap.Modal(mapModalEl);

    $(document).on('click', '.view-stop-map', function() {
        const lat = $(this).data('lat');
        const lon = $(this).data('lon');
        const name = $(this).data('name');

        $('#stop-location-name').text(name);
        $('#stop-coords').text(`Coords: ${lat}, ${lon}`);
        
        stopMapModal.show();

        mapModalEl.addEventListener('shown.bs.modal', function () {
            if (!stopMap) {
                stopMap = L.map('stop-map-container').setView([lat, lon], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(stopMap);
                stopMarker = L.marker([lat, lon]).addTo(stopMap);
            } else {
                stopMap.setView([lat, lon], 16);
                stopMarker.setLatLng([lat, lon]);
            }
            stopMarker.bindPopup(`<strong>${name}</strong>`).openPopup();
            setTimeout(() => stopMap.invalidateSize(), 10); 
        }, { once: true });
    });
  });
</script>
@endpush
