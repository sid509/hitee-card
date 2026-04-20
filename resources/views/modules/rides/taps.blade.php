@extends('layouts.app')

@section('title', 'Raw Tap Ledger')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Ledger /</span> Raw Tap Events
</h4>

<!-- Filter Bar -->
<div class="card mb-4">
    <div class="card-body">
        <form id="filterForm" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">User</label>
                <select id="filter_user" class="form-select select2-users">
                    <option value="">All Users</option>
                </select>
            </div>
            @if(auth()->user()->hasRole('super-admin'))
            <div class="col-md-3">
                <label class="form-label">Merchant</label>
                <select id="filter_merchant" class="form-select select2-basic">
                    <option value="">All Merchants</option>
                    @foreach($merchants as $m)
                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="col-md-2">
                <label class="form-label">Asset Type</label>
                <select id="filter_asset_type" class="form-select select2-basic">
                    <option value="">All Types</option>
                    <option value="bus">Bus</option>
                    <option value="parking">Parking</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Tap Type</label>
                <select id="filter_tap_type" class="form-select select2-basic">
                    <option value="">All Types</option>
                    <option value="in">Tap IN</option>
                    <option value="out">Tap OUT</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" id="btnFilter" class="btn btn-primary w-100"><i class="bx bx-filter-alt me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Raw Tap Ledger</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User & Card</th>
                        <th>Asset Details</th>
                        <th>Type</th>
                        <th>Resolved Location</th>
                        <th>Date & Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>

        </div>
    </div>
</div>

<!-- Map Modal -->
<div class="modal fade" id="tapMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tap Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="tap-map" style="height: 350px; border-radius: 8px;"></div>
                <div class="mt-3">
                    <p class="mb-1"><strong>Address/Stop:</strong> <span id="tap-location-name"></span></p>
                    <p class="small text-muted">Coordinates: <span id="tap-coords"></span></p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('rides.tap-ledger') }}",
                data: function(d) {
                    d.user_id = $('#filter_user').val();
                    d.merchant_id = $('#filter_merchant').val();
                    d.asset_type = $('#filter_asset_type').val();
                    d.type = $('#filter_tap_type').val();
                }
            },
            columnDefs: [
                {
                    targets: 0,
                    orderable: false,
                    searchable: false
                }
            ],
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'user_card', name: 'user.name'},
                {data: 'asset_info', name: 'reference.name'},
                {data: 'type', name: 'type'},
                {data: 'resolved_location_name', name: 'resolved_location_name', defaultContent: 'Moving (GPS)'},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ],

            order: [[0, 'desc']]
        });

        $('#btnFilter').click(function() {
            table.draw();
        });

        // Select2 for User Search
        $('.select2-users').select2({
            ajax: {
                url: "{{ route('search.users') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term, page: params.page }),
                processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                cache: true
            },
            placeholder: 'Search User...',
            minimumInputLength: 1,
            width: '100%'
        });

        $('.select2-basic').select2({
            width: '100%'
        });

        let tapMap;
        let tapMarker;

        $(document).on('click', '.view-tap-map', function() {
            const lat = $(this).data('lat');
            const lon = $(this).data('lon');
            const name = $(this).data('name');

            $('#tap-location-name').text(name);
            $('#tap-coords').text(`${lat}, ${lon}`);
            
            const mapModal = new bootstrap.Modal(document.getElementById('tapMapModal'));
            mapModal.show();

            setTimeout(() => {
                if (!tapMap) {
                    tapMap = L.map('tap-map').setView([lat, lon], 16);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(tapMap);
                } else {
                    tapMap.setView([lat, lon], 16);
                }

                if (tapMarker) tapMap.removeLayer(tapMarker);
                tapMarker = L.marker([lat, lon]).addTo(tapMap).bindPopup(name).openPopup();
            }, 300);
        });
    });
</script>
@endpush
