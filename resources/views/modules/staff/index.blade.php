@extends('layouts.app')

@section('title', 'Staff Management')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Staff /</span> List
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Staff List</h5>
        <a href="{{ route('staff.create') }}" class="btn btn-primary btn-sm">
            <i class="bx bx-plus me-1"></i> Add Staff
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive text-nowrap">
            <table class="table table-hover data-table w-100">
                <thead>
                    <tr>
                        <th style="width: 30px;">#</th>
                        <th>Staff Details</th>
                        <th>Assignments</th>
                        <th>Joined At</th>
                        <th class="text-center" style="width: 100px;">Status</th>
                        <th class="text-center" style="width: 120px;">Actions</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var table = $('.data-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: "{{ route('staff.index') }}",
        order: [[3, 'desc']],
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '30px'},
            {data: 'staff_info', name: 'name'},
            {data: 'assignments', name: 'assignments', orderable: false, searchable: false, className: 'text-center'},
            {data: 'created_at', name: 'created_at'},
            {data: 'status', name: 'status', className: 'text-center', render: function(data) {
                if (!data) return '-';
                let classMap = { active: 'bg-label-success', inactive: 'bg-label-secondary' };
                return `<span class="badge ${classMap[data] || 'bg-label-info'}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
            }},
            {data: 'action', name: 'action', orderable: false, searchable: false, className: 'text-center'},
        ],
        drawCallback: function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            $('.detach-btn').on('click', function(e) {
                e.preventDefault();
                var form = $(this).closest('form');
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will detach the staff from your merchant account!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, detach it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        }
    });
  });
</script>
@endpush
