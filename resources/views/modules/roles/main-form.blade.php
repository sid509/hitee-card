<div class="mb-3">
    <label class="form-label" for="name">Role Name</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-shield"></i></span>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
            placeholder="e.g. Manager" value="{{ old('name', $role->name) }}" required />
    </div>
    @error('name')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label">Permissions</label>
    <div class="row">
        @forelse($permissions as $permission)
            <div class="col-md-3">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->id }}" 
                        id="perm-{{ $permission->id }}" 
                        {{ $role->permissions->contains($permission->id) ? 'checked' : '' }}>
                    <label class="form-check-label" for="perm-{{ $permission->id }}">
                        {{ $permission->name }}
                    </label>
                </div>
            </div>
        @empty
            <div class="col-12 text-muted">No permissions defined yet.</div>
        @endforelse
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2"><i class="bx bx-save me-1"></i> Save Role</button>
    <a href="{{ route('roles.index') }}" class="btn btn-label-secondary">Cancel</a>
</div>
