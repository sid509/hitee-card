@extends('layouts.app')

@section('title', 'Edit Staff')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Staff /</span> Edit
</h4>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Staff Information: {{ $staff->name }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('staff.update', $staff->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label" for="name">Full Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" placeholder="John Doe" value="{{ old('name', $staff->name) }}" required />
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" id="email" name="email" placeholder="john@example.com" value="{{ old('email', $staff->email) }}" required />
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">Password (Leave blank to keep current)</label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Assignments</h5>

                    <div class="mb-3">
                        <label class="form-label">Assign to Buses</label>
                        <div class="row">
                            @foreach($buses as $bus)
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="buses[]" value="{{ $bus->id }}" id="bus-{{ $bus->id }}"
                                            {{ in_array($bus->id, $assignedBuses) ? 'checked' : '' }}>
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
                                        <input class="form-check-input" type="checkbox" name="parkings[]" value="{{ $parking->id }}" id="parking-{{ $parking->id }}"
                                            {{ in_array($parking->id, $assignedParkings) ? 'checked' : '' }}>
                                        <label class="form-check-label" for="parking-{{ $parking->id }}">
                                            {{ $parking->name }}
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">Update Staff</button>
                        <a href="{{ route('staff.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
