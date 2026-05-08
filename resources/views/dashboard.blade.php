@extends('layouts.app')

@section('title', 'Dashboard')

@push('page-css')
<style>
    #map { height: 400px; border-radius: 8px; border: 1px solid #dee2e6; }
    .nearby-badge { position: absolute; top: 10px; right: 10px; z-index: 1000; }
</style>
@endpush

@section('content')
<div class="row">
    <div class="col-lg-8 mb-6 order-0">
        <div class="card h-100">
            <div class="d-flex align-items-start row">
                <div class="col-sm-7">
                    <div class="card-body">
                        <h5 class="card-title text-primary mb-3">{{ __('messages.welcome') }} {{ auth()->user()->name }}! 🎉</h5>
                        <p class="mb-6">
                            Welcome back to Hitee Platform. Here is what is happening with your account today.
                        </p>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('profile.show') }}" class="btn btn-sm btn-primary">{{ __('messages.view') }} {{ __('messages.profile') }}</a>
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
                                <span class="small fw-medium">{{ __('messages.add') }} {{ __('messages.users') }}</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#quickAddBalanceModal" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-wallet fs-3 mb-2 text-success"></i>
                                <span class="small fw-medium">Load Funds</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('cards.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-credit-card fs-3 mb-2 text-warning"></i>
                                <span class="small fw-medium">Issue {{ __('messages.cards') }}</span>
                            </a>
                        </div>
                    @endif

                    @if(auth()->user()->hasRole('super-admin'))
                        <div class="col-md-3 col-6">
                            <a href="{{ route('buses.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-bus fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">{{ __('messages.add') }} {{ __('messages.buses') }}</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('parkings.create') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-car fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">{{ __('messages.add') }} {{ __('messages.parkings') }}</span>
                            </a>
                        </div>
                    @endif

                    @if(auth()->user()->hasRole('merchant'))
                        <div class="col-md-3 col-6">
                            <a href="{{ route('buses.index') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-bus fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">My {{ __('messages.buses') }}</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('parkings.index') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-car fs-3 mb-2 text-primary"></i>
                                <span class="small fw-medium">My {{ __('messages.parkings') }}</span>
                            </a>
                        </div>
                    @endif

                    @if(auth()->user()->hasRole('customers'))
                        <div class="col-md-3 col-6">
                            @if(auth()->user()->is_tourist)
                                <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#stripeTopupModal" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                    <i class="bx bxl-stripe fs-3 mb-2 text-primary"></i>
                                    <span class="small fw-medium">Topup Balance</span>
                                </a>
                            @else
                                <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#khaltiTopupModal" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                    <i class="bx bx-plus-circle fs-3 mb-2 text-primary"></i>
                                    <span class="small fw-medium">Topup Balance</span>
                                </a>
                            @endif
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('transactions.logs') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-list-ul fs-3 mb-2 text-info"></i>
                                <span class="small fw-medium">{{ __('messages.transactions') }} Logs</span>
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="{{ route('cards.index') }}" class="d-flex flex-column align-items-center text-center p-3 border rounded h-100 transition-all hover-light text-body">
                                <i class="bx bx-credit-card fs-3 mb-2 text-warning"></i>
                                <span class="small fw-medium">My {{ __('messages.cards') }}</span>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 col-md-4 order-1">
        <div class="row">
            <div class="col-12 mb-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div class="avatar flex-shrink-0">
                                <span class="avatar-initial rounded bg-white text-primary"><i class="bx bx-wallet"></i></span>
                            </div>
                            <h5 class="card-title text-white mb-0">{{ auth()->user()->hasRole('merchant') ? 'Available Income' : 'My Balance' }}</h5>
                        </div>
                        <h2 class="text-white mb-2">Rs. {{ number_format(auth()->user()->hasRole('merchant') ? auth()->user()->merchantBalance() : auth()->user()->balance(), 2) }}</h2>
                        <div class="d-flex gap-2">
                            @if(auth()->user()->hasRole('customers'))
                                @if(auth()->user()->is_tourist)
                                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#stripeTopupModal">Topup</button>
                                @else
                                    <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#khaltiTopupModal">Topup</button>
                                @endif
                                <a href="{{ route('transactions.logs') }}" class="btn btn-sm btn-outline-light">History</a>
                            @elseif(auth()->user()->hasRole('merchant'))
                                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#withdrawModal">Withdraw</button>
                                <a href="{{ route('merchant.income') }}" class="btn btn-sm btn-outline-light">{{ __('messages.earnings') }}</a>
                            @else
                                <a href="{{ route('transactions.logs') }}" class="btn btn-sm btn-outline-light">History</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @if(auth()->user()->hasRole('super-admin'))
            <div class="col-lg-6 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-2">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-user"></i></span>
                            </div>
                            <div class="card-info">
                                <h6 class="mb-0">{{ $userCount }}</h6>
                                <small class="text-muted">{{ __('messages.users') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-2">
                                <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-credit-card"></i></span>
                            </div>
                            <div class="card-info">
                                <h6 class="mb-0">{{ $cardCount }}</h6>
                                <small class="text-muted">{{ __('messages.cards') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-2">
                                <span class="avatar-initial rounded bg-label-success"><i class="bx bx-transfer-alt"></i></span>
                            </div>
                            <div class="card-info">
                                <h6 class="mb-0">{{ $transactionCount }}</h6>
                                <small class="text-muted">Txns</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-4">
                <a href="{{ route('supports.index') }}" class="card hover-light">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-2">
                                <span class="avatar-initial rounded bg-label-danger"><i class="bx bx-support"></i></span>
                            </div>
                            <div class="card-info">
                                <h6 class="mb-0">{{ $openSupportCount ?? 0 }}</h6>
                                <small class="text-muted">Open Tickets</small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            @endif

            @if(auth()->user()->hasRole('super-admin', 'merchant'))
            <div class="col-lg-6 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-2">
                                <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-bus"></i></span>
                            </div>
                            <div class="card-info">
                                <h6 class="mb-0">{{ $busCount }}</h6>
                                <small class="text-muted">{{ auth()->user()->hasRole('merchant') ? __('messages.buses') : 'Fleet' }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 col-md-12 col-6 mb-4">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar flex-shrink-0 me-2">
                                <span class="avatar-initial rounded bg-label-info"><i class="bx bxs-parking"></i></span>
                            </div>
                            <div class="card-info">
                                <h6 class="mb-0">{{ $parkingCount }}</h6>
                                <small class="text-muted">{{ __('messages.parkings') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="row">
    <!-- Map View for Admin and Merchant -->
    @if(auth()->user()->hasRole('super-admin', 'merchant'))
    <div class="col-12 mb-4" id="map-container">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Fleet & Asset Locations</h5>
                <button id="toggleMapSize" class="btn btn-sm btn-outline-primary"><i class="bx bx-fullscreen"></i> Toggle Fullscreen</button>
            </div>
            <div class="card-body">
                <div id="map"></div>
            </div>
        </div>
    </div>
    @endif

    <!-- Nearby for Customers -->
    @if(auth()->user()->hasRole('customers'))
    <div class="col-md-6 mb-4">
        <div class="card h-100 bg-label-primary">
            <div class="card-body d-flex flex-column justify-content-center align-items-center text-center">
                <div class="avatar avatar-lg mb-3">
                    <span class="avatar-initial rounded bg-primary"><i class="bx bx-map-pin fs-2"></i></span>
                </div>
                <h5>Smart {{ __('messages.route_finder') }}</h5>
                <p>Plan your journey, find direct buses, or get smart connecting route suggestions across the city.</p>
                <a href="{{ route('route-finder.index') }}" class="btn btn-primary mt-2">
                    <i class="bx bx-search-alt me-1"></i> Start Planning
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Nearby Assets (5km)</h5>
                <button id="refreshNearby" class="btn btn-sm btn-outline-primary"><i class="bx bx-refresh"></i> Refresh</button>
            </div>
            <div class="card-body">
                <div id="nearby-status" class="alert alert-info py-2 mb-3">
                    <i class="bx bx-loader-alt bx-spin me-2"></i> Detecting location...
                </div>
                <div class="row" id="nearby-assets-container">
                    <!-- Dynamic content -->
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 mb-4">
        <div class="card">
            <h5 class="card-header">Local Area Map</h5>
            <div class="card-body">
                <div id="map"></div>
            </div>
        </div>
    </div>
    @endif
</div>

@endsection

@push('page-js')
