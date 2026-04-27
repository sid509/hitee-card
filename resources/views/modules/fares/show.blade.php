@extends('layouts.app')

@section('title', 'Fare Details')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <span class="text-muted fw-light">Fares /</span> {{ $fare->name }}
    </h4>
    <div class="d-flex gap-2">
        @if(auth()->user()->hasRole('super-admin', 'merchant'))
            <a href="{{ route('fares.edit', $fare->id) }}" class="btn btn-primary">
                <i class="bx bx-edit-alt me-1"></i> Edit Fare
            </a>
        @endif
        <a href="{{ route('fares.index') }}" class="btn btn-secondary">Back to List</a>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <h5 class="card-header">Fare Information</h5>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <ul class="list-unstyled">
                            <li class="mb-3"><span class="fw-medium me-2">Name:</span> <span>{{ $fare->name }}</span></li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Merchants:</span> 
                                @php
                                    $fareMerchants = \App\Models\User::whereHas('buses', function($q) use ($fare) {
                                        $q->where('active_fare_id', $fare->id);
                                    })->get();
                                @endphp
                                @foreach($fareMerchants as $merchant)
                                    <span class="badge bg-label-secondary me-1">{{ $merchant->name }}</span>
                                @endforeach
                                @if($fareMerchants->isEmpty())
                                    <span class="text-muted small">No merchants yet</span>
                                @endif
                            </li>
                            <li class="mb-3"><span class="fw-medium me-2">Route:</span> <span>{{ $fare->route->name }}</span></li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Status:</span> 
                                <span class="badge bg-label-{{ $fare->status == 'approved' ? 'success' : ($fare->status == 'proposed' ? 'warning' : 'danger') }}">
                                    {{ ucfirst($fare->status) }}
                                </span>
                            </li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul class="list-unstyled">
                            <li class="mb-3"><span class="fw-medium me-2">Effective From:</span> <span>{{ $fare->effective_from ? formatDate($fare->effective_from) : 'Pending Approval' }}</span></li>
                            <li class="mb-3"><span class="fw-medium me-2">Created At:</span> <span>{{ formatDate($fare->created_at) }}</span></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h5 class="card-header">Pricing Matrix</h5>
            <div class="card-body">
                <div class="table-responsive text-nowrap">
                    <table class="table table-bordered table-sm text-center fare-matrix-table">
                        <thead>
                            <tr>
                                <th style="min-width: 150px;">From \ To</th>
                                @foreach($fare->route->stops as $stop)
                                    <th>{{ $stop->stop_name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fare->route->stops as $fromStop)
                                <tr>
                                    <th class="text-start">{{ $fromStop->stop_name }}</th>
                                    @foreach($fare->route->stops as $toStop)
                                        <td>
                                            @php
                                                $matrix = $fare->matrices->where('from_stop_id', $fromStop->id)->where('to_stop_id', $toStop->id)->first();
                                            @endphp
                                            @if($fromStop->id == $toStop->id)
                                                -
                                            @else
                                                <strong>Rs. {{ $matrix ? number_format($matrix->amount, 2) : '0.00' }}</strong>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
