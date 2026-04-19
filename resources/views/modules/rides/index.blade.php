@extends('layouts.app')

@section('title', 'Ride Reconciliation Ledger')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Ledger /</span> Reconciled Rides
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
                <label class="form-label">Status</label>
                <select id="filter_status" class="form-select select2-basic">
                    <option value="">All Status</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="button" id="btnFilter" class="btn btn-primary w-100"><i class="bx bx-filter-alt me-1"></i> Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Rides Ledger</h5>
        <div class="small text-muted">Tap In + Tap Out = Reconciled Ride</div>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User & Card</th>
                        <th>Asset Details</th>
                        <th>Tap In</th>
                        <th>Tap Out</th>
                        <th>Fare</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- Ride Map Modal -->
<div class="modal fade" id="rideMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Journey Map</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="ride-map" style="height: 450px; border-radius: 8px;"></div>
                <div class="row mt-4">
                    <div class="col-6">
                        <p class="mb-1 fw-bold text-success"><i class="bx bx-map-pin"></i> Started At:</p>
                        <p id="ride-start-loc" class="small mb-0"></p>
                    </div>
                    <div class="col-6">
                        <p class="mb-1 fw-bold text-danger"><i class="bx bx-map-pin"></i> Ended At:</p>
                        <p id="ride-end-loc" class="small mb-0"></p>
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
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('rides.index') }}",
                data: function(d) {
                    d.user_id = $('#filter_user').val();
                    d.merchant_id = $('#filter_merchant').val();
                    d.asset_type = $('#filter_asset_type').val();
                    d.status = $('#filter_status').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'user_card', name: 'user.name'},
                {data: 'asset_info', name: 'asset.name'},
                {data: 'tap_in_time', name: 'tap_in_time'},
                {data: 'tap_out_time', name: 'tap_out_time'},
                {data: 'fare_amount', name: 'fare_amount'},
                {data: 'status', name: 'status'},
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

        let rideMap;
        let startMarker, endMarker, polyline;

        $(document).on('click', '.view-ride-map', function() {
            const sLat = $(this).data('start-lat');
            const sLon = $(this).data('start-lon');
            const sName = $(this).data('start-name');
            const eLat = $(this).data('end-lat');
            const eLon = $(this).data('end-lon');
            const eName = $(this).data('end-name');

            $('#ride-start-loc').text(sName);
            $('#ride-end-loc').text(eName);
            
            $('#rideMapModal').modal('show');

            setTimeout(() => {
                if (!rideMap) {
                    rideMap = L.map('ride-map').setView([sLat, sLon], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(rideMap);
                }

                if (startMarker) rideMap.removeLayer(startMarker);
                if (endMarker) rideMap.removeLayer(endMarker);
                if (polyline) rideMap.removeLayer(polyline);

                startMarker = L.marker([sLat, sLon]).addTo(rideMap).bindPopup('Start: ' + sName);
                
                if (eLat) {
                    endMarker = L.marker([eLat, eLon]).addTo(rideMap).bindPopup('End: ' + eName);
                    polyline = L.polyline([[sLat, sLon], [eLat, eLon]], {color: 'blue'}).addTo(rideMap);
                    rideMap.fitBounds(polyline.getBounds(), {padding: [50, 50]});
                } else {
                    rideMap.setView([sLat, sLon], 15);
                }
            }, 300);
        });
    });
</script>
@endpush
