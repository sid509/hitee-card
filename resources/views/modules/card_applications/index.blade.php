@extends('layouts.app')

@section('title', 'Card Applications')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="py-3 mb-0"><span class="text-muted fw-light">Account /</span> Card Applications</h4>
        @if(auth()->user()->hasRole('customers'))
            <a href="{{ route('card-applications.create') }}" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> Apply for Card
            </a>
        @endif
    </div>

    <div class="card">
        <h5 class="card-header">Application History</h5>
        <div class="card-body">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover data-table-applications w-100">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
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
        $('.data-table-applications').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('card-applications.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'created_at', name: 'created_at', render: function(data) {
                    return new Date(data).toLocaleDateString();
                }},
                {data: 'user.name', name: 'user.name'},
                {data: 'type', name: 'type'},
                {data: 'status', name: 'status'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ],
            order: [[1, 'desc']]
        });
    });
</script>
@endpush
