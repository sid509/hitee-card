@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Account Settings /</span> Profile
</h4>

<div class="row">
    <div class="col-md-12">
        @if (session('success'))
            <div class="alert alert-success alert-dismissible" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card mb-4">
            <h5 class="card-header">Profile Details</h5>
            <!-- Account -->
            <div class="card-body">
                <div class="d-flex align-items-start align-items-sm-center gap-4">
                    <img src="{{ $user->avatar_url }}" alt="user-avatar" class="d-block rounded" height="100" width="100" id="uploadedAvatar" />
                    <div class="button-wrapper">
                        <form action="{{ route('profile.update-avatar') }}" method="POST" enctype="multipart/form-data">
                            @csrf
                            @method('PUT')
                            <label for="upload" class="btn btn-primary me-2 mb-4" tabindex="0">
                                <span class="d-none d-sm-block">Upload new photo</span>
                                <i class="bx bx-upload d-block d-sm-none"></i>
                                <input type="file" id="upload" name="avatar" class="account-file-input" hidden accept="image/png, image/jpeg" onchange="this.form.submit()" />
                            </label>
                            <p class="text-muted mb-0">Allowed JPG, GIF or PNG. Max size of 2MB</p>
                        </form>
                    </div>
                </div>
            </div>
            <hr class="my-0" />
            <div class="card-body">
                <form id="formAccountSettings" method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="mb-3 col-md-6">
                            <label for="name" class="form-label">Full Name</label>
                            <input class="form-control @error('name') is-invalid @enderror" type="text" id="name" name="name" value="{{ old('name', $user->name) }}" autofocus />
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="email" class="form-label">E-mail</label>
                            <input class="form-control @error('email') is-invalid @enderror" type="text" id="email" name="email" value="{{ old('email', $user->email) }}" placeholder="john.doe@example.com" />
                            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3 col-md-6">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input class="form-control @error('phone_number') is-invalid @enderror" type="text" id="phone_number" name="phone_number" value="{{ old('phone_number', $user->phone_number) }}" placeholder="98XXXXXXXX" />
                            @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3 col-md-6 d-flex align-items-center">
                            <div class="form-check mt-3">
                                <input type="hidden" name="is_tourist" value="0">
                                <input class="form-check-input" type="checkbox" id="is_tourist" name="is_tourist" value="1" {{ old('is_tourist', $user->is_tourist) ? 'checked' : '' }} />
                                <label class="form-check-label" for="is_tourist"> I am not a nepali citizen </label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary me-2">Save changes</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <h5 class="card-header">Change Password</h5>
            <div class="card-body">
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="mb-3 col-md-6 form-password-toggle">
                            <label class="form-label" for="current_password">Current Password</label>
                            <div class="input-group input-group-merge">
                                <input class="form-control @error('current_password') is-invalid @enderror" type="password" name="current_password" id="current_password" placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                                @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="mb-3 col-md-6 form-password-toggle">
                            <label class="form-label" for="password">New Password</label>
                            <div class="input-group input-group-merge">
                                <input class="form-control @error('password') is-invalid @enderror" type="password"
                                    id="password" name="password"
                                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                                @error('password')
                                <span class="invalid-feedback">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3 col-md-6 form-password-toggle">
                            <label class="form-label" for="password_confirmation">Confirm New Password</label>
                            <div class="input-group input-group-merge">
                                <input class="form-control" type="password" name="password_confirmation"
                                    id="password_confirmation"
                                    placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;" />
                                <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary me-2">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
