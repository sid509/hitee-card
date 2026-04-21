@extends('layouts.app')

@section('title', 'Smart Route Finder')

@push('page-css')
<style>
    .journey-step { position: relative; border-left: 2px solid #696cff; padding-left: 30px; padding-bottom: 30px; }
    .journey-step:last-child { border-left: none; padding-bottom: 0; }
    .step-marker { position: absolute; left: -10px; top: 0; width: 18px; height: 18px; border-radius: 50%; background: #696cff; border: 3px solid #fff; box-shadow: 0 0 0 2px #696cff; z-index: 2; }
    .step-marker.transfer { background: #ffab00; box-shadow: 0 0 0 2px #ffab00; }
    .step-marker.destination { background: #71dd37; box-shadow: 0 0 0 2px #71dd37; }
    .intermediate-list { list-style: none; padding-left: 0; border-left: 1px dashed #d9dee3; margin-left: 7px; margin-top: 5px; margin-bottom: 5px; }
    .intermediate-stop { padding-left: 20px; position: relative; font-size: 0.85rem; color: #a1acb8; }
    .intermediate-stop::before { content: '•'; position: absolute; left: 0; color: #d9dee3; font-size: 1.2rem; line-height: 1; top: -2px; }
</style>
@endpush

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Customer /</span> Smart Route Finder
</h4>

<div class="row">
    <!-- Search Form -->
    <div class="col-md-12 mb-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <form action="{{ route('route-finder.index') }}" method="GET">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-5">
                            <label class="form-label fw-medium" for="from">From Stop</label>
                            <select name="from" id="from" class="form-select select2-ajax-stops" required>
                                @if(isset($fromId) && $fromId) <option value="{{ $fromId }}" selected>{{ $from }}</option> @endif
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-medium" for="to">To Stop</label>
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
    <div class="col-md-12">
        <h5 class="mb-4">Journey Plans from <strong>{{ $from }}</strong> to <strong>{{ $to }}</strong></h5>
        
        @if($options->isEmpty())
            <div class="card border-0 shadow-none bg-label-secondary text-center py-5">
                <div class="card-body">
                    <i class="bx bx-error-circle fs-1 mb-2"></i>
                    <h5>No paths found</h5>
                    <p class="mb-0 text-muted">We couldn't find a path between these stops. Try selecting different locations.</p>
                </div>
            </div>
        @else
            @foreach($options as $index => $legs)
                @php
                    $totalFare = collect($legs)->sum('fare');
                    $totalDist = collect($legs)->sum('distance');
                    $isDirect = count($legs) === 1;
                @endphp
                <div class="card mb-4 shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center py-3 border-bottom bg-lighter">
                        <div>
                            <span class="badge bg-primary me-2">Option {{ $index + 1 }}</span>
                            @if($isDirect)
                                <span class="badge bg-label-success">Direct Route</span>
                            @else
                                <span class="badge bg-label-warning">{{ count($legs) - 1 }} Transfer(s)</span>
                            @endif
                        </div>
                        <div class="text-end">
                            <h5 class="mb-0 text-primary fw-bold">Rs. {{ number_format($totalFare, 2) }}</h5>
                            <small class="text-muted">{{ number_format($totalDist, 2) }} km total</small>
                        </div>
                    </div>
                    <div class="card-body pt-4">
                        <div class="journey-timeline ms-2">
                            @foreach($legs as $legIdx => $leg)
                                <!-- Board Leg -->
                                <div class="journey-step">
                                    <div class="step-marker {{ $legIdx > 0 ? 'transfer' : '' }}"></div>
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="w-100">
                                            <div class="d-flex align-items-center mb-1">
                                                <h6 class="mb-0 fw-bold text-heading me-2">{{ $leg['from']->name }}</h6>
                                                <div class="badge bg-label-primary p-1 px-2 d-inline-flex align-items-center" style="font-size: 0.65rem; line-height: 1;">
                                                    <i class="bx bx-log-in-circle me-1" style="font-size: 0.8rem;"></i> {{ $legIdx == 0 ? 'Start' : 'Transfer' }}
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 mb-2 align-items-center">
                                                <small class="text-muted me-1">Available lines:</small>
                                                @foreach($leg['from_available_lines'] as $line)
                                                    <span class="badge bg-label-secondary p-1 px-2" style="font-size: 0.65rem;">{{ $line }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="p-3 my-2 bg-light rounded border border-dashed">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm me-2">
                                                    <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-bus"></i></span>
                                                </div>
                                                <div>
                                                    <div class="fw-bold text-heading">Take: {{ $leg['route']->name }}</div>
                                                    <small class="text-muted">Available: {{ $leg['route']->buses->take(3)->pluck('bus_number')->implode(', ') }}</small>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <span class="fw-bold d-block text-dark">Rs. {{ number_format($leg['fare'], 2) }}</span>
                                                <small class="text-muted">{{ number_format($leg['distance'], 2) }} km</small>
                                            </div>
                                        </div>

                                        <!-- Intermediate Stops -->
                                        @if(isset($leg['intermediates']) && $leg['intermediates']->isNotEmpty())
                                            <button class="btn btn-link btn-xs p-0 text-decoration-none mt-2 fw-medium" type="button" data-bs-toggle="collapse" data-bs-target="#stops-{{ $index }}-{{ $legIdx }}">
                                                <i class="bx bx-list-ul me-1"></i> Passing through {{ $leg['intermediates']->count() }} stops <i class="bx bx-chevron-down"></i>
                                            </button>
                                            <div class="collapse" id="stops-{{ $index }}-{{ $legIdx }}">
                                                <ul class="intermediate-list mt-3">
                                                    @foreach($leg['intermediates'] as $iStop)
                                                        <li class="intermediate-stop mb-1">{{ $iStop->name }}</li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                
                                @if($loop->last)
                                    <!-- Destination -->
                                    <div class="journey-step pb-0">
                                        <div class="step-marker destination"></div>
                                        <div class="w-100">
                                            <div class="d-flex align-items-center mb-1">
                                                <h6 class="mb-0 fw-bold text-success me-2">{{ $leg['to']->name }}</h6>
                                                <div class="d-inline-flex align-items-center text-muted" style="font-size: 0.65rem;">
                                                    <i class="bx bxs-check-circle text-success me-1" style="font-size: 0.8rem;"></i> Destination
                                                </div>
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 mb-1 align-items-center">
                                                <small class="text-muted me-1">Available lines:</small>
                                                @foreach($leg['to_available_lines'] as $line)
                                                    <span class="badge bg-label-secondary p-1 px-2" style="font-size: 0.65rem;">{{ $line }}</span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
    @endif
</div>
@endsection

@push('page-js')
<script type="module">
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
