@extends('layouts.app')

@section('title', 'Cards')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Management /</span> Cards
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cards List</h5>
        @if(auth()->user()->hasRole('super-admin'))
        <a href="{{ route('cards.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Issue Card
        </a>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Card Number</th>
                        <th>HWID</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Current Active</th>
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
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('cards.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'card_number', name: 'card_number'},
                {data: 'hwid', name: 'hwid'},
                {data: 'user.name', name: 'user.name', defaultContent: 'N/A'},
                {data: 'status', name: 'status'},
                {data: 'is_active_badge', name: 'is_active_badge', orderable: false, searchable: false},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
    });
</script>
@endpush
