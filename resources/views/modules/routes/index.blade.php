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
                        <th style="width: 50px;">#</th>
                        <th style="width: 45%;">Name</th>
                        <th>Merchants</th>
                        <th class="text-center" style="width: 120px;">Stops</th>
                        <th class="text-center" style="width: 140px;">Actions</th>
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
            ajax: "{{ route('routes.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'merchant_names', name: 'merchant_names', orderable: false, searchable: false},
                {data: 'stops_count', name: 'stops_count', orderable: false, searchable: false, className: 'text-center'},
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
