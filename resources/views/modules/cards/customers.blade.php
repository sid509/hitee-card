@extends('layouts.app')

@section('title', 'Card Management — Customers')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Card Management /</span> Customers
</h4>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Card Management Customers</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered dat-table" id="cm-customers-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Customer Number</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Cards</th>
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
    $('#cm-customers-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("cards.customers.index") }}',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'customer_number', name: 'customer_number' },
            { data: 'full_name', name: 'full_name' },
            { data: 'category', name: 'category' },
            { data: 'phone_number', name: 'phone_number' },
            { data: 'email', name: 'email' },
            { data: 'cards_count', name: 'cards_count', orderable: false },
            { data: 'created_at', name: 'created_at' },
            { data: 'action', name: 'action', orderable: false, searchable: false },
        ],
        order: [[7, 'desc']],
    });
});
</script>
@endpush
@endsection
