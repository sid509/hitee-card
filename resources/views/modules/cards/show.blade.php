@extends('layouts.app')

@section('title', 'Card Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">Card /</span> {{ $card->card_number }}
        </h4>
        <div class="d-flex gap-2">
            @if(auth()->user()->hasRole('super-admin'))
                <a href="{{ route('cards.edit', $card->id) }}" class="btn btn-primary">
                    <i class="bx bx-edit-alt me-1"></i> Edit Card
                </a>
            @endif
            <a href="{{ route('cards.index') }}" class="btn btn-secondary">Back to List</a>
        </div>
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
                                <div class="d-flex flex-column gap-1">
                                    <span class="badge {{ $card->status === 'active' ? 'bg-label-success' : 'bg-label-danger' }}">{{ ucfirst($card->status) }}</span>
                                    @foreach($card->subscriptionModels as $model)
                                        <span class="badge bg-label-primary">{{ $model->name }}</span>
                                    @endforeach
                                    @if($card->subscriptionModels->isEmpty())
                                        <span class="badge bg-label-secondary">Standard Transit</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-around flex-wrap my-4 py-3 border-top border-bottom text-center">
                        <div class="d-flex flex-column align-items-center mt-3">
                            <h5 class="mb-0">Rs. {{ number_format($card->balance(), 2) }}</h5>
                            <small class="text-muted">Balance</small>
                        </div>
                        <div class="d-flex flex-column align-items-center mt-3">
                            <h5 class="mb-0">{{ $travelCount }}</h5>
                            <small class="text-muted">Rides</small>
                        </div>
                    </div>
                    <p class="small text-muted text-uppercase mb-3">Ownership & Features</p>
                    <div class="info-container">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <span class="fw-medium me-2">User:</span>
                                <span>
                                    @if($card->user)
                                        @if(auth()->user()->hasRole('super-admin'))
                                            <a href="{{ route('users.show', $card->user->id) }}">{{ $card->user->name }}</a>
                                        @elseif(auth()->id() === $card->user_id)
                                            <a href="{{ route('profile.show') }}">{{ $card->user->name }}</a>
                                        @else
                                            {{ $card->user->name }}
                                        @endif
                                    @else
                                        Unassigned
                                    @endif
                                </span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Subscription:</span>
                                <span class="text-primary fw-bold">
                                    {{ $card->subscriptionModels->pluck('name')->implode(', ') ?: 'Standard Transit' }}
                                </span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Personalized:</span>
                                @if($card->is_personalized)
                                    <span class="badge bg-label-success">Yes (KYC Verified)</span>
                                @else
                                    <span class="badge bg-label-warning">No (Basic)</span>
                                @endif
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Physical Card:</span>
                                @if($card->is_physical)
                                    <span class="badge bg-label-info">Issued</span>
                                @else
                                    <span class="badge bg-label-secondary">Digital Only</span>
                                @endif
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Hardware ID:</span>
                                <span class="text-muted">{{ $card->hwid ?? 'N/A' }}</span>
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
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-stats" aria-controls="navs-pills-stats" aria-selected="false">
                            Usage Statistics
                        </button>
                    </li>
                    @if($card->subscriptionModels->isNotEmpty())
                    <li class="nav-item">
                        <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-discounts" aria-controls="navs-pills-discounts" aria-selected="false">
                            Partner Discounts
                        </button>
                    </li>
                    @endif
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
                                            <td>{{ formatDate($ride->created_at) }}</td>
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
                                            <td>{{ formatDate($tap->created_at) }}</td>
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

                    <!-- Usage Statistics -->
                    <div class="tab-pane fade" id="navs-pills-stats" role="tabpanel">
                        <div class="row g-4">
                            <div class="col-sm-6 col-lg-4">
                                <div class="d-flex align-items-start border p-3 rounded h-100">
                                    <div class="badge bg-label-primary p-2 rounded me-3"><i class="bx bx-bus bx-sm"></i></div>
                                    <div>
                                        <h5 class="mb-0">{{ $travelCount }}</h5>
                                        <small class="text-muted">Total Rides</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <div class="d-flex align-items-start border p-3 rounded h-100">
                                    <div class="badge bg-label-info p-2 rounded me-3"><i class="bx bx-time bx-sm"></i></div>
                                    <div>
                                        <h5 class="mb-0">{{ floor($totalParkingMinutes / 60) }}h {{ $totalParkingMinutes % 60 }}m</h5>
                                        <small class="text-muted">Parking Duration</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-lg-4">
                                <div class="d-flex align-items-start border p-3 rounded h-100">
                                    <div class="badge bg-label-success p-2 rounded me-3"><i class="bx bx-purchase-tag bx-sm"></i></div>
                                    <div>
                                        <h5 class="mb-0">{{ $card->subscriptionModels->sum(fn($m) => $m->discounts->count()) }}</h5>
                                        <small class="text-muted">Active Offers</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Partner Discounts -->
                    @if($card->subscriptionModels->isNotEmpty())
                    <div class="tab-pane fade" id="navs-pills-discounts" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Model</th>
                                        <th>Partner</th>
                                        <th>Type</th>
                                        <th>Discount</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $hasDiscounts = false; @endphp
                                    @foreach($card->subscriptionModels as $model)
                                        @foreach($model->discounts as $discount)
                                            @php $hasDiscounts = true; @endphp
                                            <tr>
                                                <td><small class="text-muted">{{ $model->name }}</small></td>
                                                <td>
                                                    <span class="fw-medium">{{ $discount->servicePartner->name }}</span>
                                                </td>
                                                <td><span class="badge bg-label-primary">{{ ucfirst($discount->servicePartner->service_type) }}</span></td>
                                                <td><span class="text-success fw-bold">{{ $discount->discount_type == 'percentage' ? $discount->discount_value . '%' : 'Rs. ' . $discount->discount_value }} OFF</span></td>
                                                <td><small>{{ $discount->description }}</small></td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                    @if(!$hasDiscounts)
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No active discounts for your subscriptions.</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif

                    <!-- Balance History -->
                    <div class="tab-pane fade" id="navs-pills-balance" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($balanceLogs as $log)
                                        <tr>
                                            <td>{{ formatDate($log->created_at) }}</td>
                                            <td>
                                                <span class="badge bg-label-{{ $log->log_type === 'in' ? 'success' : 'danger' }}">
                                                    {{ strtoupper($log->log_type) }}: {{ str_replace('_', ' ', ucfirst($log->type)) }}
                                                </span>
                                            </td>
                                            <td class="text-{{ $log->log_type === 'in' ? 'success' : 'danger' }} fw-medium">
                                                {{ $log->log_type === 'in' ? '+' : '-' }} Rs. {{ number_format($log->amount, 2) }}
                                            </td>
                                            <td class="small">{{ $log->remarks }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No transactions found for this card.</td></tr>
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
