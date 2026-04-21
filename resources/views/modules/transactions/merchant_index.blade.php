@extends('layouts.app')

@section('title', 'Income Transactions')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="py-3 mb-0"><span class="text-muted fw-light">Merchant /</span> Income</h4>
        <div class="card bg-primary text-white p-2 px-3">
            <span class="small">Available Income</span>
            <h5 class="text-white mb-0">Rs. {{ number_format(auth()->user()->merchantBalance(), 2) }}</h5>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Income History</h5>
        <div class="card-body">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover merchant-income-table w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Type</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function () {
        $('.merchant-income-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('merchant.income') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at', orderable: true},
                {data: 'customer', name: 'customer'},
                {data: 'type', name: 'type'},
                {data: 'amount', name: 'amount'},
            ],
            order: [[1, 'desc']]
        });
    });
</script>
@endpush
