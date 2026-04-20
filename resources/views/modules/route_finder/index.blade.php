@extends('layouts.app')

@section('title', 'Smart Route Finder')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Customer /</span> Smart Route Finder
</h4>

<div class="row">
    <!-- Search Form -->
    <div class="col-md-12 mb-4">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('route-finder.index') }}" method="GET">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label" for="from">From Stop</label>
                            <select name="from" id="from" class="form-select select2-ajax-stops" required>
                                @if(isset($fromId) && $fromId) <option value="{{ $fromId }}" selected>{{ $from }}</option> @endif
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="to">To Stop</label>
                            <select name="to" id="to" class="form-select select2-ajax-stops" required>
                                @if(isset($toId) && $toId) <option value="{{ $toId }}" selected>{{ $to }}</option> @endif
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bx bx-search-alt me-1"></i> Find Path
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if($from && $to)
    <!-- Results Section -->
    <div class="col-md-12">
        <div class="nav-align-top mb-4">
            <ul class="nav nav-pills mb-3" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-direct" aria-controls="navs-pills-direct" aria-selected="true">
                        Direct Routes ({{ $directRoutes->count() }})
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-connected" aria-controls="navs-pills-connected" aria-selected="false">
                        Connecting Routes ({{ $connectedRoutes->count() }})
                    </button>
                </li>
            </ul>
            <div class="tab-content">
                <!-- Direct Routes Tab -->
                <div class="tab-pane fade show active" id="navs-pills-direct" role="tabpanel">
                    @if($directRoutes->isEmpty())
                        <div class="text-center py-5">
                            <i class="bx bx-info-circle fs-1 text-muted mb-2"></i>
                            <p class="text-muted">No direct buses found between these stops.</p>
                        </div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($directRoutes as $route)
                                <div class="list-group-item p-4 border rounded mb-3 shadow-sm">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <div>
                                            <h5 class="mb-1 text-primary">{{ $route->name }}</h5>
                                            <p class="text-muted small mb-0">{{ $route->description }}</p>
                                        </div>
                                        <span class="badge bg-label-success">Direct</span>
                                    </div>
                                    <h6 class="mt-3 mb-2">Available Buses on this route:</h6>
                                    <div class="row g-2">
                                        @forelse($route->buses as $bus)
                                            <div class="col-md-4">
                                                <a href="{{ route('buses.show', $bus->id) }}" class="d-block p-3 border rounded hover-light">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bx bx-bus fs-3 text-primary me-2"></i>
                                                        <div>
                                                            <div class="fw-medium text-body">{{ $bus->name }}</div>
                                                            <div class="small text-muted">{{ $bus->bus_number }}</div>
                                                        </div>
                                                    </div>
                                                </a>
                                            </div>
                                        @empty
                                            <div class="col-12"><small class="text-muted">No buses currently active on this route.</small></div>
                                        @endforelse
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Connecting Routes Tab -->
                <div class="tab-pane fade" id="navs-pills-connected" role="tabpanel">
                    @if($connectedRoutes->isEmpty())
                        <div class="text-center py-5">
                            <i class="bx bx-transfer fs-1 text-muted mb-2"></i>
                            <p class="text-muted">No connecting routes found for this journey.</p>
                        </div>
                    @else
                        @foreach($connectedRoutes as $conn)
                            <div class="list-group-item p-4 border rounded mb-4 shadow-sm">
                                <div class="timeline-journey d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
                                    <div class="text-center">
                                        <div class="badge bg-primary mb-1">Start</div>
                                        <div class="fw-bold">{{ $from }}</div>
                                    </div>
                                    <div class="flex-grow-1 border-top border-primary border-dashed position-relative" style="min-width: 50px;">
                                        <div class="position-absolute top-50 start-50 translate-middle bg-white px-2">
                                            <i class="bx bx-right-arrow-alt text-primary fs-4"></i>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="badge bg-warning mb-1">Change At</div>
                                        <div class="fw-bold">{{ $conn['connection_stop'] }}</div>
                                    </div>
                                    <div class="flex-grow-1 border-top border-primary border-dashed position-relative" style="min-width: 50px;">
                                        <div class="position-absolute top-50 start-50 translate-middle bg-white px-2">
                                            <i class="bx bx-right-arrow-alt text-primary fs-4"></i>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="badge bg-success mb-1">End</div>
                                        <div class="fw-bold">{{ $to }}</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 border-end">
                                        <label class="small text-muted mb-2">1st Leg: {{ $conn['leg1']->name }}</label>
                                        <div class="list-group list-group-flush small">
                                            @foreach($conn['leg1']->buses->take(3) as $bus)
                                                <div class="list-group-item px-0 py-1 border-0">
                                                    <i class="bx bx-bus me-1 text-primary"></i> {{ $bus->name }} ({{ $bus->bus_number }})
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div class="col-md-6 ps-md-4">
                                        <label class="small text-muted mb-2">2nd Leg: {{ $conn['leg2']->name }}</label>
                                        <div class="list-group list-group-flush small">
                                            @foreach($conn['leg2']->buses->take(3) as $bus)
                                                <div class="list-group-item px-0 py-1 border-0">
                                                    <i class="bx bx-bus me-1 text-success"></i> {{ $bus->name }} ({{ $bus->bus_number }})
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('page-js')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        $('.select2-ajax-stops').select2({
            ajax: {
                url: "{{ route('search.stops') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term, page: params.page }),
                processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                cache: true
            },
            placeholder: 'Select a stop...',
            minimumInputLength: 1,
            width: '100%'
        });
    });
</script>
@endpush
