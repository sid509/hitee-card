@php
    $formatMinor = function($v) { return $v !== null ? number_format($v / 100, 2) : '—'; };
    $formatDate = function($v) { return $v ? \Carbon\Carbon::parse($v)->format('M d, Y H:i') : '—'; };
    $statusBadge = function($s) {
        $colors = [
            'REGISTERED' => 'bg-label-secondary',
            'INITIALIZED' => 'bg-label-info',
            'ISSUED' => 'bg-label-primary',
            'ACTIVE' => 'bg-label-success',
            'BLOCKED' => 'bg-label-danger',
            'REPLACED' => 'bg-label-warning',
            'KEYS_PENDING' => 'bg-label-warning',
            'KEYS_PREPARED' => 'bg-label-info',
            'WRITE_AUTHORIZED' => 'bg-label-primary',
            'COMPLETED' => 'bg-label-success',
            'FAILED' => 'bg-label-danger',
            'CANCELLED' => 'bg-label-secondary',
            'CREDIT_PENDING' => 'bg-label-warning',
            'CREDIT_ATTEMPTED' => 'bg-label-info',
            'RECONCILIATION_PENDING' => 'bg-label-warning',
        ];
        $class = $colors[$s] ?? 'bg-label-secondary';
        return '<span class="badge ' . $class . '">' . e($s) . '</span>';
    };
@endphp

@extends('layouts.app')

@section('title', 'Card Details — ' . $card->card_number)

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">Card Management /</span> {{ $card->card_number }}
        </h4>
        <a href="{{ route('cards.index') }}" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to List
        </a>
    </div>

    <div class="row">
        {{-- Card Info Sidebar --}}
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center flex-column mb-3">
                        <div class="avatar avatar-xl bg-label-primary rounded p-4 mb-3" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="bx bx-credit-card" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="mb-1">{{ $card->card_number }}</h5>
                        <div class="d-flex gap-2 flex-wrap justify-content-center mt-1">
                            {!! $statusBadge($card->status) !!}
                            <span class="badge {{ $card->environment === 'PRODUCTION' ? 'bg-label-success' : 'bg-label-warning' }}">{{ $card->environment }}</span>
                        </div>
                    </div>

                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">UID</dt>
                        <dd class="col-sm-7"><code>{{ $card->card_uid }}</code></dd>

                        <dt class="col-sm-5 text-muted">Card Type</dt>
                        <dd class="col-sm-7">{{ $card->card_type_code ?? '—' }}</dd>

                        <dt class="col-sm-5 text-muted">Structure Ver.</dt>
                        <dd class="col-sm-7">{{ $card->card_structure_version ?? '—' }}</dd>

                        <dt class="col-sm-5 text-muted">Key Profile</dt>
                        <dd class="col-sm-7">{{ $card->key_profile_version ?? '—' }}</dd>

                        <dt class="col-sm-5 text-muted">Installed Profile</dt>
                        <dd class="col-sm-7">{{ $card->installed_key_profile_version ?? '—' }}</dd>

                        <dt class="col-sm-5 text-muted">Production Eligible</dt>
                        <dd class="col-sm-7">
                            @if($card->production_eligible)
                                <span class="badge bg-label-success">Yes</span>
                            @else
                                <span class="badge bg-label-secondary">No</span>
                            @endif
                        </dd>

                        <dt class="col-sm-5 text-muted">Initialized</dt>
                        <dd class="col-sm-7">{{ $formatDate($card->initialized_at) }}</dd>

                        <dt class="col-sm-5 text-muted">Issued</dt>
                        <dd class="col-sm-7">{{ $formatDate($card->issued_at) }}</dd>

                        <dt class="col-sm-5 text-muted">Activated</dt>
                        <dd class="col-sm-7">{{ $formatDate($card->activated_at) }}</dd>

                        <dt class="col-sm-5 text-muted">Blocked</dt>
                        <dd class="col-sm-7">{{ $formatDate($card->blocked_at) }}</dd>

                        @if($card->lifecycle_reason)
                        <dt class="col-sm-5 text-muted">Lifecycle Reason</dt>
                        <dd class="col-sm-7"><small>{{ $card->lifecycle_reason }}</small></dd>
                        @endif

                        <dt class="col-sm-5 text-muted">Registered</dt>
                        <dd class="col-sm-7">{{ $formatDate($card->created_at) }}</dd>
                    </dl>
                </div>
            </div>

            @if($customer)
            <div class="card mb-4">
                <div class="card-header"><h6 class="mb-0">Customer</h6></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">Name</dt>
                        <dd class="col-sm-7">{{ $customer->full_name }}</dd>

                        <dt class="col-sm-5 text-muted">Category</dt>
                        <dd class="col-sm-7">{{ $customer->category ?? '—' }}</dd>

                        @if($customer->phone_number ?? null)
                        <dt class="col-sm-5 text-muted">Phone</dt>
                        <dd class="col-sm-7">{{ $customer->phone_number }}</dd>
                        @endif

                        @if($customer->email ?? null)
                        <dt class="col-sm-5 text-muted">Email</dt>
                        <dd class="col-sm-7">{{ $customer->email }}</dd>
                        @endif

                        <dt class="col-sm-5 text-muted">Customer #</dt>
                        <dd class="col-sm-7"><code>{{ $customer->customer_number }}</code></dd>
                    </dl>
                </div>
            </div>
            @endif
        </div>

        {{-- Tabs --}}
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <ul class="nav nav-pills mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#issuance">Issuance ({{ $issuanceOps->count() }})</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#init">Initialization ({{ $initOps->count() }})</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#lifecycle">Lifecycle ({{ $lifecycleEvents->count() }})</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#recharges">Recharges ({{ $recharges->count() }})</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#debits">Debits ({{ $debits->count() }})</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#reversals">Reversals ({{ $reversals->count() }})</button>
                        </li>
                        @if($validatorTrips->count() > 0)
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#trips">Validator Trips ({{ $validatorTrips->count() }})</button>
                        </li>
                        @endif
                    </ul>

                    <div class="tab-content">
                        {{-- Issuance --}}
                        <div class="tab-pane fade show active" id="issuance">
                            @if($issuanceOps->isEmpty())
                                <p class="text-muted text-center py-4">No issuance operations. The card has been registered but not yet issued through the Card Management API.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Mode</th><th>Category</th><th>Status</th><th>Deposit</th><th>Expiry</th><th>Workstation</th><th>Date</th></tr></thead>
                                        <tbody>
                                            @foreach($issuanceOps as $op)
                                            <tr>
                                                <td>{{ $op->issuance_mode }}</td>
                                                <td>{{ $op->card_category }}</td>
                                                <td>{!! $statusBadge($op->status) !!}</td>
                                                <td>{{ $formatMinor($op->deposit_minor_units) }}</td>
                                                <td>{{ $op->expiry_date ?? '—' }}</td>
                                                <td><small>{{ $op->workstation_id }}</small></td>
                                                <td><small>{{ $formatDate($op->created_at) }}</small></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Initialization --}}
                        <div class="tab-pane fade" id="init">
                            @if($initOps->isEmpty())
                                <p class="text-muted text-center py-4">No initialization operations.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Mode</th><th>Status</th><th>Step</th><th>Workstation</th><th>Date</th></tr></thead>
                                        <tbody>
                                            @foreach($initOps as $op)
                                            <tr>
                                                <td>{{ $op->mode ?? '—' }}</td>
                                                <td>{!! $statusBadge($op->status) !!}</td>
                                                <td><small>{{ $op->last_successful_step ?? '—' }}</small></td>
                                                <td><small>{{ $op->workstation_id }}</small></td>
                                                <td><small>{{ $formatDate($op->created_at) }}</small></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Lifecycle --}}
                        <div class="tab-pane fade" id="lifecycle">
                            @if($lifecycleEvents->isEmpty())
                                <p class="text-muted text-center py-4">No lifecycle events.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Action</th><th>Reason</th><th>Workstation</th><th>Date</th></tr></thead>
                                        <tbody>
                                            @foreach($lifecycleEvents as $ev)
                                            <tr>
                                                <td>{!! $statusBadge($ev->action) !!}</td>
                                                <td><small>{{ $ev->reason ?? '—' }}</small></td>
                                                <td><small>{{ $ev->workstation_id ?? '—' }}</small></td>
                                                <td><small>{{ $formatDate($ev->created_at) }}</small></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Recharges --}}
                        <div class="tab-pane fade" id="recharges">
                            @if($recharges->isEmpty())
                                <p class="text-muted text-center py-4">No recharge operations.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Status</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>TAC</th><th>Date</th></tr></thead>
                                        <tbody>
                                            @foreach($recharges as $op)
                                            <tr>
                                                <td>{!! $statusBadge($op->status) !!}</td>
                                                <td>{{ $formatMinor($op->amount_minor_units) }}</td>
                                                <td>{{ $formatMinor($op->balance_before) }}</td>
                                                <td>{{ $formatMinor($op->balance_after) }}</td>
                                                <td><code>{{ $op->tac_hex ?? '—' }}</code></td>
                                                <td><small>{{ $formatDate($op->created_at) }}</small></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Debits --}}
                        <div class="tab-pane fade" id="debits">
                            @if($debits->isEmpty())
                                <p class="text-muted text-center py-4">No debit operations.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Status</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>TAC</th><th>MAC2</th><th>Date</th></tr></thead>
                                        <tbody>
                                            @foreach($debits as $op)
                                            <tr>
                                                <td>{!! $statusBadge($op->status) !!}</td>
                                                <td>{{ $formatMinor($op->amount_minor_units) }}</td>
                                                <td>{{ $formatMinor($op->balance_before) }}</td>
                                                <td>{{ $formatMinor($op->balance_after) }}</td>
                                                <td><code>{{ $op->tac_hex ?? '—' }}</code></td>
                                                <td><code>{{ $op->mac2_hex ?? '—' }}</code></td>
                                                <td><small>{{ $formatDate($op->created_at) }}</small></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Reversals --}}
                        <div class="tab-pane fade" id="reversals">
                            @if($reversals->isEmpty())
                                <p class="text-muted text-center py-4">No reversal operations.</p>
                            @else
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead><tr><th>Status</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>Date</th></tr></thead>
                                        <tbody>
                                            @foreach($reversals as $op)
                                            <tr>
                                                <td>{!! $statusBadge($op->status) !!}</td>
                                                <td>{{ $formatMinor($op->amount_minor_units) }}</td>
                                                <td>{{ $formatMinor($op->balance_before) }}</td>
                                                <td>{{ $formatMinor($op->balance_after) }}</td>
                                                <td><small>{{ $formatDate($op->created_at) }}</small></td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Validator Trips --}}
                        @if($validatorTrips->count() > 0)
                        <div class="tab-pane fade" id="trips">
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead><tr><th>Status</th><th>Fare</th><th>Board Stop</th><th>Alight Stop</th><th>Device</th><th>Date</th></tr></thead>
                                    <tbody>
                                        @foreach($validatorTrips as $trip)
                                        <tr>
                                            <td>{!! $statusBadge($trip->status) !!}</td>
                                            <td>{{ $formatMinor($trip->fare_minor_units) }}</td>
                                            <td><small>{{ $trip->board_stop_id ?? '—' }}</small></td>
                                            <td><small>{{ $trip->alight_stop_id ?? '—' }}</small></td>
                                            <td><small>{{ $trip->device_id }}</small></td>
                                            <td><small>{{ $formatDate($trip->created_at) }}</small></td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
