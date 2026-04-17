@extends('layouts.app')

@section('title', 'Routes')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Fleet Management /</span> Routes
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Routes List</h5>
        <a href="{{ route('routes.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Add Route
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Merchant</th>
                        <th>Stops</th>
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
            responsive: true,
            ajax: "{{ route('routes.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'merchant.name', name: 'merchant.name', defaultContent: 'N/A'},
                {data: 'stops_count', name: 'stops_count', orderable: false, searchable: false},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
    });
</script>
@endpush
