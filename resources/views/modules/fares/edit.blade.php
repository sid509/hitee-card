@extends('layouts.app')

@section('title', 'Edit Fare')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Fares /</span> Edit
</h4>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <h5 class="card-header">Edit Fare: {{ $fare->name }}</h5>
            <div class="card-body">
                <form action="{{ route('fares.update', $fare->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fare Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $fare->name) }}" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Route</label>
                            <input type="text" class="form-control" value="{{ $fare->route->name }}" disabled>
                            <input type="hidden" name="route_id" id="route_select_fare" value="{{ $fare->route_id }}">
                        </div>
                    </div>

                    <div class="mt-4" id="matrix-container">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <h6 class="mb-0">Fare Matrix (Point-to-Point)</h6>
                                <p class="text-muted small mb-0">Prices are saved automatically when you change a value.</p>
                            </div>
                            <span class="badge bg-label-info"><i class="bx bx-bolt-circle me-1"></i> Auto-Save Enabled</span>
                        </div>
                        <div id="matrix-form-wrapper" class="table-responsive">
                            <div class="text-center py-4"><span class="spinner-border text-primary"></span> Loading current matrix...</div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">Update Fare Details</button>
                        <a href="{{ route('fares.index') }}" class="btn btn-label-secondary">Back to List</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function() {
        const routeId = $('#route_select_fare').val();
        const fareId = "{{ $fare->id }}";

        if (routeId) {
            $.get("{{ route('fares.matrix-form') }}", { route_id: routeId, fare_id: fareId }, function(html) {
                $('#matrix-form-wrapper').html(html);
            });
        }

        // Merchant Search for Admin
        if ($('.select2-ajax-merchant').length) {
            $('.select2-ajax-merchant').select2({
                ajax: {
                    url: "{{ route('search.merchants') }}",
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term, page: params.page }),
                    processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                    cache: true
                },
                placeholder: 'Search Merchant...',
                minimumInputLength: 1,
                width: '100%'
            });
        }
    });
</script>
@endpush
