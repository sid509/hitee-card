<div class="mb-3">
    <label class="form-label" for="name">Full Name</label>
    <div class="input-group input-group-merge">
        <span id="name-icon" class="input-group-text"><i class="bx bx-user"></i></span>
        <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name" 
            placeholder="John Doe" value="{{ old('name', $user->name) }}" required />
    </div>
    @error('name')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="email">Email</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-envelope"></i></span>
        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" 
            placeholder="john.doe@example.com" value="{{ old('email', $user->email) }}" required />
    </div>
    @error('email')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3 form-password-toggle">
    <label class="form-label" for="password">Password {{ isset($user->id) ? '(Leave blank to keep current)' : '' }}</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror" 
            placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" 
            {{ isset($user->id) ? '' : 'required' }} />
        <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
    </div>
    @error('password')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3 form-password-toggle">
    <label class="form-label" for="password_confirmation">Confirm Password</label>
    <div class="input-group input-group-merge">
        <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" 
            placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" 
            {{ isset($user->id) ? '' : 'required' }} />
        <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
    </div>
</div>

<div class="mb-3">
    <label class="form-label">Roles</label>
    <div class="row">
        @foreach(\App\Models\Role::all() as $role)
            <div class="col-md-4">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->id }}" 
                        id="role-{{ $role->id }}" 
                        {{ $user->roles->contains($role->id) ? 'checked' : '' }}>
                    <label class="form-check-label" for="role-{{ $role->id }}">
                        {{ $role->name }}
                    </label>
                </div>
            </div>
        @endforeach
    </div>
</div>

<div class="mt-4">
    <button type="submit" class="btn btn-primary me-2">Save User</button>
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">Cancel</a>
</div>
