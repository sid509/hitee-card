<div class="mb-3">
    <label class="form-label" for="name">Permission Name</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-key"></i></span>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
            placeholder="e.g. edit users" value="{{ old('name', $permission->name) }}" required />
    </div>
    @error('name')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2"><i class="bx bx-save me-1"></i> Save Permission</button>
    <a href="{{ route('permissions.index') }}" class="btn btn-label-secondary">Cancel</a>
</div>
