<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <div class="app-brand demo py-3">
        <a href="{{ route('dashboard') }}" class="app-brand-link">
            <span class="app-brand-logo demo">
                <img src="{{ asset('assets/img/hitee/' . ($theme === 'dark' ? 'logo_white.png' : 'logo.png')) }}" alt="Hitee Logo" height="34">
            </span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

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
            <span class="menu-header-text">Administration</span>
        </li>
        <li class="menu-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
            <a href="{{ route('users.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div class="text-truncate">Users</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
            <a href="{{ route('roles.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-shield-quarter"></i>
                <div class="text-truncate">Roles</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
            <a href="{{ route('permissions.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-key"></i>
                <div class="text-truncate">Permissions</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('supports.*') ? 'active' : '' }}">
            <a href="{{ route('supports.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-support"></i>
                <div class="text-truncate">Support Requests</div>
            </a>
        </li>
        @endif

        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Management</span>
        </li>
        
        @if(auth()->user()->hasRole('super-admin', 'merchant'))
        <li class="menu-item {{ request()->routeIs('buses.*') ? 'active' : '' }}">
            <a href="{{ route('buses.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-bus"></i>
                <div class="text-truncate">Buses</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('parkings.*') ? 'active' : '' }}">
            <a href="{{ route('parkings.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bxs-parking"></i>
                <div class="text-truncate">Parkings</div>
            </a>
        </li>
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Earnings</span>
        </li>
        <li class="menu-item {{ request()->routeIs('merchant.income') ? 'active' : '' }}">
            <a href="{{ route('merchant.income') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-trending-up"></i>
                <div class="text-truncate">Income</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('merchant.withdrawals') ? 'active' : '' }}">
            <a href="{{ route('merchant.withdrawals') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-export"></i>
                <div class="text-truncate">Withdrawals</div>
            </a>
        </li>
        @endif

        @if(auth()->user()->hasRole('super-admin', 'customers'))
        <li class="menu-item {{ request()->routeIs('cards.*') ? 'active' : '' }}">
            <a href="{{ route('cards.index') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-credit-card"></i>
                <div class="text-truncate">Cards</div>
            </a>
        </li>
        <li class="menu-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
            <a href="{{ route('transactions.logs') }}" class="menu-link">
                <i class="menu-icon tf-icons bx bx-list-ul"></i>
                <div class="text-truncate">Transactions</div>
            </a>
        </li>
        @endif

    </ul>
</aside>
