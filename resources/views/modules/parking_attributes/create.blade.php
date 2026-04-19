@extends('layouts.app')

@section('title', 'Add Parking Attribute')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Administration /</span> Add Parking Attribute
</h4>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <h5 class="card-header">New Attribute</h5>
            <div class="card-body">
                <form action="{{ route('parking-attributes.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mb-3">
                        <label class="form-label" for="name">Attribute Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
                            placeholder="e.g. CCTV Surveillance" value="{{ old('name') }}" required />
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="icon">SVG Icon</label>
                        <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" 
                            accept=".svg" required />
                        <div class="form-text">Only SVG files are allowed. Max 1MB.</div>
                        @error('icon') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">Create Attribute</button>
                        <a href="{{ route('parking-attributes.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
