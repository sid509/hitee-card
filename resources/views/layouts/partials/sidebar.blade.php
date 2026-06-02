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

        <!-- 1. Core Operations -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Operations</span>
        </li>
        
        <li class="menu-item {{ request()->routeIs('rides.my-rides', 'rides.index') ? 'active' : '' }}">
            <a href="{{ auth()->user()->hasRole('customers') ? route('rides.my-rides') : route('rides.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-trip"></i>
                <div class="text-truncate">{{ __('messages.my_rides') }}</div>
            </a>
        </li>

        <li class="menu-item {{ request()->routeIs('route-finder.*') ? 'active' : '' }}">
            <a href="{{ route('route-finder.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-navigation"></i>
                <div class="text-truncate">{{ __('messages.route_finder') }}</div>
            </a>
        </li>

        <li class="menu-item {{ request()->routeIs('transactions.logs') ? 'active' : '' }}">
            <a href="{{ route('transactions.logs') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-list-ul"></i>
                <div class="text-truncate">Transaction Logs</div>
            </a>
        </li>

        @if(auth()->user()->hasRole('super-admin', 'merchant'))
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

        @if(auth()->user()->hasRole('customers'))
        <li class="menu-item">
            <a href="javascript:void(0);" class="menu-link" data-bs-toggle="modal" data-bs-target="{{ auth()->user()->is_tourist ? '#stripeTopupModal' : '#khaltiTopupModal' }}">
                <i class="menu-icon tf-icons bx bx-plus-circle"></i>
                <div class="text-truncate">Add Balance</div>
            </a>
        </li>
        @endif

        @if(auth()->user()->hasRole('super-admin', 'merchant', 'staff'))
        <!-- 2. Asset Management -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Asset Management</span>
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
        <li class="menu-item {{ request()->routeIs('service-partners.*') ? 'active' : '' }}">
            <a href="{{ route('service-partners.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-store"></i>
                <div class="text-truncate">Service Partners</div>
            </a>
        </li>
        @endif

        @if(auth()->user()->hasRole('super-admin', 'merchant'))
        <li class="menu-item {{ request()->routeIs('routes.*', 'fares.*', 'stops.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-map-alt"></i>
                <div class="text-truncate">Transport Network</div>
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

        @if(auth()->user()->hasRole('merchant'))
        <li class="menu-item {{ request()->routeIs('staff.*') ? 'active' : '' }}">
            <a href="{{ route('staff.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-group"></i>
                <div class="text-truncate">{{ __('messages.staff') }}</div>
            </a>
        </li>
        @endif

        <!-- 3. Cards & Identity -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Cards & Identity</span>
        </li>
        <li class="menu-item {{ request()->routeIs('cards.index') ? 'active' : '' }}">
            <a href="{{ route('cards.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-credit-card"></i>
                <div class="text-truncate">@if(auth()->user()->hasRole('customers')) My Cards @else All Cards @endif</div>
            </a>
        </li>
        @if(auth()->user()->hasRole('super-admin'))
        <li class="menu-item {{ request()->routeIs('subscription-models.*') ? 'active' : '' }}">
            <a href="{{ route('subscription-models.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-category"></i>
                <div class="text-truncate">Subscription Models</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('card-applications.*') ? 'active' : '' }}">
            <a href="{{ route('card-applications.index') }}" class="menu-link d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="menu-icon tf-icons bx bx-envelope"></i>
                    <div class="text-truncate">Card Requests</div>
                </div>
                @if(isset($pendingCardApplicationsCount) && $pendingCardApplicationsCount > 0)
                    <span class="badge badge-center rounded-pill bg-danger" style="width: 1.5rem; height: 1.5rem;">{{ $pendingCardApplicationsCount }}</span>
                @endif
            </a>
        </li>
        @endif

        <!-- 4. Engagement & Support -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Engagement & Support</span>
        </li>
        @if(auth()->user()->hasRole('super-admin'))
        <li class="menu-item {{ request()->routeIs('broadcast.*') ? 'active' : '' }}">
            <a href="{{ route('broadcast.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-broadcast"></i>
                <div class="text-truncate">{{ __('messages.broadcasts') }}</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('banners.*') ? 'active' : '' }}">
            <a href="{{ route('banners.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-image"></i>
                <div class="text-truncate">{{ __('messages.banners') }}</div>
            </a>
        </li>
        @endif

        <li class="menu-item {{ request()->routeIs('supports.*') ? 'active' : '' }}">
            <a href="{{ route('supports.index') }}" class="menu-link d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center">
                    <i class="menu-icon tf-icons bx bx-support"></i>
                    <div class="text-truncate">Help & Support</div>
                </div>
                @if(isset($openSupportCount) && $openSupportCount > 0)
                    <span class="badge badge-center rounded-pill bg-danger" style="width: 1.5rem; height: 1.5rem;">{{ $openSupportCount }}</span>
                @endif
            </a>
        </li>

        <!-- 5. Administration -->
        @if(auth()->user()->hasRole('super-admin'))
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administration</span>
        </li>
        <li class="menu-item {{ request()->routeIs('users.*', 'roles.*', 'permissions.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-user-check"></i>
                <div class="text-truncate">Users & Access</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <a href="{{ route('users.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.users') }}</div>
                    </a>
                </li>
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

        <li class="menu-item {{ request()->routeIs('audit.*', 'activity-logs.*', 'parking-attributes.*', 'settings.*', 'logs.*') ? 'active open' : '' }}">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div class="text-truncate">System Tools</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item {{ request()->routeIs('audit.*') ? 'active' : '' }}">
                    <a href="{{ route('audit.index') }}" class="menu-link">
                        <div class="text-truncate">System Audit</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('activity-logs.*') ? 'active' : '' }}">
                    <a href="{{ route('activity-logs.index') }}" class="menu-link">
                        <div class="text-truncate">Activity Logs</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('parking-attributes.*') ? 'active' : '' }}">
                    <a href="{{ route('parking-attributes.index') }}" class="menu-link">
                        <div class="text-truncate">Parking Attributes</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <a href="{{ route('settings.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.settings') }}</div>
                    </a>
                </li>
                <li class="menu-item {{ request()->routeIs('logs.*') ? 'active' : '' }}">
                    <a href="{{ route('logs.index') }}" class="menu-link">
                        <div class="text-truncate">{{ __('messages.server_logs') }}</div>
                    </a>
                </li>
            </ul>
        </li>
        @endif
    </ul>
</aside>

