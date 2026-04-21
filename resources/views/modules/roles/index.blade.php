@extends('layouts.app')

@section('title', 'Roles')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Roles /</span> List
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Roles Management</h5>
        <a href="{{ route('roles.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Add Role
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <style>
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
                table.data-table th:nth-child(1) { width: 50px; }
                table.data-table th:nth-child(2) { width: 250px; }
                table.data-table th:nth-child(3) { width: 250px; }
                table.data-table th:nth-child(4) { width: 180px; }
                table.data-table th:nth-child(5) { width: 150px; }

                .dark-style table.data-table td { border-color: rgba(255,255,255,0.05) !important; }
            </style>
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Created At</th>
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
            responsive: false,
            autoWidth: false,
            stateSave: true,
            order: [[3, 'desc']],
            ajax: "{{ route('roles.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'slug', name: 'slug'},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
    });
</script>
@endpush
