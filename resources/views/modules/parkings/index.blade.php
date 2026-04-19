@extends('layouts.app')

@section('title', 'Parkings')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">{{ __('messages.fleet_management') }} /</span> {{ __('messages.parkings') }}
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ __('messages.parkings') }} {{ __('messages.list') }}</h5>
        @if(auth()->user()->hasRole('super-admin'))
        <a href="{{ route('parkings.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> {{ __('messages.add') }} {{ __('messages.parkings') }}
        </a>
        @endif
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>{{ __('messages.id') }}</th>
                        <th>{{ __('messages.name') }}</th>
                        <th>{{ __('messages.location') }}</th>
                        <th>{{ __('messages.merchant') }}</th>
                        <th>{{ __('messages.status') }}</th>
                        <th>{{ __('messages.actions') }}</th>
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
            ajax: "{{ route('parkings.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'location', name: 'location'},
                {data: 'merchant.name', name: 'merchant.name', defaultContent: 'N/A'},
                {data: 'status', name: 'status'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
    });
</script>
@endpush
