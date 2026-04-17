@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4"><span class="text-muted fw-light">Account /</span> Transactions</h4>

    <!-- Filters -->
    <div class="card mb-4">
        <h5 class="card-header">Filters</h5>
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select filter-input">
                        <option value="">All Types</option>
                        <option value="in">Credit (In)</option>
                        <option value="out">Debit (Out)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Activity</label>
                    <select name="activity" class="form-select filter-input">
                        <option value="">All Activities</option>
                        <option value="manual">Manual Load</option>
                        <option value="khalti">Khalti Topup</option>
                        <option value="cashback">Cashback</option>
                        <option value="fare_deduction">Bus Fare</option>
                        <option value="parking">Parking Fee</option>
                        <option value="penalty">Penalty</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="button" id="resetFilters" class="btn btn-outline-secondary">Reset Filters</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Transaction History</h5>
        <div class="card-body">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover transaction-data-table w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Activity</th>
                            <th>Amount</th>
                            <th>Remarks</th>
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
        var table = $('.transaction-data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('transactions.logs', $userId) }}",
                data: function (d) {
                    d.type = $('select[name="type"]').val();
                    d.activity = $('select[name="activity"]').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at'},
                {data: 'direction', name: 'direction', orderable: false, searchable: false},
                {data: 'type', name: 'type'},
                {data: 'amount', name: 'amount'},
                {data: 'remarks', name: 'remarks'},
            ],
            order: [[1, 'desc']]
        });

        $('.filter-input').on('change', function() {
            table.draw();
        });

        $('#resetFilters').on('click', function() {
            $('#filterForm')[0].reset();
            table.draw();
        });
    });
</script>
@endpush
