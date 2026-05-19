@extends('layouts.app')

@section('title', 'User Management')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Administration /</span> Users
</h4>

<!-- User List Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">All Registered Users</h5>
        <div class="d-flex gap-2">
            <select id="roleFilter" class="form-select form-select-sm" style="width: 150px;">
                <option value="">All Roles</option>
                @foreach($roles as $role)
                    <option value="{{ $role->slug }}">{{ $role->name }}</option>
                @endforeach
            </select>
            @if(auth()->user()->hasRole('super-admin'))
            <div class="dropdown">
                <button class="btn btn-secondary dropdown-toggle btn-sm" type="button" id="bulkActions" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    Bulk Actions
                </button>
                <div class="dropdown-menu" aria-labelledby="bulkActions">
                    <a class="dropdown-item bulk-status-change" href="javascript:void(0);" data-status="active">Activate Selected</a>
                    <a class="dropdown-item bulk-status-change" href="javascript:void(0);" data-status="inactive">Deactivate Selected</a>
                </div>
            </div>
            <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">
                <i class="bx bx-plus me-1"></i> Add New User
            </a>
        </div>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th width="10"><input type="checkbox" class="form-check-input" id="select-all"></th>
                        <th width="10">#</th>
                        <th>User</th>
                        <th>Card Info</th>
                        <th>Roles</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Joined</th>
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
<!-- Unified Balance Management Modal -->
<div class="modal fade" id="manageBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Manage Balance: <span id="manage_user_name" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 mb-3">
                    <div class="d-flex align-items-center">
                        <i class="bx bx-wallet me-2"></i>
                        <span>Current Balance: <strong id="manage_current_balance">Rs. 0.00</strong></span>
                    </div>
                </div>

                <div class="nav-align-top mb-4">
                    <ul class="nav nav-tabs nav-fill" role="tablist">
                        <li class="nav-item">
                            <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#tab-add-balance" aria-controls="tab-add-balance" aria-selected="true">
                                <i class="tf-icons bx bx-plus-circle me-1"></i> Add Funds
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#tab-deduct-balance" aria-controls="tab-deduct-balance" aria-selected="false">
                                <i class="tf-icons bx bx-minus-circle me-1"></i> Deduct Funds
                            </button>
                        </li>
                    </ul>
                    <div class="tab-content border-0 px-0 pb-0">
                        <!-- Tab: Add Balance -->
                        <div class="tab-pane fade show active" id="tab-add-balance" role="tabpanel">
                            <form action="{{ route('transactions.manual-add') }}" method="POST">
                                @csrf
                                <input type="hidden" name="user_id" class="target_user_id">
                                <input type="hidden" name="card_id" class="target_card_id">
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
                                    <textarea name="remarks" class="form-control-text" rows="2" placeholder="Reason for adding balance"></textarea>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">Add Balance</button>
                                </div>
                            </form>
                        </div>
                        
                        <!-- Tab: Deduct Balance -->
                        <div class="tab-pane fade" id="tab-deduct-balance" role="tabpanel">
                            <form action="{{ route('transactions.manual-deduct') }}" method="POST">
                                @csrf
                                <input type="hidden" name="user_id" class="target_user_id">
                                <input type="hidden" name="card_id" class="target_card_id">
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
                                    <select name="merchant_id" id="merchant_search" class="form-select select2-ajax-merchant">
                                        <option value="">Search Merchant...</option>
                                    </select>
                                </div>
                                <div class="mb-3" id="reference_container" style="display: none;">
                                    <label class="form-label" id="reference_label">Reference</label>
                                    <select name="reference_id" id="reference_search" class="form-select select2-ajax-references">
                                        <option value="">Search...</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remarks</label>
                                    <textarea name="remarks" class="form-control-text" rows="2" placeholder="Reason for deduction"></textarea>
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-danger">Deduct Balance</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endif
@endpush

@push('page-js')
<script type="module">
    $(function () {
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: false,
            autoWidth: false,
            ajax: {
                url: "{{ route('users.index') }}",
                data: function (d) {
                    d.role = $('#roleFilter').val();
                }
            },
            order: [[7, 'desc']],
            columnDefs: [
                {
                    targets: [0, 1, 3, 4, 5, 6, 8],
                    orderable: false,
                    searchable: false
                }
            ],
            columns: [
                {data: 'checkbox', name: 'checkbox', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'user_info', name: 'name', className: 'column-ellipsis', orderable: false},
                {data: 'card_info', name: 'card_info', orderable: false, searchable: false},
                {data: 'role_icons', name: 'role_icons', orderable: false},
                {data: 'balance', name: 'balance', orderable: false, searchable: false},
                {data: 'status', name: 'status', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at', orderable: true},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ],
            drawCallback: function() {
                // Initialize tooltips after table draws
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });

        $('#roleFilter').on('change', function() {
            table.draw();
        });

        // Manage Balance Button
        $(document).on('click', '.manage-balance-btn', function() {
            const id = $(this).data('id');
            const name = $(this).data('name');
            const balance = $(this).data('balance');
            const cardId = $(this).data('card-id');

            $('.target_user_id').val(id);
            $('.target_card_id').val(cardId);
            $('#manage_user_name').text(name);
            $('#manage_current_balance').text('Rs. ' + parseFloat(balance).toLocaleString(undefined, {minimumFractionDigits: 2}));
            
            new bootstrap.Modal(document.getElementById('manageBalanceModal')).show();
        });

        // Select All Checkbox
        $('#select-all').on('click', function() {
            $('.row-checkbox').prop('checked', this.checked);
        });

        // Approve User
        $(document).on('click', '.approve-user-btn', function() {
            const id = $(this).data('id');
            const btn = $(this);
            
            Swal.fire({
                title: 'Approve User?',
                text: "This will activate the user account.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, Approve!',
                customClass: {
                    confirmButton: 'btn btn-success me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) {
                    btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');
                    $.ajax({
                        url: `/users/${id}/approve`,
                        method: "POST",
                        data: { _token: "{{ csrf_token() }}" },
                        success: function(response) {
                            if (response.status) {
                                showToast(response.message, 'Approved', 'success');
                                table.ajax.reload(null, false);
                            } else {
                                showAlert(response.message, 'error');
                            }
                        },
                        complete: function() {
                            btn.prop('disabled', false).html('<i class="bx bx-check-shield"></i>');
                        }
                    });
                }
            });
        });

        // Toggle User Status
        $(document).on('click', '.toggle-user-status', function() {
            const id = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.ajax({
                url: `/users/${id}/toggle-status`,
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    if (response.status) {
                        showToast(response.message, 'Success', 'success');
                        table.ajax.reload(null, false);
                    } else {
                        showAlert(response.message, 'error');
                    }
                },
                error: function() {
                    showAlert('Failed to update user status.', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false);
                }
            });
        });

        // Select2 Merchant Search
        if ($.fn.select2) {
            $('#merchant_search').select2({
                dropdownParent: $('#manageBalanceModal'),
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

            $('#reference_search').select2({
                dropdownParent: $('#manageBalanceModal'),
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
        }

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
    .nav-tabs .nav-link.active { background-color: transparent !important; border-bottom: 2px solid #696cff !important; }
</style>
@endpush
