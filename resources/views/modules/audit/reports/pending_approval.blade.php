@extends('layouts.app')

@section('title', 'Users Pending Approval')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Audit /</span> Pending Approval
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Users Verified but Awaiting Activation</h5>
        <a href="{{ route('audit.index') }}" class="btn btn-sm btn-secondary"><i class="bx bx-chevron-left me-1"></i> Back to Dashboard</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Last Notified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
$(function () {
    const table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('audit.pending-approval') }}",
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'user_info', name: 'name'},
            {data: 'status_label', name: 'status'},
            {data: 'last_notified_at', name: 'last_notified_at'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });

    $(document).on('click', '.approve-user-btn', function() {
        const id = $(this).data('id');
        if (confirm('Are you sure you want to approve and activate this user?')) {
            $.post("{{ url('users') }}/" + id + "/approve", { _token: "{{ csrf_token() }}" }, function(res) {
                if (res.status) {
                    showAlert(res.message, 'success');
                    table.ajax.reload();
                } else {
                    showAlert(res.message, 'error');
                }
            });
        }
    });
});
</script>
@endpush
