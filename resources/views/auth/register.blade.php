@extends('layouts.auth')

@section('title', 'Register')

@section('content')
<div class="card px-sm-6 px-0">
    <div class="card-body">
        <!-- Logo -->
        <div class="app-brand justify-content-center mb-6">
            <a href="{{ url('/') }}" class="app-brand-link gap-2">
                <span class="app-brand-logo demo">
                    <img src="{{ asset('assets/img/hitee/logo_big_white.png') }}" alt="Hitee Logo" height="60" class="logo-dark-version">
                    <img src="{{ asset('assets/img/hitee/logo_big.png') }}" alt="Hitee Logo" height="60" class="logo-light-version">
                </span>
            </a>
        </div>
        <!-- /Logo -->
        <h4 class="mb-1">Adventure starts here 🚀</h4>
        <p class="mb-6">Join Hitee today and start managing your cards!</p>

        <form id="formAuthentication" class="mb-6" action="{{ route('register') }}" method="POST">
            @csrf
            <div class="mb-6">
                <label for="name" class="form-label">Full Name</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="name" name="name"
                    placeholder="Enter your name" value="{{ old('name') }}" autofocus />
                @error('name')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>
            <div class="mb-6">
                <label for="email" class="form-label">Email</label>
                <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                    placeholder="Enter your email" value="{{ old('email') }}" />
                @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>
            <div class="mb-6 form-password-toggle">
                <label class="form-label" for="password">Password</label>
                <div class="input-group input-group-merge">
                    <input type="password" id="password" class="form-control @error('password') is-invalid @enderror"
                        name="password"
                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                        aria-describedby="password" />
                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                    @error('password')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                    @enderror
                </div>
            </div>
            <div class="mb-6 form-password-toggle">
                <label class="form-label" for="password_confirmation">Confirm Password</label>
                <div class="input-group input-group-merge">
                    <input type="password" id="password_confirmation" class="form-control"
                        name="password_confirmation"
                        placeholder="&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;&#xb7;"
                        aria-describedby="password" />
                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                </div>
            </div>

            <div class="mb-6">
                <button class="btn btn-primary d-grid w-100" type="submit">Sign up</button>
            </div>
        </form>

        <p class="text-center">
            <span>Already have an account?</span>
            <a href="{{ route('login') }}">
                <span>Sign in instead</span>
            </a>
        </p>

        <div class="divider my-6">
            <div class="divider-text">or</div>
        </div>

        <div class="d-grid gap-2">
            <a href="{{ route('social.redirect', 'facebook') }}" class="btn btn-facebook">
                <i class="tf-icons bx bxl-facebook me-2"></i>
                Register with Facebook
            </a>

            <div class="divider my-0">
                <div class="divider-text">or</div>
            </div>

            <a href="{{ route('social.redirect', 'google') }}" class="btn btn-google">
                <i class="tf-icons bx bxl-google me-2"></i>
                Register with Google
            </a>
        </div>
    </div>
</div>
@endsection
