@extends('layouts.app')

@section('title', 'Propose Fare')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Fares /</span> Propose
</h4>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <h5 class="card-header">New Fare Proposal</h5>
            <div class="card-body">
                <form action="{{ route('fares.store') }}" method="POST">
                    @csrf
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Proposal Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Standard Fare Hike 2026" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Route</label>
                            <select name="route_id" id="route_select_fare" class="form-select" required>
                                <option value="">Select Route</option>
                                @foreach($routes as $route)
                                    <option value="{{ $route->id }}">{{ $route->name }} ({{ $route->stops->count() }} stops)</option>
                                @endforeach
                            </select>
                        </div>
                        @if(auth()->user()->hasRole('super-admin'))
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Merchant</label>
                            <select name="merchant_id" class="form-select select2-ajax-merchant" required></select>
                        </div>
                        @endif
                    </div>

                    <div class="mt-4" id="matrix-container" style="display: none;">
                        <h6>Fare Matrix (Point-to-Point)</h6>
                        <p class="text-muted small">Enter the fare in Rs. between the following stops.</p>
                        <div id="matrix-form-wrapper" class="table-responsive">
                            <!-- Loaded via AJAX -->
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">Submit Proposal</button>
                        <a href="{{ route('fares.index') }}" class="btn btn-label-secondary">Cancel</a>
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
        $('#route_select_fare').on('change', function() {
            const routeId = $(this).val();
            if (!routeId) {
                $('#matrix-container').hide();
                return;
            }

            $('#matrix-form-wrapper').html('<div class="text-center py-4"><span class="spinner-border text-primary"></span> Loading matrix...</div>');
            $('#matrix-container').show();

            $.get("{{ route('fares.matrix-form') }}", { route_id: routeId }, function(html) {
                $('#matrix-form-wrapper').html(html);
            });
        });

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
