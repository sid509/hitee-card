@extends('layouts.app')

@section('title', 'Withdrawals')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="py-3 mb-0"><span class="text-muted fw-light">Merchant /</span> Withdrawals</h4>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#withdrawModal">
            <i class="bx bx-export me-1"></i> Withdraw to Khalti
        </button>
    </div>

    <div class="card">
        <h5 class="card-header">Withdrawal History</h5>
        <div class="card-body">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover merchant-withdrawals-table w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@push('modals')
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
@endpush

@endsection

@push('page-js')
<script type="module">
    $(function () {
        $('.merchant-withdrawals-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('merchant.withdrawals') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at', orderable: true},
                {data: 'transaction_id', name: 'transaction_id'},
                {data: 'status', name: 'status'},
                {data: 'amount', name: 'amount'},
                {data: 'remarks', name: 'remarks'},
            ],
            order: [[1, 'desc']]
        });
    });
</script>
@endpush
