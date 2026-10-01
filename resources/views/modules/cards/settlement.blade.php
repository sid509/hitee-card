@extends('layouts.app')

@section('title', 'Settlement Batches')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Card Management /</span> Settlement
</h4>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Settlement Batches</h5></div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered dat-table" id="cm-settlement-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Batch ID</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total Fare</th>
                        <th>Commission</th>
                        <th>Payout</th>
                        <th>Entries</th>
                        <th>Created</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@push('page-js')
<script>
$(function() {
    $('#cm-settlement-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("cards.settlement.index") }}',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'id', name: 'id' },
            { data: 'settlement_date', name: 'settlement_date' },
            { data: 'status_badge', name: 'status_badge', orderable: false },
            { data: 'total_fare_minor_units', name: 'total_fare_minor_units' },
            { data: 'total_commission_minor_units', name: 'total_commission_minor_units' },
            { data: 'total_payout_minor_units', name: 'total_payout_minor_units' },
            { data: 'entries_count', name: 'entries_count', orderable: false },
            { data: 'created_at', name: 'created_at' },
        ],
        order: [[8, 'desc']],
        columnDefs: [
            {
                targets: [4, 5, 6],
                render: function(data) {
                    if (data === null || data === undefined || data === '') return '—';
                    return (data / 100).toFixed(2);
                }
            }
        ]
    });
});
</script>
@endpush
@endsection
