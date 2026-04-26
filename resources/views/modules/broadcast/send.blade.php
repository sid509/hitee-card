@extends('layouts.app')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">{{ __('messages.broadcasts') }} /</span> {{ __('messages.send') }}
    </h4>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('messages.broadcast_selection') }}</h5>
            <a href="{{ route('broadcast.index') }}" class="btn btn-sm btn-secondary">{{ __('messages.back') }}</a>
        </div>
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('broadcast.send') }}" onsubmit="return confirm('Confirm broadcast?');">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="template_id">{{ __('messages.select_template') }}</label>
                        <select name="template_id" id="template_id" class="form-select" required onchange="toggleTarget()">
                            <option value="">-- {{ __('messages.choose_template') }} --</option>
                            @foreach($templates as $template)
                                <option value="{{ $template->id }}" data-type="{{ $template->type }}">
                                    {{ $template->name }} ({{ strtoupper($template->type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="target_type">{{ __('messages.target_audience') }}</label>
                        <select name="target_type" id="target_type" class="form-select" required onchange="toggleTarget()">
                            <option value="all">{{ __('messages.all_users') }}</option>
                            <option value="specific">{{ __('messages.specific_audience') }} (Testing/Direct)</option>
                        </select>
                    </div>
                </div>

                <div id="specific_target_fields" style="display: none;">
                    <div class="row">
                        <!-- Email Input -->
                        <div class="col-12 mb-3" id="field_email" style="display: none;">
                            <label class="form-label" for="specific_emails">{{ __('messages.emails') }} (Comma separated)</label>
                            <textarea name="specific_emails" id="specific_emails" class="form-control" rows="5" placeholder="user1@example.com, user2@example.com"></textarea>
                        </div>
                        
                        <!-- FCM Input -->
                        <div class="col-12 mb-3" id="field_fcm" style="display: none;">
                            <label class="form-label" for="specific_fcm_tokens">{{ __('messages.tokens') }} (Comma separated)</label>
                            <textarea name="specific_fcm_tokens" id="specific_fcm_tokens" class="form-control" rows="5" placeholder="token1, token2"></textarea>
                        </div>

                        <!-- SMS Input -->
                        <div class="col-12 mb-3" id="field_sms" style="display: none;">
                            <label class="form-label" for="specific_phones">{{ __('messages.phone_numbers') }} (Comma separated)</label>
                            <textarea name="specific_phones" id="specific_phones" class="form-control" rows="5" placeholder="98XXXXXXXX, 97XXXXXXXX"></textarea>
                        </div>
                    </div>
                </div>

                <div class="alert alert-warning mt-3">
                    <i class="bx bx-error-circle me-1"></i> <span id="broadcast_warning_text">{{ __('messages.broadcast_warning') }}</span>
                </div>

                <button type="submit" class="btn btn-success">
                    <i class="bx bx-send me-1"></i> {{ __('messages.send') }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script>
    window.toggleTarget = function() {
        var targetType = document.getElementById('target_type').value;
        var templateSelect = document.getElementById('template_id');
        var selectedOption = templateSelect.options[templateSelect.selectedIndex];
        var templateType = selectedOption ? selectedOption.getAttribute('data-type') : null;
        
        var fieldsContainer = document.getElementById('specific_target_fields');
        var fieldEmail = document.getElementById('field_email');
        var fieldFcm = document.getElementById('field_fcm');
        var fieldSms = document.getElementById('field_sms');
        var warningText = document.getElementById('broadcast_warning_text');

        // Reset display
        fieldEmail.style.display = 'none';
        fieldFcm.style.display = 'none';
        fieldSms.style.display = 'none';

        if (targetType === 'specific') {
            fieldsContainer.style.display = 'block';
            
            // Show only relevant field based on template type
            if (templateType === 'email') {
                fieldEmail.style.display = 'block';
                warningText.innerText = "Emails will be sent to the specific addresses provided below.";
            } else if (templateType === 'fcm') {
                fieldFcm.style.display = 'block';
                warningText.innerText = "FCM Push Notifications will be sent to the specific tokens provided below.";
            } else if (templateType === 'sms') {
                fieldSms.style.display = 'block';
                warningText.innerText = "SMS messages will be sent to the specific phone numbers provided below.";
            } else {
                warningText.innerText = "Please select a template to see the relevant audience fields.";
            }
        } else {
            fieldsContainer.style.display = 'none';
            warningText.innerText = "This will broadcast to ALL registered users in the database using the selected template.";
        }
    }

    // Initial check
    setTimeout(function() {
        toggleTarget();
    }, 500);
</script>
@endpush
