@extends('layouts.app')

@section('title', 'Dashboard')

@push('page-css')
<style>
    #map { height: 400px; border-radius: 8px; border: 1px solid #dee2e6; }
    .nearby-badge { position: absolute; top: 10px; right: 10px; z-index: 1000; }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-8 mb-6 order-0">
        <div class="card h-100">
            <div class="d-flex align-items-start row">
                <div class="col-sm-7">
                    <div class="card-body">
                        <h5 class="card-title text-primary mb-3">{{ __('messages.welcome') }} {{ auth()->user()->name }}! 🎉</h5>
                        <p class="mb-6">
                            Welcome back to Hitee Platform. Here is what is happening with your account today.
                        </p>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('profile.show') }}" class="btn btn-sm btn-primary">View Profile</a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-6">
                        <img src="{{ asset('assets/img/illustrations/man-with-laptop.png') }}" height="175"
                            alt="View Badge User" />
                    </div>
                </div>
            </div>
            <div class="card-body border-top">
                <h6 class="text-muted mb-4">Quick Shortcuts</h6>
                <div class="row g-3">
                    @if(auth()->user()->hasRole('super-admin'))
                        <div class="col-md-3 col-6">
                            <a href="{{ route('users.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-user-plus fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">Add User</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#quickAddBalanceModal" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-wallet fs-3 mb-2 text-success"></i>
                                <span class="small fw-medium">Load Funds</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('cards.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-credit-card fs-3 mb-2 text-warning"></i>
                                <span class="small fw-medium">Issue Card</span>
                            </a>
                        </div>
                    @endif

                    @if(auth()->user()->hasRole('super-admin'))
                        <div class="col-md-3 col-6">
                            <a href="{{ route('buses.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-bus fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">Add Bus</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('parkings.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-car fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">Add Parking</span>
                            </a>
                        </div>
                    @endif

                    @if(auth()->user()->hasRole('merchant'))
                        <div class="col-md-3 col-6">
                            <a href="{{ route('buses.index') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-bus fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">My Buses</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('parkings.index') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-car fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">My Parkings</span>
                            </a>
                        </div>
                    @endif

                    @if(auth()->user()->hasRole('customers'))
                        <div class="col-md-3 col-6">
                            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#khaltiTopupModal" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-plus-circle fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">Topup Balance</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('transactions.logs') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-list-ul fs-3 mb-2 text-info"></i>
                                <span class="small fw-medium">Transaction Logs</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('cards.index') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-credit-card fs-3 mb-2 text-warning"></i>
                                <span class="small fw-medium">My Cards</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 order-1">
        <div class="row">
            <div class="col-12 mb-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-white text-primary"><i class="bx bx-wallet"></i></span>
                            </div>
                            <h5 class="card-title text-white mb-0">{{ auth()->user()->hasRole('merchant') ? 'Available Income' : 'My Balance' }}</h5>
                        </div>
                        <h2 class="text-white mb-2">Rs. {{ number_format(auth()->user()->hasRole('merchant') ? auth()->user()->merchantBalance() : auth()->user()->balance(), 2) }}</h2>
                        <div class="d-flex gap-2">
                            @if(auth()->user()->hasRole('customers'))
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#khaltiTopupModal">Topup</button>
                                <a href="{{ route('transactions.logs') }}" class="btn btn-sm btn-outline-light">History</a>
                            @elseif(auth()->user()->hasRole('merchant'))
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#withdrawModal">Withdraw</button>
                                <a href="{{ route('merchant.income') }}" class="btn btn-sm btn-outline-light">Earnings</a>
                            @else
                                <a href="{{ route('transactions.logs') }}" class="btn btn-sm btn-outline-light">History</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @if(auth()->user()->hasRole('super-admin'))
            <div class="col-lg-6 col-md-12 col-6 mb-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-user"></i></span>
                            </div>
                        </div>
                        <p class="mb-1">Total Users</p>
                        <h4 class="card-title mb-3">{{ $userCount }}</h4>
                    </div>
                </div>
            </div>
            @endif

            @if(auth()->user()->hasRole('super-admin', 'merchant'))
            <div class="col-lg-6 col-md-12 col-6 mb-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-bus"></i></span>
                            </div>
                        </div>
                        <p class="mb-1">{{ auth()->user()->hasRole('merchant') ? 'My Buses' : 'Total Buses' }}</p>
                        <h4 class="card-title mb-3">{{ $busCount }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-info"><i class="bx bx-garage"></i></span>
                            </div>
                        </div>
                        <p class="mb-1">{{ auth()->user()->hasRole('merchant') ? 'My Parkings' : 'Total Parkings' }}</p>
                        <h4 class="card-title mb-3">{{ $parkingCount }}</h4>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="row">
    <!-- Map View for Admin and Merchant -->
    @if(auth()->user()->hasRole('super-admin', 'merchant'))
    <div class="col-12 mb-4">
        <div class="card">
            <h5 class="card-header">Fleet & Asset Locations</h5>
            <div class="card-body">
                <div id="map"></div>
            </div>
        </div>
    </div>
    @endif

    <!-- Nearby for Customers -->
    @if(auth()->user()->hasRole('customers'))
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Nearby Assets (5km)</h5>
                <button id="refreshNearby" class="btn btn-sm btn-outline-primary"><i class="bx bx-refresh"></i> Refresh</button>
            </div>
            <div class="card-body">
                <div id="nearby-status" class="alert alert-info py-2 mb-3">
                    <i class="bx bx-loader-alt bx-spin me-2"></i> Detecting your location...
                </div>
                <div class="row" id="nearby-assets-container">
                    <!-- Dynamic content -->
                </div>
                <div id="map" class="mt-3"></div>
            </div>
        </div>
    </div>
    @endif
</div>

@endsection

@push('page-js')
<script type="module">
    $(function() {
        // Initialize Map
        const isCustomer = {{ auth()->user()->hasRole('customers') ? 'true' : 'false' }};
        const buses = @json($buses);
        const parkings = @json($parkings);
        
        const map = L.map('map').setView([27.7172, 85.3240], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const busIcon = L.divIcon({
            html: '<i class="bx bx-bus bg-primary text-white p-1 rounded-circle" style="font-size: 24px;"></i>',
            className: 'custom-div-icon',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        const parkingIcon = L.divIcon({
            html: '<i class="bx bx-garage bg-info text-white p-1 rounded-circle" style="font-size: 24px;"></i>',
            className: 'custom-div-icon',
            iconSize: [30, 30],
            iconAnchor: [15, 15]
        });

        const markers = L.layerGroup().addTo(map);

        function renderMarkers(busList, parkingList) {
            markers.clearLayers();
            const bounds = [];
            busList.forEach(bus => {
                if (bus.latitude && bus.longitude) {
                    L.marker([bus.latitude, bus.longitude], {icon: busIcon})
                        .bindPopup(`<strong>Bus: ${bus.name}</strong><br>No: ${bus.bus_number}`)
                        .addTo(markers);
                    bounds.push([bus.latitude, bus.longitude]);
                }
            });
            parkingList.forEach(p => {
                if (p.latitude && p.longitude) {
                    L.marker([p.latitude, p.longitude], {icon: parkingIcon})
                        .bindPopup(`<strong>Parking: ${p.name}</strong><br>${p.location}`)
                        .addTo(markers);
                    bounds.push([p.latitude, p.longitude]);
                }
            });
            if (bounds.length > 0) map.fitBounds(bounds, {padding: [50, 50]});
        }

        if (!isCustomer) renderMarkers(buses, parkings);

        if (isCustomer) {
            function detectLocation() {
                if ("geolocation" in navigator) {
                    navigator.geolocation.getCurrentPosition(function(pos) {
                        const lat = pos.coords.latitude, lng = pos.coords.longitude;
                        $('#nearby-status').html('<i class="bx bx-loader-alt bx-spin me-2"></i> Fetching nearby assets...');
                        $.get("{{ route('search.nearby') }}", { lat, lng }, function(data) {
                            $('#nearby-status').html(`<i class="bx bx-check-circle me-2 text-success"></i> Found <strong>${data.counts.buses}</strong> buses and <strong>${data.counts.parkings}</strong> parkings within 5km.`);
                            $('#nearby-assets-container').html(`
                                <div class="col-md-6 mb-3"><div class="card bg-label-primary"><div class="card-body py-3 d-flex align-items-center"><div class="avatar me-3"><span class="avatar-initial rounded bg-primary"><i class="bx bx-bus"></i></span></div><div><h5 class="mb-0">${data.counts.buses}</h5><span>Buses Nearby</span></div></div></div></div>
                                <div class="col-md-6 mb-3"><div class="card bg-label-info"><div class="card-body py-3 d-flex align-items-center"><div class="avatar me-3"><span class="avatar-initial rounded bg-info"><i class="bx bx-garage"></i></span></div><div><h5 class="mb-0">${data.counts.parkings}</h5><span>Parkings Nearby</span></div></div></div></div>
                            `);
                            L.marker([lat, lng]).bindPopup('Your Location').addTo(markers);
                            renderMarkers(data.buses, data.parkings);
                        });
                    }, () => {
                        $('#nearby-status').attr('class', 'alert alert-warning py-2 mb-3').html('Location denied. Showing Kathmandu.');
                        $.get("{{ route('search.nearby') }}", { lat: 27.7172, lng: 85.3240 }, (data) => renderMarkers(data.buses, data.parkings));
                    });
                }
            }
            detectLocation();
            $('#refreshNearby').on('click', detectLocation);
        }

        // Fix Select2
        if ($.fn.select2) {
            $('#user_search_quick').select2({
                dropdownParent: $('#quickAddBalanceModal'),
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
        }
    });
</script>
<style>
    .select2-container--open { z-index: 9999 !important; }
</style>
@endpush

@push('modals')
@if(auth()->user()->hasRole('super-admin'))
<!-- Quick Load Funds Modal -->
<div class="modal fade" id="quickAddBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Load Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('transactions.manual-add') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select User</label>
                        <select name="user_id" id="user_search_quick" class="form-select" required>
                            <option value="">Search User...</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (Rs.)</label>
                        <input type="number" name="amount" class="form-control" placeholder="0.00" step="0.01" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="manual">Manual Load</option>
                            <option value="cashback">Cashback</option>
                            <option value="penalty_reversal">Penalty Reversal</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Reason for loading funds"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Load Funds</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(auth()->user()->hasRole('merchant'))
<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Withdraw Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('merchant.withdraw') }}" method="POST">
                @csrf
                <div class="modal-body text-center">
                    <img src="{{ asset('assets/img/hitee/khalti.png') }}" alt="Khalti" class="mb-4" style="height: 50px;">
                    <div class="mb-3 text-start">
                        <label class="form-label">Available Income Balance</label>
                        <input type="text" class="form-control" value="Rs. {{ number_format(auth()->user()->merchantBalance(), 2) }}" readonly disabled>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label">Withdrawal Amount (Rs.)</label>
                        <input type="number" name="amount" class="form-control form-control-lg" placeholder="100.00" step="0.01" min="100" required>
                        <div class="form-text">Minimum withdrawal amount is Rs. 100. Funds will be loaded to your Khalti wallet.</div>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Optional remarks"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Process Withdrawal</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(auth()->user()->hasRole('customers'))
<!-- Khalti Topup Modal -->
<div class="modal fade" id="khaltiTopupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Topup with Khalti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('khalti.payment') }}" method="POST">
                @csrf
                <div class="modal-body text-center">
                    <img src="{{ asset('assets/img/hitee/khalti.png') }}" alt="Khalti" class="mb-4" style="height: 50px;">
                    <div class="mb-3 text-start">
                        <label class="form-label">Topup Amount (Rs.)</label>
                        <input type="number" name="amount" class="form-control form-control-lg" placeholder="100.00" step="1" min="10" required>
                        <div class="form-text">Minimum topup amount is Rs. 10.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Pay with Khalti</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endpush
