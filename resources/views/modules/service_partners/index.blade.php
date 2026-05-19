@extends('layouts.app')

@section('title', 'Service Partners')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Operations /</span> Service Partners
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Service Partners List</h5>
        @if(auth()->user()->hasRole('super-admin'))
        <a href="{{ route('service-partners.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Add Service Partner
        </a>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Merchant</th>
                        <th>Type</th>
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
        ajax: "{{ route('service-partners.index') }}",
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'name', name: 'name'},
            {data: 'merchant.name', name: 'merchant.name', defaultContent: 'N/A'},
            {data: 'service_type', name: 'service_type'},
            {data: 'status', name: 'status'},
            {data: 'action', name: 'action', orderable: false, searchable: false},
        ]
    });
});
</script>
@endpush
