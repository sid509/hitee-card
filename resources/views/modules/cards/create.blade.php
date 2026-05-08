@extends('layouts.app')

@section('title', 'Issue Card')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Management / Cards /</span> Issue
</h4>

<div class="row">
    <div class="col-xl">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Issue New Card</h5>
            </div>
            <div class="card-body">
                @if(isset($application))
                <div class="alert alert-info d-flex align-items-center mb-4">
                    <i class="bx bx-info-circle me-2"></i>
                    <div>
                        Processing application for <strong>{{ $application->user->name }}</strong>. 
                        Card Type: <span class="badge bg-label-primary">{{ ucfirst($application->type) }}</span>
                    </div>
                </div>
                @endif
                <form action="{{ route('cards.store') }}" method="POST">
                    @csrf
                    @if(isset($application))
                        <input type="hidden" name="application_id" value="{{ $application->id }}">
                    @endif
                    @include('modules.cards.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function() {
        $('.select2-users').select2({
            ajax: {
                url: "{{ route('search.users') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term, page: params.page }),
                processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                cache: true
            },
            placeholder: 'Search Customer...',
            allowClear: true,
            width: '100%'
        });
    });
</script>
@endpush
