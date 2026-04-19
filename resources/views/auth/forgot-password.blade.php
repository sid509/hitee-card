@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
<div class="card px-sm-6 px-0">
    <div class="card-body">
        <!-- Logo -->
        <div class="app-brand justify-content-center mb-6">
            <a href="{{ url('/') }}" class="app-brand-link gap-2">
                <span class="app-brand-logo demo">
                    <img src="{{ asset('assets/img/hitee/' . ($theme === 'dark' ? 'logo_big_white.png' : 'logo_big.png')) }}" alt="Hitee Logo" height="60">
                </span>
            </a>
        </div>
        <!-- /Logo -->
        <h4 class="mb-1">Forgot Password? 🔒</h4>
        <p class="mb-6">Enter your email and we'll send you instructions to reset your password</p>

        @if (session('status'))
        <div class="alert alert-success" role="alert">
            {{ session('status') }}
        </div>
        @endif

        <form id="formAuthentication" class="mb-6" action="{{ route('password.email') }}" method="POST">
            @csrf
            <div class="mb-6">
                <label for="email" class="form-label">Email</label>
                <input type="text" class="form-control @error('email') is-invalid @enderror" id="email" name="email"
                    placeholder="Enter your email" value="{{ old('email') }}" autofocus />
                @error('email')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>
            <button class="btn btn-primary d-grid w-100" type="submit">Send Reset Link</button>
        </form>

        <div class="text-center">
            <a href="{{ route('login') }}" class="d-flex align-items-center justify-content-center">
                <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                Back to login
            </a>
        </div>
    </div>
</div>
@endsection
