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
                table.data-table th:nth-child(3) { width: 200px; }
                table.data-table th:nth-child(4) { width: 120px; text-align: center; }
                table.data-table th:nth-child(5) { width: 150px; }
                table.data-table th:nth-child(6) { width: 150px; text-align: center; }

                table.data-table td:nth-child(4),
                table.data-table td:nth-child(6) { text-align: center; }
                .dark-style table.data-table td { border-color: rgba(255,255,255,0.05) !important; }
            </style>
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Route Name</th>
                        <th>Assigned Merchants</th>
                        <th>Stops Count</th>
                        <th>Created At</th>
                        <th>Action</th>
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
            responsive: false,
            autoWidth: false,
            
            order: [[4, 'desc']],
            ajax: "{{ route('routes.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'merchant_names', name: 'merchant_names', orderable: false, searchable: false},
                {data: 'stops_count', name: 'stops_count', orderable: false, searchable: false, className: 'text-center'},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center'},
            ],
            drawCallback: function() {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        });
    });
</script>
@endpush
