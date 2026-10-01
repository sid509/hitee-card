@extends('layouts.app')

@section('title', 'Card Management')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Card Management /</span> Cards
</h4>

<div class="row mb-4">
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar me-2"><span class="avatar-initial rounded bg-label-primary"><i class="bx bx-credit-card"></i></span></div>
                    <h6 class="mb-0">Total Cards</h6>
                </div>
                <h4 class="mb-0">{{ $stats['total'] }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar me-2"><span class="avatar-initial rounded bg-label-success"><i class="bx bx-check-circle"></i></span></div>
                    <h6 class="mb-0">Active</h6>
                </div>
                <h4 class="mb-0">{{ $stats['active'] }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar me-2"><span class="avatar-initial rounded bg-label-info"><i class="bx bx-badge-check"></i></span></div>
                    <h6 class="mb-0">Issued</h6>
                </div>
                <h4 class="mb-0">{{ $stats['issued'] }}</h4>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3 mb-3">
        <div class="card h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <div class="avatar me-2"><span class="avatar-initial rounded bg-label-danger"><i class="bx bx-block"></i></span></div>
                    <h6 class="mb-0">Blocked</h6>
                </div>
                <h4 class="mb-0">{{ $stats['blocked'] }}</h4>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Registered Cards</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered dat-table" id="cm-cards-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>UID</th>
                        <th>Card Number</th>
                        <th>Status</th>
                        <th>Environment</th>
                        <th>Customer</th>
                        <th>Key Profile</th>
                        <th>Issued</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@push('page-js')
<script>
$(function() {
    $('#cm-cards-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("card-management.index") }}',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'uid', name: 'uid' },
            { data: 'card_number', name: 'card_number' },
            { data: 'status_badge', name: 'status_badge', orderable: false },
            { data: 'env_badge', name: 'env_badge', orderable: false },
            { data: 'customer_name', name: 'customer_name' },
            { data: 'profile_info', name: 'profile_info', orderable: false },
            { data: 'issued_at', name: 'issued_at' },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        order: [[8, 'desc']],
    });
});
</script>
@endpush
@endsection
