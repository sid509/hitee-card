@extends('layouts.auth')

@section('title', 'Login')

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
        <h4 class="mb-1">Welcome to Hitee! 👋</h4>
        <p class="mb-6">Please sign-in to your account and start the adventure</p>

        <form id="formAuthentication" class="mb-6" action="{{ route('login') }}" method="POST">
            @csrf
            <div class="mb-6">
                <label for="phone_number" class="form-label">Phone Number</label>
                <input type="text" class="form-control @error('phone_number') is-invalid @enderror" id="phone_number" name="phone_number"
                    placeholder="Enter your phone number" value="{{ old('phone_number') }}" autofocus />
                @error('phone_number')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>
            <div class="mb-6 form-password-toggle">
                <div class="d-flex justify-content-between">
                    <label class="form-label" for="password">Password</label>
                    <a href="{{ route('password.request') }}">
                        <span>Forgot Password?</span>
                    </a>
                </div>
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
            <div class="mb-8">
                <div class="d-flex justify-content-between">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="remember-me" name="remember" {{
                            old('remember') ? 'checked' : '' }} />
                        <label class="form-check-label" for="remember-me"> Remember Me </label>
                    </div>
                </div>
            </div>
            <div class="mb-6">
                <button class="btn btn-primary d-grid w-100" type="submit">Login</button>
            </div>
        </form>

        <p class="text-center">
            <span>New on our platform?</span>
            <a href="{{ route('register') }}">
                <span>Create an account</span>
            </a>
        </p>

        <div class="divider my-6">
            <div class="divider-text">or</div>
        </div>

        <div class="d-grid gap-2">
            <a href="{{ route('social.redirect', 'facebook') }}" class="btn btn-facebook">
                <i class="tf-icons bx bxl-facebook me-2"></i>
                Login with Facebook
            </a>

            <div class="divider my-0">
                <div class="divider-text">or</div>
            </div>

            <a href="{{ route('social.redirect', 'google') }}" class="btn btn-google">
                <i class="tf-icons bx bxl-google me-2"></i>
                Login with Google
            </a>
        </div>
    </div>
</div>
@endsection
