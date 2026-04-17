@extends('layouts.app')

@section('title', 'Support Requests')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Administration /</span> Support Requests
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Support List</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

<!-- View Support Modal -->
<div class="modal fade" id="viewSupportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Support Request Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="fw-bold">From:</label>
                    <p id="view_user_name"></p>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Subject:</label>
                    <p id="view_subject" class="fw-medium text-primary"></p>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Date:</label>
                    <p id="view_created_at"></p>
                </div>
                <div class="mb-3">
                    <label class="fw-bold">Message:</label>
                    <p id="view_message" class="border p-2 rounded bg-light"></p>
                </div>
                <div id="closed_info" style="display:none">
                    <div class="mb-3">
                        <label class="fw-bold text-danger">Closing Reason:</label>
                        <p id="view_closing_reason" class="border p-2 rounded bg-lighter"></p>
                    </div>
                    <div class="mb-3">
                        <label class="fw-bold">Closed At:</label>
                        <p id="view_closed_at"></p>
                    </div>
                </div>
                <form id="closeSupportForm">
                    @csrf
                    <input type="hidden" id="support_id">
                    <div class="mb-3" id="reason_container">
                        <label class="form-label fw-bold text-primary">Reason to Close</label>
                        <textarea name="closing_reason" class="form-control" rows="3" required placeholder="Provide a reason for closing this request..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="btnCloseSupport">Close Ticket</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function () {
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('supports.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'user_name', name: 'user.name'},
                {data: 'subject', name: 'subject'},
                {data: 'message', name: 'message', render: function(data) {
                    return data.length > 50 ? data.substr(0, 50) + '...' : data;
                }},
                {data: 'status', name: 'status'},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        // Event delegation for View button
        $(document).on('click', '.btn-view-support', function() {
            const id = $(this).data('id');
            const btn = $(this);
            const originalHtml = btn.html();
            
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

            $.get("/supports/" + id, function(res) {
                const data = res.content;
                $('#support_id').val(data.id);
                $('#view_user_name').text(data.user.name);
                $('#view_subject').text(data.subject || 'General Query');
                $('#view_created_at').text(data.created_at);
                $('#view_message').text(data.message);

                if (data.status === 'closed') {
                    $('#closed_info').show();
                    $('#reason_container').hide();
                    $('#btnCloseSupport').hide();
                    $('#view_closing_reason').text(data.closing_reason);
                    $('#view_closed_at').text(data.closed_at);
                } else {
                    $('#closed_info').hide();
                    $('#reason_container').show();
                    $('#btnCloseSupport').show();
                    // Reset textarea
                    $('#closeSupportForm textarea').val('');
                }

                const modal = new bootstrap.Modal(document.getElementById('viewSupportModal'));
                modal.show();
            }).fail(function(xhr) {
                showAlert(xhr.responseJSON?.message || 'Failed to fetch support details', 'error');
            }).always(function() {
                btn.prop('disabled', false).html(originalHtml);
            });
        });

        $('#btnCloseSupport').on('click', function() {
            const id = $('#support_id').val();
            const btn = $(this);
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Closing...');

            $.ajax({
            url: "/supports/" + id + "/close",
            method: "POST",
            data: $('#closeSupportForm').serialize(),
            success: function(res) {
                const modalEl = document.getElementById('viewSupportModal');
                const modalInstance = window.bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) {
                    modalInstance.hide();
                } else {
                    $(modalEl).find('[data-bs-dismiss="modal"]').click();
                }

                showToast(res.message);
                table.draw();
            },
            error: function(xhr) {
                showAlert(xhr.responseJSON?.message || 'Something went wrong', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('Close Ticket');
            }
            });

        });
    });
</script>
@endpush
