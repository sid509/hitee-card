@extends('layouts.app')

@section('title', 'Buses')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">{{ __('messages.fleet_management') }} /</span> {{ __('messages.buses') }}
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('messages.buses') }} {{ __('messages.list') }}</h5>
        @if(auth()->user()->hasRole('super-admin'))
        <a href="{{ route('buses.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> {{ __('messages.add') }} {{ __('messages.buses') }}
        </a>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Number</th>
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
            ajax: "{{ route('buses.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {
                    data: 'name', 
                    name: 'name',
                    createdCell: function(td) {
                        $(td).addClass('text-truncate-cell').attr('title', $(td).text());
                    }
                },
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
