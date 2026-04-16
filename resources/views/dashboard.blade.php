@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row">
    <div class="col-lg-8 mb-6 order-0">
        <div class="card h-100">
            <div class="d-flex align-items-start row">
                <div class="col-sm-7">
                    <div class="card-body">
                        <h5 class="card-title text-primary mb-3">{{ __('messages.welcome') }} {{ auth()->user()->name }}! 🎉</h5>
                        <p class="mb-6">
                            Welcome back to Hitee Platform. Here is what is happening with your fleet today.
                        </p>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('profile.show') }}" class="btn btn-sm btn-primary">View Profile</a>
                        </div>
                    </div>
                </div>
                <div class="col-sm-5 text-center text-sm-left">
                    <div class="card-body pb-0 px-0 px-md-6">
                        <img src="{{ asset('assets/img/illustrations/man-with-laptop.png') }}" height="175"
                            alt="View Badge User" />
                    </div>
                </div>
            </div>
            <div class="card-body border-top">
                <h6 class="text-muted mb-4">Quick Shortcuts</h6>
                <div class="row g-3">
                    @if(auth()->user()->hasRole('super-admin'))
                    <div class="col-md-3 col-6">
                        <a href="{{ route('users.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                            <i class="bx bx-user-plus fs-3 mb-2 text-primary"></i>
                            <span class="small fw-medium">Add User</span>
                        </a>
                    </div>
                    @endif
                    <div class="col-md-3 col-6">
                        <a href="{{ route('buses.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                            <i class="bx bx-bus fs-3 mb-2 text-primary"></i>
                            <span class="small fw-medium">Add Bus</span>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="{{ route('parkings.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                            <i class="bx bx-car fs-3 mb-2 text-primary"></i>
                            <span class="small fw-medium">Add Parking</span>
                        </a>
                    </div>
                    <div class="col-md-3 col-6">
                        <a href="{{ route('cards.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                            <i class="bx bx-credit-card fs-3 mb-2 text-primary"></i>
                            <span class="small fw-medium">Issue Card</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 order-1">
        <div class="row">
            <div class="col-lg-6 col-md-12 col-6 mb-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-user"></i></span>
                            </div>
                        </div>
                        <p class="mb-1">Total Users</p>
                        <h4 class="card-title mb-3">{{ $userCount }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-bus"></i></span>
                            </div>
                        </div>
                        <p class="mb-1">Total Buses</p>
                        <h4 class="card-title mb-3">{{ $busCount }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-car"></i></span>
                            </div>
                        </div>
                        <p class="mb-1">Parkings</p>
                        <h4 class="card-title mb-3">{{ $parkingCount }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-6">
                <div class="card">
                    <div class="card-body">
                        <div class="card-title d-flex align-items-start justify-content-between mb-4">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-credit-card"></i></span>
                            </div>
                        </div>
                        <p class="mb-1">Cards Issued</p>
                        <h4 class="card-title mb-3">{{ $cardCount }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
