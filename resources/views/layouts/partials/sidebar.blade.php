<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo py-3">
        <a href="{{ route('dashboard') }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="{{ asset('assets/img/hitee/logo_white.png') }}" alt="Hitee Logo" height="34" class="logo-dark-version">
                <img src="{{ asset('assets/img/hitee/logo.png') }}" alt="Hitee Logo" height="34" class="logo-light-version">
            </span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>

    <ul class="menu-inner py-1">
        <!-- Dashboards -->
        <li class="menu-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <a href="{{ route('dashboard') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div class="text-truncate">{{ __('messages.dashboards') }}</div>
            </a>
        </li>

        @if(auth()->user()->hasRole('super-admin'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.administration') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <a href="{{ route('users.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div class="text-truncate">{{ __('messages.users') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
            <a href="{{ route('roles.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-shield-quarter"></i>
                <div class="text-truncate">{{ __('messages.roles') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
            <a href="{{ route('permissions.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-key"></i>
                <div class="text-truncate">{{ __('messages.permissions') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('stops.*') ? 'active' : '' }}">
            <a href="{{ route('stops.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-map-pin"></i>
                <div class="text-truncate">Stops</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('supports.*') ? 'active' : '' }}">
            <a href="{{ route('supports.index') }}" class="menu-link d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="menu-icon tf-icons bx bx-support"></i>
                    <div class="text-truncate">{{ __('messages.support_requests') }}</div>
                </div>
                @if(isset($openSupportCount) && $openSupportCount > 0)
                    <span class="badge badge-center rounded-pill bg-danger" style="width: 1.5rem; height: 1.5rem;">{{ $openSupportCount }}</span>
                @endif
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
            <a href="{{ route('activity-logs.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-history"></i>
                <div class="text-truncate">{{ __('messages.activity_logs') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('parking-attributes.*') ? 'active' : '' }}">
            <a href="{{ route('parking-attributes.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-list-check"></i>
                <div class="text-truncate">{{ __('messages.parking_attributes') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('banners.*') ? 'active' : '' }}">
            <a href="{{ route('banners.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-image"></i>
                <div class="text-truncate">Banners</div>
            </a>
        </li>

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.system') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <a href="{{ route('settings.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div class="text-truncate">{{ __('messages.settings') }}</div>
            </a>
        </li>
        @endif

        @if(auth()->user()->hasRole('super-admin', 'merchant', 'staff'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.journey_ledger') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('rides.index') ? 'active' : '' }}">
            <a href="{{ route('rides.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-git-commit"></i>
                <div class="text-truncate">{{ __('messages.rides_ledger') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('rides.tap-ledger') ? 'active' : '' }}">
            <a href="{{ route('rides.tap-ledger') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-fingerprint"></i>
                <div class="text-truncate">{{ __('messages.raw_taps') }}</div>
            </a>
        </li>
        @endif

        @if(auth()->user()->hasRole('merchant'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.staff_management') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('staff.*') ? 'active' : '' }}">
            <a href="{{ route('staff.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-group"></i>
                <div class="text-truncate">{{ __('messages.staff') }}</div>
            </a>
        </li>
        @endif

        @if(auth()->user()->hasRole('super-admin', 'merchant', 'staff'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.fleet_management') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('buses.*') ? 'active' : '' }}">
            <a href="{{ route('buses.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-bus"></i>
                <div class="text-truncate">{{ __('messages.buses') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('parkings.*') ? 'active' : '' }}">
            <a href="{{ route('parkings.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bxs-parking"></i>
                <div class="text-truncate">{{ __('messages.parkings') }}</div>
            </a>
        </li>
        @if(!auth()->user()->hasRole('staff'))
        <li class="menu-item {{ request()->routeIs('routes.*') ? 'active' : '' }}">
            <a href="{{ route('routes.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-map-alt"></i>
                <div class="text-truncate">{{ __('messages.routes') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('fares.*') ? 'active' : '' }}">
            <a href="{{ route('fares.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-money-withdraw"></i>
                <div class="text-truncate">{{ __('messages.fares') }}</div>
            </a>
        </li>
        @endif
        @endif

        @if(auth()->user()->hasRole('super-admin', 'merchant'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.earnings') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('merchant.income') ? 'active' : '' }}">
            <a href="{{ route('merchant.income') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-trending-up"></i>
                <div class="text-truncate">{{ __('messages.income') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('merchant.withdrawals') ? 'active' : '' }}">
            <a href="{{ route('merchant.withdrawals') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-export"></i>
                <div class="text-truncate">{{ __('messages.withdrawals') }}</div>
            </a>
        </li>
        @endif

        @if(auth()->user()->hasRole('super-admin', 'customers', 'staff'))
        <li class="menu-item {{ request()->routeIs('route-finder.*') ? 'active' : '' }}">
            <a href="{{ route('route-finder.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-map-pin"></i>
                <div class="text-truncate">{{ __('messages.route_finder') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('rides.my-rides') ? 'active' : '' }}">
            <a href="{{ route('rides.my-rides') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-trip"></i>
                <div class="text-truncate">{{ __('messages.my_rides') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('cards.*') ? 'active' : '' }}">
            <a href="{{ route('cards.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-credit-card"></i>
                <div class="text-truncate">{{ __('messages.cards') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
            <a href="{{ route('transactions.logs') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-list-ul"></i>
                <div class="text-truncate">{{ __('messages.transactions') }}</div>
            </a>
        </li>
        @endif

    </ul>
</aside>
