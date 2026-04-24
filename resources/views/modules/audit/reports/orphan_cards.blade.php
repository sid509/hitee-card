@extends('layouts.app')

@section('title', 'Orphan Cards')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Audit /</span> Orphan Cards
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cards not assigned to any user</h5>
        <a href="{{ route('audit.index') }}" class="btn btn-sm btn-secondary"><i class="bx bx-chevron-left me-1"></i> Back to Dashboard</a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Card Number</th>
                        <th>HWID</th>
                        <th>Balance</th>
                        <th>Status</th>
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
    $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('audit.orphan-cards') }}",
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'card_number', name: 'card_number'},
            {data: 'hwid', name: 'hwid'},
            {data: 'balance', name: 'balance'},
            {data: 'status', name: 'status', render: function(data) {
                return `<span class="badge bg-label-info">${data ? data.toUpperCase() : 'UNKNOWN'}</span>`;
            }},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });
});
</script>
@endpush
