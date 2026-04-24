@extends('layouts.auth')

@section('title', 'Verify Email')

@section('content')
<div class="card">
    <div class="card-body">
        <!-- Logo -->
        <div class="app-brand justify-content-center mb-4">
            <a href="{{ url('/') }}" class="app-brand-link gap-2">
                <span class="app-brand-logo demo">
                    <img src="{{ asset('assets/img/hitee/logo.png') }}" alt="Hitee Logo" height="34">
                </span>
            </a>
        </div>
        <!-- /Logo -->
        <h4 class="mb-2">Verify your email ✉️</h4>
        <p class="text-start">
            Account activation link sent to your email address. Please follow the link inside to continue.
        </p>

        @if (session('message'))
            <div class="alert alert-success" role="alert">
                A new verification link has been sent to your email address.
            </div>
        @endif

        <form class="d-inline" method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="btn btn-primary w-100 my-3">
                Resend Verification Email
            </button>
        </form>
        
        <p class="text-center">
            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                Log Out
            </a>
        </p>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
            @csrf
        </form>
    </div>
</div>
@endsection
