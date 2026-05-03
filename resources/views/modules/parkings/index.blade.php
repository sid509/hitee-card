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
                table.data-table th:nth-child(2) { width: 220px; }
                table.data-table th:nth-child(3) { width: 220px; }
                table.data-table th:nth-child(4) { width: 150px; }
                table.data-table th:nth-child(5) { width: 120px; text-align: center; }
                table.data-table th:nth-child(6) { width: 150px; }
                table.data-table th:nth-child(7) { width: 150px; }

                table.data-table td:nth-child(5) { text-align: center; }
                .dark-style table.data-table td { border-color: rgba(255,255,255,0.05) !important; }
            </style>
            <table class="table table-hover data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Parking Name</th>
                        <th>Location</th>
                        <th>Entry Fee</th>
                        <th>Merchant</th>
                        <th>Status</th>
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
            
            order: [[6, 'desc']],
            ajax: "{{ route('parkings.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'location', name: 'location'},
                {data: 'entry_fee', name: 'entry_fee', orderable: false, searchable: false},
                {data: 'merchant.name', name: 'merchant.name', defaultContent: 'N/A'},
                {data: 'status', name: 'status'},
                {data: 'created_at', name: 'created_at'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });
    });
    </script>
    @endpush
