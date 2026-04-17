@extends('layouts.app')

@section('title', 'Activity Logs')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">System /</span> Activity Logs
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Audit Trail</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>Properties</th>
                        <th>Date & Time</th>
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
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('activity-logs.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'user_name', name: 'user_name'},
                {data: 'action', name: 'action'},
                {data: 'description', name: 'description'},
                {data: 'ip_address', name: 'ip_address'},
                {data: 'properties', name: 'properties', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at'},
            ],
            order: [[6, 'desc']]
        });
    });
</script>
@endpush
