@if(auth()->check())
<div class="modal fade" id="supportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Need Help?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickSupportForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.subject') }}</label>
                        <input type="text" name="subject" class="form-control" placeholder="{{ __('messages.subject') }}?" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">{{ __('messages.message') }}</label>
                        <textarea name="message" class="form-control-text" rows="4" placeholder="{{ __('messages.message') }}..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('messages.close') }}</button>
                    <button type="submit" id="btnSendSupport" class="btn btn-primary">{{ __('messages.send') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
