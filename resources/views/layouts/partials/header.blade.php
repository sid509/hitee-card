<nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
    id="layout-navbar">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
            <i class="bx bx-menu bx-md"></i>
        </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
        <!-- Search -->
        <div class="navbar-nav align-items-center me-auto">
            <div class="nav-item d-flex align-items-center cursor-pointer" id="spotlight-trigger">
                <i class="bx bx-search bx-md"></i>
                <span class="text-muted ps-2 d-md-block d-none">{{ __('messages.search') }} (Cmd+K)</span>
            </div>
        </div>
        <!-- /Search -->

        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
            <!-- Language -->
            <li class="nav-item dropdown-language dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <i class='bx bx-globe bx-md'></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item {{ app()->getLocale() == 'en' ? 'active' : '' }}" href="{{ route('lang.switch', 'en') }}" data-language="en" data-text-direction="ltr">
                            <span>English</span>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item {{ app()->getLocale() == 'ne' ? 'active' : '' }}" href="{{ route('lang.switch', 'ne') }}" data-language="ne" data-text-direction="ltr">
                            <span>नेपाली</span>
                        </a>
                    </li>
                </ul>
            </li>
            <!-- /Language -->

            <!-- Style Switcher -->
            <li class="nav-item dropdown-style-switcher dropdown me-2 me-xl-0">
                <a class="nav-link dropdown-toggle hide-arrow" href="{{ route('theme.toggle') }}">
                    <i class='bx bx-md {{ $theme === 'dark' ? 'bx-moon' : 'bx-sun' }}'></i>
                </a>
            </li>
            <!-- /Style Switcher -->

            <!-- User -->
            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);" data-bs-toggle="dropdown">
                    <div class="avatar avatar-online">
                        <img src="{{ asset('assets/img/avatars/1.png') }}" alt class="w-px-40 h-auto rounded-circle" />
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="d-flex">
                                <div class="flex-shrink-0 me-3">
                                    <div class="avatar avatar-online">
                                        <img src="{{ asset('assets/img/avatars/1.png') }}" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-0">{{ auth()->user()->name ?? 'John Doe' }}</h6>
                                    <small class="text-body-secondary">{{ auth()->user()->roles->first()->name ?? 'User' }}</small>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider my-1"></div>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('profile.show') }}">
                            <i class="bx bx-user bx-md me-3"></i><span>{{ __('messages.profile') }}</span>
                        </a>
                    </li>
                    <li>
                        <div class="dropdown-divider my-1"></div>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="bx bx-power-off bx-md me-3"></i><span>{{ __('messages.logout') }}</span>
                            </button>
                        </form>
                    </li>
                </ul>
            </li>
            <!--/ User -->
        </ul>
    </div>
</nav>
