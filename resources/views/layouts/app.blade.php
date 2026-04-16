<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" 
    class="layout-menu-fixed layout-compact {{ $theme === 'dark' ? 'dark-style' : 'light-style' }}" 
    dir="ltr"
    data-theme="{{ $theme === 'dark' ? 'theme-dark' : 'theme-default' }}" 
    data-assets-path="{{ asset('assets') }}/" 
    data-template="hitee-vertical-menu-template">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>@yield('title') | Hitee</title>

    <meta name="description" content="Hitee Solutions & Tap Tap Card" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}" />

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    @stack('page-css')
    <style>
        /* Backdrop blur for modals */
        body.modal-open .layout-wrapper {
            filter: blur(5px);
            transition: filter 0.3s ease;
        }
        .modal-backdrop.show {
            opacity: 0.1;
        }
        /* Ensure SweetAlert2 is always on top */
        .swal2-container {
            z-index: 99999 !important;
        }
        /* Solid Alerts */
        .alert-solid-success {
            background-color: #71dd37 !important;
            border-color: #71dd37 !important;
            color: #fff !important;
        }
        .alert-solid-danger {
            background-color: #ff3e1d !important;
            border-color: #ff3e1d !important;
            color: #fff !important;
        }
        .alert-solid-success .btn-close, .alert-solid-danger .btn-close {
            filter: brightness(0) invert(1);
        }
        .alert-solid-success .alert-icon i, .alert-solid-danger .alert-icon i {
            color: #fff !important;
        }
    </style>
</head>

<body>
    @if(auth()->user()->isImpersonated())
    <div class="alert alert-warning alert-dismissible mb-0 text-center rounded-0" role="alert">
        You are currently impersonating <strong>{{ auth()->user()->name }}</strong>.
        <a href="{{ route('impersonate.leave') }}" class="alert-link ms-2">Stop Impersonating</a>
    </div>
    @endif

    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            <!-- Menu -->
            @include('layouts.partials.sidebar')
            <!-- / Menu -->

            <!-- Layout container -->
            <div class="layout-page">
                <!-- Navbar -->
                @include('layouts.partials.header')
                <!-- / Navbar -->

                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <!-- Alert Container -->
                        <div id="alert-container"></div>

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

    @stack('page-js')

    <!-- Support Modal -->
    <div class="modal fade" id="supportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Support Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="supportForm">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">From</label>
                            <input type="text" class="form-control" value="{{ auth()->user()->name }} ({{ auth()->user()->email }})" disabled readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Message</label>
                            <textarea name="message" class="form-control" rows="4" placeholder="How can we help you?" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="btnSendSupport">Send Message</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="module">
        $(function() {
            // Global Alert Helper (Using solid success and danger classes)
            window.showToast = function(message, title = '', type = 'success') {
                const isError = type === 'error' || type === 'danger';
                const alertClass = isError ? 'alert-solid-danger' : 'alert-solid-success';
                const icon = isError ? 'bx-error-circle' : 'bx-check-circle';
                
                const alertHtml = `
                    <div class="alert ${alertClass} d-flex align-items-center flex-wrap gap-1 alert-dismissible fade show" role="alert">
                        <span class="alert-icon rounded-circle">
                            <i class="bx ${icon} icon-sm"></i>
                        </span>
                        <div class="ms-1">
                            ${title ? '<strong>' + title + ': </strong>' : ''}
                            ${message}
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                
                const $alert = $(alertHtml);
                $('#alert-container').html($alert);
                
                // Auto-dismiss after 8 seconds
                setTimeout(() => {
                    $alert.fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 8000);

                // Smooth scroll to top
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            // Global Swal Helper
            window.showAlert = function(message, type = 'success', title = '') {
                Swal.fire({
                    title: title || (type.charAt(0).toUpperCase() + type.slice(1)),
                    text: message,
                    icon: type,
                    confirmButtonText: 'OK',
                    customClass: {
                        confirmButton: 'btn btn-primary'
                    },
                    buttonsStyling: false
                });
            }

            // Global Confirmation Helper
            window.showConfirm = function(title, text, confirmBtnText = 'Yes, do it!', type = 'warning') {
                return Swal.fire({
                    title: title,
                    text: text,
                    icon: type,
                    showCancelButton: true,
                    confirmButtonText: confirmBtnText,
                    cancelButtonText: 'Cancel',
                    customClass: {
                        confirmButton: 'btn btn-primary me-3',
                        cancelButton: 'btn btn-label-secondary'
                    },
                    buttonsStyling: false
                });
            }

            const supportForm = $('#supportForm');
            const btnSend = $('#btnSendSupport');

            // Session Flash Messages
            @if(session('success'))
                showToast("{{ session('success') }}", 'Success', 'success');
            @endif

            @if(session('error'))
                showAlert("{{ session('error') }}", 'error');
            @endif

            @if(session('status'))
                showToast("{{ session('status') }}", 'Status', 'info');
            @endif

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
                        // Close modal safely
                        try {
                            const modalEl = document.getElementById('supportModal');
                            // Method 1: BS5 API
                            const modalInstance = window.bootstrap.Modal.getInstance(modalEl);
                            if (modalInstance) {
                                modalInstance.hide();
                            } else {
                                // Method 2: Click close button as fallback
                                $(modalEl).find('[data-bs-dismiss="modal"]').click();
                            }
                        } catch (err) {
                            console.error('Modal close error:', err);
                            // Method 3: Manual jQuery hide if all else fails
                            $('#supportModal').modal('hide');
                        }
                        
                        // Reset form
                        supportForm[0].reset();
                        
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
