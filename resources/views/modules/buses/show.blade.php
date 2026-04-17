@extends('layouts.app')

@section('title', 'Bus Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="py-3 mb-0"><span class="text-muted fw-light">Bus /</span> {{ $bus->name }}</h4>
        <a href="{{ route('buses.index') }}" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="row">
        <!-- Bus Info -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="avatar avatar-xl m-auto mb-3">
                            <span class="avatar-initial rounded-circle bg-label-primary"><i class="bx bx-bus bx-lg"></i></span>
                        </div>
                        <h5>{{ $bus->name }}</h5>
                        <span class="badge bg-label-success">{{ strtoupper($bus->status) }}</span>
                    </div>
                    <div class="info-container">
                        <ul class="list-unstyled">
                            <li class="mb-3">
                                <span class="fw-medium me-2">Bus Number:</span>
                                <span>{{ $bus->bus_number }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Hardware ID:</span>
                                <span>{{ $bus->hwid }}</span>
                            </li>
                            <li class="mb-3">
                                <span class="fw-medium me-2">Merchant:</span>
                                <span>{{ $bus->merchant->name ?? 'N/A' }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Income Card -->
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white mb-1">Total Lifetime Income</h6>
                            <h4 class="text-white mb-0">Rs. {{ number_format($bus->totalIncome(), 2) }}</h4>
                        </div>
                        <div class="avatar">
                            <span class="avatar-initial rounded bg-white text-success"><i class="bx bx-trending-up"></i></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Income History -->
        <div class="col-md-8">
            <div class="card">
                <h5 class="card-header">Bus Income History</h5>
                <div class="card-body">
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover bus-income-table w-100">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                        </table>
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
        $('.bus-income-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('merchant.income') }}",
                data: function(d) {
                    d.reference_id = "{{ $bus->id }}";
                    d.reference_type = "App\\Models\\Bus";
                }
            },
            columns: [
                {data: 'created_at', name: 'created_at'},
                {data: 'customer', name: 'customer'},
                {data: 'amount', name: 'amount'},
            ],
            order: [[0, 'desc']]
        });
    });
</script>
@endpush
