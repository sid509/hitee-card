@extends('layouts.app')

@section('title', 'My Rides')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Journey /</span> My Rides
</h4>

<div class="card mb-4 bg-label-primary border-primary">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-1 text-primary">Travel Simulation</h5>
            <p class="mb-0 small text-muted">Test the Tap In / Tap Out feature manually using your active card.</p>
        </div>
        <button id="btnSimulateTap" class="btn btn-primary">
            <i class="bx bx-radio-circle-marked me-1"></i> Tap My Card
        </button>
    </div>
</div>

<div class="card">
    <h5 class="card-header">Journey History</h5>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Asset Details</th>
                        <th>Merchant</th>
                        <th>Status</th>
                        <th>Tap In</th>
                        <th>Tap Out</th>
                        <th>Fare</th>
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
            ajax: "{{ route('rides.my-rides') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'asset_info', name: 'asset.name'},
                {data: 'merchant.name', name: 'merchant.name', defaultContent: 'N/A'},
                {data: 'status', name: 'status'},
                {data: 'tap_in.created_at', name: 'tap_in.created_at', defaultContent: '-'},
                {data: 'tap_out.created_at', name: 'tap_out.created_at', defaultContent: '---'},
                {data: 'fare_amount', name: 'fare_amount'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ],
            order: [[0, 'desc']]
        });

        $('#btnSimulateTap').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

            // Mock Data for Simulation
            // We'll pick a random active bus for simulation purposes
            const mockData = {
                lat: 27.7172,
                lon: 85.3240,
                card_number: "{{ auth()->user()->activeCard->card_number ?? '' }}",
                hw_id: 'HW-DEMO-001' // We should ideally fetch a real HWID from a bus
            };

            if (!mockData.card_number) {
                showAlert('You do not have an active card to simulate a tap.', 'error');
                btn.prop('disabled', false).html('<i class="bx bx-radio-circle-marked me-1"></i> Tap My Card');
                return;
            }

            $.ajax({
                url: "/api/tap",
                method: "POST",
                headers: {
                    'Authorization': 'Bearer ' + "{{ session('api_token') ?? '' }}" // This assumes we handle API tokens for web simulation
                },
                data: mockData,
                success: function(res) {
                    if (res.status) {
                        showToast(res.message, 'Success', 'success');
                        table.ajax.reload();
                    } else {
                        showAlert(res.message, 'warning');
                    }
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Tap Simulation failed. Ensure you have an active card.';
                    showAlert(msg, 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="bx bx-radio-circle-marked me-1"></i> Tap My Card');
                }
            });
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
