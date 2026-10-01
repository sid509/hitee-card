@extends('layouts.app')

@section('title', 'Validator Devices')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Card Management /</span> Validators
</h4>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Registered Validator Devices</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered dat-table" id="cm-validators-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Device ID</th>
                        <th>Serial Number</th>
                        <th>Operator</th>
                        <th>Status</th>
                        <th>Route</th>
                        <th>Trips</th>
                        <th>Last Heartbeat</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@push('page-js')
<script>
$(function() {
    $('#cm-validators-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("card-management.validators.index") }}',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'device_id', name: 'device_id' },
            { data: 'serial_number', name: 'serial_number' },
            { data: 'operator_id', name: 'operator_id' },
            { data: 'status_badge', name: 'status_badge', orderable: false },
            { data: 'assigned_route_id', name: 'assigned_route_id' },
            { data: 'trips_count', name: 'trips_count', orderable: false },
            { data: 'last_heartbeat_at', name: 'last_heartbeat_at' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        order: [[8, 'desc']],
    });
});
</script>
@endpush
@endsection
