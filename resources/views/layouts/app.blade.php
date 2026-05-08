<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" 
    class="layout-menu-fixed layout-compact {{ $theme === 'dark' ? 'dark-style' : ($theme === 'light' ? 'light-style' : '') }}" 
    dir="ltr"
    data-theme="{{ $theme === 'dark' ? 'theme-dark' : ($theme === 'light' ? 'theme-default' : '') }}" 
    data-assets-path="{{ asset('assets') }}/" 
    data-template="hitee-vertical-menu-template">

<head>
    <meta charset="utf-8" />
    <script>
        (function() {
            const theme = "{{ $theme }}";
            if (theme === 'system') {
                const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');
                const applyTheme = (isDark) => {
                    if (isDark) {
                        document.documentElement.classList.add('dark-style');
                        document.documentElement.classList.remove('light-style');
                        document.documentElement.setAttribute('data-theme', 'theme-dark');
                    } else {
                        document.documentElement.classList.add('light-style');
                        document.documentElement.classList.remove('dark-style');
                        document.documentElement.setAttribute('data-theme', 'theme-default');
                    }
                };

                applyTheme(darkQuery.matches);
                
                darkQuery.addEventListener('change', e => {
                    if ("{{ $theme }}" === 'system') {
                        applyTheme(e.matches);
                    }
                });
            }
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>@yield('title') | Hitee Platform</title>

    <meta name="description" content="Hitee Card Platform" />

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}" />
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @stack('page-css')
    <style>
        .cursor-pointer { cursor: pointer; }
        .hover-light:hover { background-color: rgba(67, 89, 113, 0.04); }
        .dark-style .hover-light:hover { background-color: rgba(255, 255, 255, 0.04); }
        .swal2-container { z-index: 9999 !important; }
        .border-dashed { border-style: dashed !important; }
        .dark-style .tooltip .tooltip-inner {
            background-color: #fff !important;
            color: #2b2c40 !important;
            box-shadow: 0 0.25rem 1rem rgba(0, 0, 0, 0.2);
        }
        .dark-style .tooltip .bs-tooltip-top .tooltip-arrow::before { border-top-color: #fff !important; }
        .dark-style .tooltip .bs-tooltip-bottom .tooltip-arrow::before { border-bottom-color: #fff !important; }
        .dark-style .tooltip .bs-tooltip-start .tooltip-arrow::before { border-left-color: #fff !important; }
        .dark-style .tooltip .bs-tooltip-end .tooltip-arrow::before { border-right-color: #fff !important; }
        
        /* Logo Switching */
        .logo-dark-version, .logo-light-version { display: none !important; }
        .dark-style .logo-dark-version { display: inline-block !important; }
        .light-style .logo-light-version { display: inline-block !important; }

        /* Consistent Sizing for Filters and Buttons */
        .select2-container--default .select2-selection--single {
            height: 38px !important;
            padding: 5px 12px;
            border: 1px solid #d9dee3;
            border-radius: 0.375rem;
        }
        .dark-style .select2-container--default .select2-selection--single {
            border-color: #444564;
            background-color: #232333;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px !important;
            padding-left: 0 !important;
            color: #697a8d;
        }
        .dark-style .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #a3a4cc;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }
        .form-select, .form-control {
            height: 38px !important;
        }
        .btn-filter-reset {
            height: 38px !important;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

<body>
    <!-- Page Loader -->
    <div id="page-loader" class="page-loader">
        <div class="loader-content">
            <div class="spinner-border text-primary" role="status" style="width: 2.5rem; height: 2.5rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    @if(app('impersonate')->isImpersonating())
    <div class="impersonate-banner bg-danger text-white text-center py-2">
        You are currently impersonating <strong>{{ auth()->user()->name }}</strong>.
        <a href="{{ route('impersonate.leave') }}" class="btn btn-sm btn-light ms-3">Stop Impersonating</a>
    </div>
    @endif

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            @include('layouts.partials.sidebar')
            <!-- / Menu -->

            <!-- Layout page -->
            <div class="layout-page">
                <!-- Navbar -->
                @include('layouts.partials.header')
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        @yield('content')
                    </div>
                    <!-- / Content -->

                    <!-- Footer -->
                    @include('layouts.partials.footer')
                    <!-- / Footer -->

                    <div class="content-backdrop fade"></div>
                </div>
                <!-- Content wrapper -->
            </div>
            <!-- / Layout page -->
        </div>

        <!-- Overlay -->
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>
    <!-- / Layout wrapper -->

    @stack('modals')

    <!-- Spotlight Search Modal -->
    <div class="modal fade" id="spotlightModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="spotlight-input-group">
                    <input type="text" id="spotlight-input" placeholder="{{ __('messages.type_to_search') }}" autocomplete="off">
                </div>
                <div class="spotlight-results" id="spotlight-results">
                    <!-- Results will be injected here -->
                    <div class="text-center py-5 text-muted">
                        <i class="bx bx-search-alt fs-1 mb-2"></i>
                        <p>{{ __('messages.search') }}...</p>
                    </div>
                </div>
                <div class="spotlight-footer">
                    <span><kbd>↑↓</kbd> to navigate</span>
                    <span><kbd>Enter</kbd> to select</span>
                    <span><kbd>Esc</kbd> to close</span>
                </div>
            </div>
        </div>
    </div>

    @include('layouts.partials.support-modal')
    @include('layouts.partials.financial-modals')

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <!-- Global Helpers -->
    <script type="module">
        window.addEventListener('load', function() {
            const loader = document.getElementById('page-loader');
            if (loader) {
                loader.classList.add('fade-out');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 400);
            }
        });

        window.showAlert = function(message, icon = 'success', title = 'Success') {
            Swal.fire({
                title: title,
                text: message,
                icon: icon,
                customClass: {
                    confirmButton: 'btn btn-primary'
                },
                buttonsStyling: false
            });
        };

        window.showToast = function(message, title = 'Info', type = 'info') {
            showAlert(message, type, title);
        };

        window.showConfirm = function(title, text, confirmButtonText = 'Yes, do it!') {
            return Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: confirmButtonText,
                customClass: {
                    confirmButton: 'btn btn-primary me-3',
                    cancelButton: 'btn btn-label-secondary'
                },
                buttonsStyling: false
            });
        };

        $(function() {
            // Display session messages
            @if(session('success'))
                showAlert("{{ session('success') }}", 'success', 'Success');
            @endif
            @if(session('error'))
                showAlert("{{ session('error') }}", 'error', 'Error');
            @endif
            @if(session('info'))
                showAlert("{{ session('info') }}", 'info', 'Info');
            @endif
            @if(session('warning'))
                showAlert("{{ session('warning') }}", 'warning', 'Warning');
            @endif

            const btnSend = $('#btnSendSupport');
            const supportForm = $('#quickSupportForm');

            // Spotlight Logic
            const modalEl = document.getElementById('spotlightModal');
            if (modalEl) {
                const spotlightModal = new bootstrap.Modal(modalEl);
                const input = $('#spotlight-input');
                const results = $('#spotlight-results');
                let debounceTimer;

                $('#spotlight-trigger').on('click', () => spotlightModal.show());

                $(document).on('keydown', function(e) {
                    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                        e.preventDefault();
                        spotlightModal.show();
                    }
                });

                modalEl.addEventListener('shown.bs.modal', () => input.focus());
                
                modalEl.addEventListener('hidden.bs.modal', () => {
                    input.val('');
                    results.html('<div class="text-center py-5 text-muted"><i class="bx bx-search-alt fs-1 mb-2"></i><p>Search for anything...</p></div>');
                });

                input.on('input', function() {
                    clearTimeout(debounceTimer);
                    const q = $(this).val();

                    if (q.length < 2) {
                        results.html('<div class="text-center py-5 text-muted"><i class="bx bx-search-alt fs-1 mb-2"></i><p>Search for anything...</p></div>');
                        return;
                    }

                    results.html('<div class="text-center py-5"><span class="spinner-border text-primary"></span></div>');

                    debounceTimer = setTimeout(() => {
                        $.get("{{ route('search.global') }}", { q: q }, function(data) {
                            if (Object.keys(data).length === 0) {
                                results.html('<div class="text-center py-5 text-muted"><i class="bx bx-confused fs-1 mb-2"></i><p>No results found for "' + q + '"</p></div>');
                                return;
                            }

                            let html = '';
                            for (const category in data) {
                                html += `<div class="category-header">${category}</div>`;
                                data[category].forEach(item => {
                                    html += `
                                        <a href="${item.url}" class="result-item">
                                            <div class="result-icon"><i class="bx ${item.icon}"></i></div>
                                            <div class="result-meta">
                                                <span class="result-title">${item.title}</span>
                                                <span class="result-subtitle">${item.subtitle}</span>
                                            </div>
                                            <i class="bx bx-chevron-right text-muted"></i>
                                        </a>
                                    `;
                                });
                            }
                            results.html(html);
                        });
                    }, 300);
                });
            }

            $(document).on('click', '.delete-btn', function(e) {
                e.preventDefault();
                const form = $(this).closest('form');

                showConfirm('Are you sure?', 'You won\'t be able to revert this!', 'Yes, delete it!')
                    .then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
            });

            $('.select2-ajax-merchant').each(function() {
                $(this).select2({
                    ajax: {
                        url: "{{ route('search.merchants') }}",
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term, page: params.page }),
                        processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                        cache: true
                    },
                    placeholder: 'Search Merchant...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : null
                });
            });

            $('.select2-users').each(function() {
                $(this).select2({
                    ajax: {
                        url: "{{ route('search.users') }}",
                        dataType: 'json',
                        delay: 250,
                        data: params => ({ q: params.term, page: params.page }),
                        processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                        cache: true
                    },
                    placeholder: 'Search Customer...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $(this).closest('.modal').length ? $(this).closest('.modal') : null
                });
            });

            supportForm.on('submit', function(e) {
                e.preventDefault();
                btnSend.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Sending...');
                $.ajax({
                    url: "{{ route('support.send') }}",
                    method: "POST",
                    data: $(this).serialize(),
                    success: function(response) {
                        supportForm[0].reset();
                        const modal = bootstrap.Modal.getInstance(document.getElementById('supportModal'));
                        if (modal) modal.hide();
                        setTimeout(() => {
                            showAlert(response.message || 'Support request sent successfully');
                        }, 500);
                    },
                    error: function(xhr) {
                        const msg = xhr.responseJSON?.message || 'Failed to send support request';
                        showAlert(msg, 'error');
                    },
                    complete: function() {
                        btnSend.prop('disabled', false).text('Send Message');
                    }
                });
            });
        });
    </script>
    @stack('page-js')
</body>
</html>
