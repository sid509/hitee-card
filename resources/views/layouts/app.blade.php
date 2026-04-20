<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" 
    class="layout-menu-fixed layout-compact {{ $theme === 'dark' ? 'dark-style' : 'light-style' }}" 
    dir="ltr"
    data-theme="{{ $theme === 'dark' ? 'theme-dark' : 'theme-default' }}" 
    data-assets-path="{{ asset('assets') }}/" 
    data-template="hitee-vertical-menu-template">

<head>
    <meta charset="utf-8" />
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
    </style>

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>

<body>
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

    <!-- Scripts -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    @stack('page-js')

    <!-- Global Helpers -->
    <script type="module">
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
            // Simple logic for Bootstrap Toast or just alert
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
            const btnSend = $('#btnSendSupport');
            const supportForm = $('#quickSupportForm');

            // Spotlight Logic
            const modalEl = document.getElementById('spotlightModal');
            if (modalEl) {
                const spotlightModal = new bootstrap.Modal(modalEl);
                const input = $('#spotlight-input');
                const results = $('#spotlight-results');
                let debounceTimer;

                // Trigger on click
                $('#spotlight-trigger').on('click', () => spotlightModal.show());

                // Trigger on Keyboard Shortcut (Cmd+K or Ctrl+K)
                $(document).on('keydown', function(e) {
                    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                        e.preventDefault();
                        spotlightModal.show();
                    }
                });

                // Focus input when modal opens
                modalEl.addEventListener('shown.bs.modal', () => input.focus());
                
                // Clear on hide
                modalEl.addEventListener('hidden.bs.modal', () => {
                    input.val('');
                    results.html('<div class="text-center py-5 text-muted"><i class="bx bx-search-alt fs-1 mb-2"></i><p>Search for anything...</p></div>');
                });

                // Handle typing
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

            // Global delete confirmation
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
                        // Show success message with delay
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
</body>

</html>
