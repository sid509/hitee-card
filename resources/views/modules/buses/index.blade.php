@extends('layouts.app')

@section('title', 'Buses')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Management /</span> Buses
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Buses List</h5>
        @if(auth()->user()->hasRole('super-admin'))
        <a href="{{ route('buses.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Add Bus
        </a>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Bus Number</th>
                        <th>Merchant</th>
                        <th>Route</th>
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
        var table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('buses.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'bus_number', name: 'bus_number'},
                {data: 'merchant.name', name: 'merchant.name', defaultContent: 'N/A'},
                {data: 'route_name', name: 'route_name', orderable: false},
                {data: 'status', name: 'status'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
    });
</script>
@endpush
