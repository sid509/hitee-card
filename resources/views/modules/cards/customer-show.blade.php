@extends('layouts.app')

@section('title', 'Customer Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">Card Management / Customers /</span> {{ $customer->full_name }}
        </h4>
        <a href="{{ route('cards.customers.index') }}" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center flex-column mb-3">
                        <div class="avatar avatar-xl bg-label-info rounded p-4 mb-3" style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center;">
                            <i class="bx bx-user" style="font-size: 3rem;"></i>
                        </div>
                        <h5 class="mb-1">{{ $customer->full_name }}</h5>
                        <span class="badge bg-label-info">{{ $customer->category ?? 'Standard' }}</span>
                    </div>
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">Customer #</dt>
                        <dd class="col-sm-7"><code>{{ $customer->customer_number }}</code></dd>
                        @if($customer->phone_number ?? null)
                        <dt class="col-sm-5 text-muted">Phone</dt>
                        <dd class="col-sm-7">{{ $customer->phone_number }}</dd>
                        @endif
                        @if($customer->email ?? null)
                        <dt class="col-sm-5 text-muted">Email</dt>
                        <dd class="col-sm-7">{{ $customer->email }}</dd>
                        @endif
                        <dt class="col-sm-5 text-muted">Registered</dt>
                        <dd class="col-sm-7">{{ \Carbon\Carbon::parse($customer->created_at)->format('M d, Y') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header"><h6 class="mb-0">Cards ({{ $cards->count() }})</h6></div>
                <div class="card-body">
                    @if($cards->isEmpty())
                        <p class="text-muted text-center py-4">No cards issued to this customer.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead><tr><th>Card Number</th><th>UID</th><th>Status</th><th>Environment</th><th>Issued</th><th></th></tr></thead>
                                <tbody>
                                    @foreach($cards as $c)
                                    @php
                                        $colors = ['ACTIVE'=>'bg-label-success','ISSUED'=>'bg-label-primary','REGISTERED'=>'bg-label-secondary','BLOCKED'=>'bg-label-danger'];
                                        $cls = $colors[$c->status] ?? 'bg-label-secondary';
                                    @endphp
                                    <tr>
                                        <td>{{ $c->card_number }}</td>
                                        <td><code>{{ $c->card_uid }}</code></td>
                                        <td><span class="badge {{ $cls }}">{{ $c->status }}</span></td>
                                        <td><span class="badge {{ $c->environment === 'PRODUCTION' ? 'bg-label-success' : 'bg-label-warning' }}">{{ $c->environment }}</span></td>
                                        <td><small>{{ $c->issued_at ? \Carbon\Carbon::parse($c->issued_at)->format('M d, Y') : '—' }}</small></td>
                                        <td><a href="{{ route('cards.show', $c->id) }}" class="btn btn-sm btn-outline-primary">View</a></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
