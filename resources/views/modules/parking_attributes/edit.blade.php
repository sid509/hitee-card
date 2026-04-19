@extends('layouts.app')

@section('title', 'Edit Parking Attribute')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Administration /</span> Edit Parking Attribute
</h4>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <h5 class="card-header">Edit Attribute</h5>
            <div class="card-body">
                <form action="{{ route('parking-attributes.update', $attribute->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-3">
                        <label class="form-label" for="name">Attribute Name</label>
                        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
                            value="{{ old('name', $attribute->name) }}" required />
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="icon">SVG Icon</label>
                        @if($attribute->icon)
                            <div class="mb-2">
                                <p class="small text-muted mb-1">Current Icon:</p>
                                <img src="{{ asset('storage/' . $attribute->icon) }}" alt="{{ $attribute->name }}" height="40" width="40" class="border p-1">
                            </div>
                        @endif
                        <input type="file" class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" 
                            accept=".svg" />
                        <div class="form-text">Leave blank to keep the current icon. Only SVG allowed. Max 1MB.</div>
                        @error('icon') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">Update Attribute</button>
                        <a href="{{ route('parking-attributes.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
