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
                        <th>Status</th>
                        <th>Tap In</th>
                        <th>Tap Out</th>
                        <th>Fare</th>
                        <th>Created At</th>
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
                {data: 'status', name: 'status'},
                {data: 'tap_in_time', name: 'tap_in_time', defaultContent: '-'},
                {data: 'tap_out_time', name: 'tap_out_time', defaultContent: '---'},
                {data: 'fare_amount', name: 'fare_amount'},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ],
            order: [[6, 'desc']]
        });

        $('#btnSimulateTap').on('click', function() {
            const btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Processing...');

            $.ajax({
                url: "{{ route('rides.simulate-tap') }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function(res) {
                    // res is now a Laravel JSON response from controller
                    const data = res.original || res;
                    if (data.status) {
                        showToast(data.message, 'Success', 'success');
                        table.ajax.reload();
                    } else {
                        showAlert(data.message, 'warning');
                    }
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Tap Simulation failed.';
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
            
            const mapModal = new bootstrap.Modal(document.getElementById('rideMapModal'));
            mapModal.show();

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
                    
                    // Fetch road-following path from OSRM
                    fetch(`https://router.project-osrm.org/route/v1/driving/${sLon},${sLat};${eLon},${eLat}?overview=full&geometries=geojson`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.routes && data.routes.length > 0) {
                                const coordinates = data.routes[0].geometry.coordinates.map(coord => [coord[1], coord[0]]);
                                polyline = L.polyline(coordinates, {
                                    color: '#696cff',
                                    weight: 5,
                                    opacity: 0.7,
                                    lineJoin: 'round'
                                }).addTo(rideMap);
                                rideMap.fitBounds(polyline.getBounds(), {padding: [50, 50]});
                            } else {
                                // Fallback to straight line if OSRM fails
                                polyline = L.polyline([[sLat, sLon], [eLat, eLon]], {color: 'blue', dashArray: '5, 10'}).addTo(rideMap);
                                rideMap.fitBounds(polyline.getBounds(), {padding: [50, 50]});
                            }
                        })
                        .catch(err => {
                            console.error('OSRM Error:', err);
                            polyline = L.polyline([[sLat, sLon], [eLat, eLon]], {color: 'blue', dashArray: '5, 10'}).addTo(rideMap);
                            rideMap.fitBounds(polyline.getBounds(), {padding: [50, 50]});
                        });
                } else {
                    rideMap.setView([sLat, sLon], 15);
                }
            }, 300);
        });
    });
</script>
@endpush
