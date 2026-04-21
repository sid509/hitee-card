@extends('layouts.app')

@section('title', 'Cards')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Management /</span> Cards
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cards List</h5>
        <div class="d-flex gap-2">
            @if(auth()->user()->hasRole('super-admin'))
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle btn-sm" type="button" id="bulkActions" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bx bx-check-square me-1"></i> Bulk Actions
                </button>
                <ul class="dropdown-menu" aria-labelledby="bulkActions">
                    <li><a class="dropdown-item bulk-status-change" href="javascript:void(0);" data-status="active">Activate Selected</a></li>
                    <li><a class="dropdown-item bulk-status-change" href="javascript:void(0);" data-status="inactive">Deactivate Selected</a></li>
                </ul>
            </div>
            @endif
            <a href="{{ route('cards.create') }}" class="btn btn-primary btn-sm">
                <i class="bx bx-plus me-1"></i> Issue New Card
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <style>
                /* Force absolute stability across pagination */
                table.data-table {
                    table-layout: fixed !important;
                    width: 100% !important;
                    margin: 0 !important;
                }
                table.data-table th, table.data-table td {
                    overflow: hidden;
                    white-space: nowrap;
                    text-overflow: ellipsis;
                }
                /* Explicit column widths */
                table.data-table th:nth-child(1) { width: 40px; }  /* Checkbox */
                table.data-table th:nth-child(2) { width: 50px; }  /* ID */
                table.data-table th:nth-child(3) { width: 180px; } /* Card Number */
                table.data-table th:nth-child(4) { width: 180px; } /* HWID */
                table.data-table th:nth-child(5) { width: 150px; } /* User */
                table.data-table th:nth-child(6) { width: 120px; text-align: center; } /* Status */
                table.data-table th:nth-child(7) { width: 100px; text-align: center; } /* Usage */
                table.data-table th:nth-child(8) { width: 150px; } /* Created At */
                table.data-table th:nth-child(9) { width: 180px; } /* Actions */

                /* Cell specific styling */
                table.data-table td:nth-child(6), 
                table.data-table td:nth-child(7) { text-align: center; }

                .dark-style table.data-table td { border-color: rgba(255,255,255,0.05) !important; }
            </style>
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th width="10" class="text-start"><input type="checkbox" class="form-check-input" id="select-all"></th>
                        <th>ID</th>
                        <th>Card Number</th>
                        <th>HWID</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Usage</th>
                        <th>Created At</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('modals')
@if(auth()->user()->hasRole('customers'))
<!-- Request Change Modal -->
<div class="modal fade" id="requestChangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Card Change</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="requestChangeForm">
                @csrf
                <input type="hidden" id="request_card_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Card Number</label>
                        <input type="text" id="request_card_number" class="form-control" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Request Type</label>
                        <select name="type" id="request_type" class="form-select" required>
                            <option value="upgrade" id="opt_upgrade">Upgrade Card</option>
                            <option value="enable" id="opt_enable">Enable/Activate Card</option>
                            <option value="disable" id="opt_disable">Disable/Deactivate Card</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Additional Message (Optional)</label>
                        <textarea name="message" class="form-control" rows="3" placeholder="Explain your request..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" id="btnSubmitRequest" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
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
            
            order: [[7, 'desc']],
            ajax: "{{ route('cards.index') }}",
            columnDefs: [
                {
                    targets: [0, 1, 6, 8],
                    orderable: false,
                    searchable: false
                }
            ],
            columns: [
                {data: 'checkbox', name: 'checkbox', orderable: false, searchable: false},
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'card_number', name: 'card_number'},
                {data: 'hwid', name: 'hwid'},
                {data: 'user.name', name: 'user.name', defaultContent: 'N/A'},
                {data: 'status', name: 'status', render: function(data) {
                    let classMap = { active: 'bg-label-success', inactive: 'bg-label-secondary', blocked: 'bg-label-danger' };
                    return `<span class="badge ${classMap[data] || 'bg-label-info'}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
                }},
                {data: 'usage_badge', name: 'is_currently_active', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        // Toggle Card Status
        $(document).on('click', '.toggle-card-status', function() {
            const id = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.ajax({
                url: `/cards/${id}/toggle-status`,
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
                    showAlert('Failed to update card status.', 'error');
                },
                complete: function() {
                    btn.prop('disabled', false);
                }
            });
        });

        // Select All Checkbox
        $('#select-all').on('click', function() {
            $('.row-checkbox').prop('checked', this.checked);
        });

        // Bulk Status Change
        $(document).on('click', '.bulk-status-change', function() {
            const status = $(this).data('status');
            const ids = $('.row-checkbox:checked').map(function() { return $(this).val(); }).get();

            if (ids.length === 0) {
                showAlert('Please select at least one card.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Are you sure?',
                text: `You want to change status of ${ids.length} cards to ${status}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, change it!',
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            }).then(function(result) {
                if (result.value) {
                    $.ajax({
                        url: "{{ route('cards.bulk-toggle-status') }}",
                        method: "POST",
                        data: {
                            _token: "{{ csrf_token() }}",
                            ids: ids,
                            status: status
                        },
                        success: function(response) {
                            if (response.status) {
                                showToast(response.message, 'Success', 'success');
                                table.ajax.reload(null, false);
                                $('#select-all').prop('checked', false);
                            }
                        }
                    });
                }
            });
        });

        // Request Change Button
        $(document).on('click', '.btn-request-change', function() {
            const id = $(this).data('id');
            const cardNumber = $(this).data('card-number');
            const canEnable = $(this).data('can-enable');
            const canDisable = $(this).data('can-disable');
            const canUpgrade = $(this).data('can-upgrade');

            $('#request_card_id').val(id);
            $('#request_card_number').val(cardNumber);
            
            // Show/Hide options based on applicability
            if (canEnable) $('#opt_enable').show(); else $('#opt_enable').hide();
            if (canDisable) $('#opt_disable').show(); else $('#opt_disable').hide();
            if (canUpgrade) $('#opt_upgrade').show(); else $('#opt_upgrade').hide();

            // Set default selected
            if (canDisable) $('#request_type').val('disable');
            else if (canEnable) $('#request_type').val('enable');
            else if (canUpgrade) $('#request_type').val('upgrade');

            new bootstrap.Modal(document.getElementById('requestChangeModal')).show();
        });

        $('#requestChangeForm').on('submit', function(e) {
            e.preventDefault();
            const id = $('#request_card_id').val();
            const btn = $('#btnSubmitRequest');
            
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');

            $.ajax({
                url: `/cards/${id}/request-change`,
                method: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    const modal = bootstrap.Modal.getInstance(document.getElementById('requestChangeModal'));
                    if (modal) modal.hide();
                    showToast(response.message, 'Success', 'success');
                    table.ajax.reload();
                },
                error: function(xhr) {
                    const msg = xhr.responseJSON?.message || 'Failed to submit request';
                    showAlert(msg, 'error');
                },
                complete: function() {
                    btn.prop('disabled', false).text('Submit Request');
                }
            });
        });
    });
</script>
@endpush
