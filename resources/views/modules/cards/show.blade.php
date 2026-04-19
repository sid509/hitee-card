@extends('layouts.app')

@section('title', 'Card Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">Card /</span> {{ $card->card_number }}
        </h4>
    </div>

    <div class="row">
        <!-- Sidebar Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="user-avatar-section">
                        <div class="d-flex align-items-center flex-column">
                            <div class="avatar avatar-xl bg-label-primary rounded p-4 mb-3" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                                <i class="bx bx-credit-card fs-1" style="font-size: 3rem !important;"></i>
                            </div>
                            <div class="user-info text-center">
                                <h5 class="mb-2">{{ $card->card_number }}</h5>
                                <span class="badge {{ $card->status === 'active' ? 'bg-label-success' : 'bg-label-danger' }} mb-2">{{ ucfirst($card->status) }}</span>
                            </div>
                        </div>
                    </div>
                    <p class="small text-muted text-uppercase mb-3">Ownership</p>
                    <div class="info-container">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <span class="fw-medium me-2">User:</span>
                                <span>{{ $card->user->name ?? 'Unassigned' }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Hardware ID:</span>
                                <span class="text-muted">{{ $card->hwid }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Usage State:</span>
                                @if($card->hasOngoingRide())
                                    <span class="badge bg-label-warning">In Use</span>
                                @else
                                    <span class="badge bg-label-secondary">Idle</span>
                                @endif
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity History -->
        <div class="col-md-8">
            <div class="nav-align-top mb-4">
                <ul class="nav nav-pills mb-3" role="tablist">
                    <li class="nav-item">
                        <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-rides" aria-controls="navs-pills-rides" aria-selected="true">
                            Recent Rides
                        </button>
                    </li>
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-taps" aria-controls="navs-pills-taps" aria-selected="false">
                            Raw Taps
                        </button>
                    </li>
                </ul>
                <div class="tab-content">
                    <!-- Recent Rides -->
                    <div class="tab-pane fade show active" id="navs-pills-rides" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Asset</th>
                                        <th>Fare</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentRides as $ride)
                                        <tr>
                                            <td>{{ $ride->created_at->format('M d, H:i') }}</td>
                                            <td>
                                                <span class="fw-medium">{{ $ride->reference->bus_number ?? $ride->reference->name }}</span><br>
                                                <small class="text-muted">{{ $ride->reference_type === 'App\Models\Bus' ? 'Bus' : 'Parking' }}</small>
                                            </td>
                                            <td>Rs. {{ number_format($ride->fare_amount, 2) }}</td>
                                            <td><span class="badge bg-label-{{ $ride->status === 'completed' ? 'success' : 'warning' }}">{{ ucfirst($ride->status) }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No rides recorded for this card.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Raw Taps -->
                    <div class="tab-pane fade" id="navs-pills-taps" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>Location</th>
                                        <th>Map</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($recentTaps as $tap)
                                        <tr>
                                            <td>{{ $tap->created_at->format('M d, H:i:s') }}</td>
                                            <td><span class="badge bg-{{ $tap->type === 'in' ? 'success' : 'danger' }}">TAP {{ strtoupper($tap->type) }}</span></td>
                                            <td>{{ $tap->resolved_location_name }}</td>
                                            <td>
                                                <button class="btn btn-icon btn-sm btn-outline-primary view-tap-map" 
                                                    data-lat="{{ $tap->latitude }}" 
                                                    data-lon="{{ $tap->longitude }}"
                                                    data-name="{{ $tap->resolved_location_name }}">
                                                    <i class="bx bx-map-alt"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No tap events found.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tap Map Modal -->
<div class="modal fade" id="tapMapModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tap Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="tap-map" style="height: 350px; border-radius: 8px;"></div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function() {
        let tapMap;
        let tapMarker;

        $(document).on('click', '.view-tap-map', function() {
            const lat = $(this).data('lat');
            const lon = $(this).data('lon');
            const name = $(this).data('name');

            $('#tapMapModal').modal('show');

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
