@extends('layouts.app')

@section('title', 'Fare Management')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Fleet Management /</span> Fares
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Fare Proposals</h5>
        <a href="{{ route('fares.create') }}" class="btn btn-primary">
            <i class="bx bx-plus me-1"></i> Propose New Fare
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Route</th>
                        <th>Merchant</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>

@push('modals')
<!-- Assign to Bus Modal -->
<div class="modal fade" id="assignBusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Fare to Bus</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="assignBusForm">
                @csrf
                <input type="hidden" name="fare_id" id="assign_fare_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select Bus</label>
                        <select name="bus_id" id="bus_search_fare" class="form-select" required>
                            <option value="">Search Bus...</option>
                        </select>
                        <div class="form-text">The selected bus will follow the route and pricing linked to this fare.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Confirm Assignment</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endpush

@endsection

@push('page-js')
<script type="module">
    $(function () {
        const table = $('.data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: "{{ route('fares.index') }}",
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'name', name: 'name'},
                {data: 'route.name', name: 'route.name'},
                {data: 'merchant.name', name: 'merchant.name', defaultContent: 'N/A'},
                {data: 'status', name: 'status'},
                {data: 'action', name: 'action', orderable: false, searchable: false},
            ]
        });

        // Approve Fare
        $(document).on('click', '.approve-fare', function() {
            const id = $(this).data('id');
            showConfirm('Approve Fare?', 'This fare will become available for assignment to buses.').then(result => {
                if (result.isConfirmed) {
                    $.post(`/fares/${id}/approve`, { _token: "{{ csrf_token() }}" }, function(res) {
                        showAlert(res.message);
                        table.draw();
                    });
                }
            });
        });

        // Assign Bus Modal
        $(document).on('click', '.assign-bus', function() {
            const id = $(this).data('id');
            $('#assign_fare_id').val(id);
            new bootstrap.Modal(document.getElementById('assignBusModal')).show();
        });

        // Bus Search for Assignment
        $('#bus_search_fare').select2({
            dropdownParent: $('#assignBusModal'),
            ajax: {
                url: "{{ route('search.references') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term, type: 'fare_deduction' }),
                processResults: data => ({ results: data.results }),
                cache: true
            },
            placeholder: 'Search Bus...',
            minimumInputLength: 0,
            width: '100%'
        });

        $('#assignBusForm').on('submit', function(e) {
            e.preventDefault();
            $.post("{{ route('fares.assign-bus') }}", $(this).serialize(), function(res) {
                showAlert(res.message);
                $('#assignBusModal').modal('hide');
            }).fail(xhr => showAlert(xhr.responseJSON.message || 'Error assigning bus', 'error'));
        });
    });
</script>
@endpush
