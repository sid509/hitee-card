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
        <li class="menu-item {{ request()->routeIs('audit.*') ? 'active' : '' }}">
            <a href="{{ route('audit.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-analyse"></i>
                <div class="text-truncate">{{ __('messages.system_audit') }}</div>
            </a>
        </li>
        @endif

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.operations') }}</span>
        </li>
        @if(auth()->user()->hasRole('super-admin', 'merchant', 'staff', 'customers'))
        <li class="menu-item {{ request()->routeIs('rides.index', 'rides.tap-ledger') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-git-commit"></i>
                <div class="text-truncate">{{ __('messages.journeys') }}</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('rides.index') ? 'active' : '' }}">
                    <a href="{{ route('rides.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.rides_ledger') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('rides.tap-ledger') ? 'active' : '' }}">
                    <a href="{{ route('rides.tap-ledger') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.raw_taps') }}</div>
                    </a>
                </li>
            </ul>
        </li>
        @endif

        @if(auth()->user()->hasRole('merchant'))
        <li class="menu-item {{ request()->routeIs('staff.*') ? 'active' : '' }}">
            <a href="{{ route('staff.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-group"></i>
                <div class="text-truncate">{{ __('messages.staff') }}</div>
            </a>
        </li>
        @endif

        <!-- Fleet & Infrastructure -->
        @if(auth()->user()->hasRole('super-admin', 'merchant', 'staff'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.fleet_infrastructure') }}</span>
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
        <li class="menu-item {{ request()->routeIs('routes.*', 'fares.*', 'stops.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-map-alt"></i>
                <div class="text-truncate">{{ __('messages.network') }}</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('routes.*') ? 'active' : '' }}">
                    <a href="{{ route('routes.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.routes') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('fares.*') ? 'active' : '' }}">
                    <a href="{{ route('fares.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.fares') }}</div>
                    </a>
                </li>
                @if(auth()->user()->hasRole('super-admin'))
                <li class="menu-item {{ request()->routeIs('stops.*') ? 'active' : '' }}">
                    <a href="{{ route('stops.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.stops') }}</div>
                    </a>
                </li>
                @endif
            </ul>
        </li>
        @endif
        @endif

        <!-- Administration Group -->
        @if(auth()->user()->hasRole('super-admin'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.administration') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('broadcast.*') ? 'active' : '' }}">
            <a href="{{ route('broadcast.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-broadcast"></i>
                <div class="text-truncate">{{ __('messages.broadcasts') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('users.*', 'roles.*', 'permissions.*', 'cards.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div class="text-truncate">{{ __('messages.users_access') }}</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <a href="{{ route('users.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.users') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('cards.*') ? 'active' : '' }}">
                    <a href="{{ route('cards.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.cards') }}</div>
                    </a>
                </li>
                @if(auth()->user()->hasRole('super-admin'))
                <li class="menu-item {{ request()->routeIs('card-applications.*') ? 'active' : '' }}">
                    <a href="{{ route('card-applications.index') }}" class="menu-link d-flex justify-content-between align-items-center">
                        <div class="text-truncate">Card Requests</div>
                        @if(isset($pendingCardApplicationsCount) && $pendingCardApplicationsCount > 0)
                            <span class="badge badge-center rounded-pill bg-danger" style="width: 1.5rem; height: 1.5rem;">{{ $pendingCardApplicationsCount }}</span>
                        @endif
                    </a>
                </li>
                @endif
                <li class="menu-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                    <a href="{{ route('roles.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.roles') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                    <a href="{{ route('permissions.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.permissions') }}</div>
                    </a>
                </li>
            </ul>
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
        <li class="menu-item {{ request()->routeIs('banners.*') ? 'active' : '' }}">
            <a href="{{ route('banners.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-image"></i>
                <div class="text-truncate">{{ __('messages.banners') }}</div>
            </a>
        </li>

        <!-- System Settings -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.system') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('parking-attributes.*') ? 'active' : '' }}">
            <a href="{{ route('parking-attributes.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-list-check"></i>
                <div class="text-truncate">{{ __('messages.parking_attributes') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <a href="{{ route('settings.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div class="text-truncate">{{ __('messages.settings') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('logs.*') ? 'active' : '' }}">
            <a href="{{ route('logs.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-terminal"></i>
                <div class="text-truncate">{{ __('messages.server_logs') }}</div>
            </a>
        </li>
        @endif

        <!-- Earning & Finance -->
        @if(auth()->user()->hasRole('super-admin', 'merchant'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">{{ __('messages.financials') }}</span>
        </li>
        <li class="menu-item {{ request()->routeIs('merchant.income', 'merchant.withdrawals') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-trending-up"></i>
                <div class="text-truncate">{{ __('messages.earnings') }}</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('merchant.income') ? 'active' : '' }}">
                    <a href="{{ route('merchant.income') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.income') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('merchant.withdrawals') ? 'active' : '' }}">
                    <a href="{{ route('merchant.withdrawals') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.withdrawals') }}</div>
                    </a>
                </li>
            </ul>
        </li>
        @endif
    </ul>
</aside>
