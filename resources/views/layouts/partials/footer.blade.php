<footer class="content-footer footer bg-footer-theme">
    <div class="container-xxl">
        <div class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
            <div class="mb-2 mb-md-0">
                ©
                <script>
                    document.write(new Date().getFullYear());
                </script>
                , Hitee Solutions. All rights reserved.
            </div>
            <div class="d-none d-lg-inline-block">
                @if(auth()->check() && auth()->user()->hasRole('super-admin'))
                    <a href="{{ url('docs/api') }}" class="footer-link me-4" target="_blank">API Docs</a>
                @endif
                <a href="javascript:void(0)" class="footer-link" data-bs-toggle="modal" data-bs-target="#supportModal">Tech Support</a>
            </div>
        </div>
    </div>
</footer>
