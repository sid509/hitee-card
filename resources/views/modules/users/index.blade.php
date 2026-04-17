@extends('layouts.app')

@section('title', 'Users')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Users /</span> List
</h4>

<!-- Basic Bootstrap Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Users List</h5>
        <a href="{{ route('users.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Add User
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Roles</th>
                        <th>Balance</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
@if(auth()->user()->hasRole('super-admin'))
<!-- Load Funds Modal -->
<div class="modal fade" id="addBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="balanceModalTitle">Load Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('transactions.manual-add') }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" id="balance_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <input type="text" class="form-control" id="balance_user_name" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" class="form-control" id="balance_current_amount" readonly disabled>
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

<!-- Deduct Balance Modal -->
<div class="modal fade" id="deductBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deductModalTitle">Deduct Balance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('transactions.manual-deduct') }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" id="deduct_user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">User</label>
                        <input type="text" class="form-control" id="deduct_user_name" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" class="form-control" id="deduct_current_amount" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Deduction Amount (Rs.)</label>
                        <input type="number" name="amount" class="form-control" placeholder="0.00" step="0.01" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" id="deduct_type" class="form-select" required>
                            <option value="fare_deduction">Bus Fare</option>
                            <option value="parking">Parking Fee</option>
                            <option value="penalty">Penalty</option>
                            <option value="manual_deduction">Manual Deduction</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Merchant (Optional)</label>
                        <select name="merchant_id" id="merchant_search" class="form-select">
                            <option value="">Search Merchant...</option>
                        </select>
                        <div class="form-text">Credit this amount as merchant income.</div>
                    </div>
                    <div class="mb-3" id="reference_container" style="display: none;">
                        <label class="form-label" id="reference_label">Reference (Bus/Parking)</label>
                        <select name="reference_id" id="reference_search" class="form-select">
                            <option value="">Search...</option>
                        </select>
                        <div class="form-text">Specific Bus or Parking to track income.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Reason for deduction"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Deduct Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endpush

@push('page-js')
<!-- Select2 CSS/JS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script type="module">
    $(function () {
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('users.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'email', name: 'email'},
                {data: 'role_names', name: 'role_names', orderable: false, searchable: false},
                {data: 'balance', name: 'balance', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        // Add Balance Button
        $(document).on('click', '.add-balance-btn', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const balance = $(this).data('balance');

            $('#balance_user_id').val(id);
            $('#balance_user_name').val(name);
            $('#balance_current_amount').val('Rs. ' + parseFloat(balance).toLocaleString(undefined, {minimumFractionDigits: 2}));
            $('#balanceModalTitle').text('Load Funds for ' + name);
            
            new bootstrap.Modal(document.getElementById('addBalanceModal')).show();
        });

        // Deduct Balance Button
        $(document).on('click', '.deduct-balance-btn', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const balance = $(this).data('balance');

            $('#deduct_user_id').val(id);
            $('#deduct_user_name').val(name);
            $('#deduct_current_amount').val('Rs. ' + parseFloat(balance).toLocaleString(undefined, {minimumFractionDigits: 2}));
            $('#deductModalTitle').text('Deduct Balance for ' + name);
            
            new bootstrap.Modal(document.getElementById('deductBalanceModal')).show();
        });

        // Select2 Merchant Search
        $('#merchant_search').select2({
            dropdownParent: $('#deductBalanceModal'),
            ajax: {
                url: "{{ route('search.merchants') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return { q: params.term, page: params.page };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results,
                        pagination: { more: data.pagination.more }
                    };
                },
                cache: true
            },
            placeholder: 'Search Merchant...',
            minimumInputLength: 1,
            width: '100%'
        }).on('change', function() {
            $('#reference_search').val(null).trigger('change');
            checkReferenceVisibility();
        });

        // Select2 Reference Search
        $('#reference_search').select2({
            dropdownParent: $('#deductBalanceModal'),
            ajax: {
                url: "{{ route('search.references') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        page: params.page,
                        type: $('#deduct_type').val(),
                        merchant_id: $('#merchant_search').val()
                    };
                },
                processResults: function (data, params) {
                    params.page = params.page || 1;
                    return {
                        results: data.results,
                        pagination: { more: data.pagination.more }
                    };
                },
                cache: true
            },
            placeholder: 'Search...',
            minimumInputLength: 0,
            width: '100%'
        });

        $('#deduct_type').on('change', function() {
            $('#reference_search').val(null).trigger('change');
            checkReferenceVisibility();
        });

        function checkReferenceVisibility() {
            const type = $('#deduct_type').val();
            const container = $('#reference_container');
            const label = $('#reference_label');

            if (type === 'fare_deduction') {
                container.show();
                label.text('Select Bus');
                $('#reference_search').attr('placeholder', 'Search Bus...');
            } else if (type === 'parking') {
                container.show();
                label.text('Select Parking');
                $('#reference_search').attr('placeholder', 'Search Parking...');
            } else {
                container.hide();
            }
        }
    });
</script>
<style>
    .select2-container--open { z-index: 9999 !important; }
</style>
@endpush
