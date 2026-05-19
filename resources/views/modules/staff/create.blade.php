@extends('layouts.app')

@section('title', 'Add Staff')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Staff /</span> Add New
</h4>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Staff Information</h5>
                <small class="text-muted float-end">Search existing or create new</small>
            </div>
            <div class="card-body">
                <form action="{{ route('staff.store') }}" method="POST">
                    @csrf
                    
                    <div class="mb-4">
                        <label class="form-label" for="user_search">Search Existing User (Optional)</label>
                        <div class="input-group input-group-merge">
                            <span class="input-group-text"><i class="bx bx-search"></i></span>
                            <select id="user_search" name="user_id" class="form-select select2-ajax-users">
                                <option value="">-- Create New User --</option>
                            </select>
                        </div>
                        <div class="form-text">If you select an existing user, the details below will be ignored.</div>
                    </div>

                    <hr class="my-4">

                    <div id="new_user_fields">
                        <div class="mb-3">
                            <label class="form-label" for="name">Full Name</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="bx bx-user"></i></span>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" placeholder="John Doe" value="{{ old('name') }}" />
                            </div>
                            @error('name') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="email">Email</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="john@example.com" value="{{ old('email') }}" />
                            </div>
                            @error('email') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group input-group-merge">
                                <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                            </div>
                            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Assignments</h5>

                    <div class="mb-3">
                        <label class="form-label">Assign to Buses</label>
                        <div class="row">
                            @foreach($buses as $bus)
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="buses[]" value="{{ $bus->id }}" id="bus-{{ $bus->id }}">
                                        <label class="form-check-label" for="bus-{{ $bus->id }}">
                                            {{ $bus->bus_number }} ({{ $bus->name }})
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assign to Parkings</label>
                        <div class="row">
                            @foreach($parkings as $parking)
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="parkings[]" value="{{ $parking->id }}" id="parking-{{ $parking->id }}">
                                        <label class="form-check-label" for="parking-{{ $parking->id }}">
                                            {{ $parking->name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2"><i class="bx bx-plus me-1"></i> Add Staff</button>
                        <a href="{{ route('staff.index') }}" class="btn btn-label-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const userSearchSelect = $('.select2-ajax-users');
    if (userSearchSelect.length) {
        userSearchSelect.select2({
            placeholder: 'Search for a user...',
            allowClear: true,
            ajax: {
                url: "{{ route('staff.search') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term }),
                processResults: data => ({
                    results: data.map(user => ({
                        id: user.id,
                        text: `${user.name} (${user.email})`
                    }))
                }),
                cache: true
            }
        });

        userSearchSelect.on('change', function() {
            if ($(this).val()) {
                $('#new_user_fields').slideUp();
                $('#new_user_fields input').prop('required', false);
            } else {
                $('#new_user_fields').slideDown();
                $('#new_user_fields input').prop('required', true);
            }
        });
    }
});
</script>
@endpush
