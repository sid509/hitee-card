@extends('layouts.app')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">{{ __('messages.broadcasts') }} /</span> {{ __('messages.add') }}
    </h4>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">{{ __('messages.notification_templates') }}</h5>
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

            <form method="POST" action="{{ route('broadcast.store') }}" id="templateForm">
                @csrf
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="type">{{ __('messages.type') }}</label>
                        <select name="type" id="type" class="form-select" required onchange="toggleEditor()">
                            <option value="fcm">FCM Push Notification</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="name">{{ __('messages.template_name') }}</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. Welcome Message">
                    </div>
                </div>

                <div class="row" id="subjectFields">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="subject_en">{{ __('messages.subject') }} (English)</label>
                        <input type="text" class="form-control" id="subject_en" name="subject_en" placeholder="{{ __('messages.subject') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="subject_ne">{{ __('messages.subject') }} (Nepali)</label>
                        <input type="text" class="form-control" id="subject_ne" name="subject_ne" placeholder="{{ __('messages.subject') }}">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="body_en">{{ __('messages.message') }} (English)</label>
                        <textarea id="body_en" class="form-control" name="body_en" rows="5" required placeholder="Supports variable {name}"></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="body_ne">{{ __('messages.message') }} (Nepali)</label>
                        <textarea id="body_ne" class="form-control" name="body_ne" rows="5" required placeholder="Supports variable {name}"></textarea>
                    </div>
                </div>

                <p class="text-muted"><small>Note: You can use dynamic variables like <code>{name}</code> in both subject and body.</small></p>

                <button type="submit" class="btn btn-primary">{{ __('messages.save') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script src="https://cdn.ckeditor.com/4.25.1-lts/standard/ckeditor.js"></script>
<script>
    var enEditor, neEditor;
    var isEditorInitialized = false;

    window.toggleEditor = function() {
        var type = document.getElementById('type').value;
        var subjectFields = document.getElementById('subjectFields');
        
        if (type === 'sms') {
            if(subjectFields) subjectFields.style.display = 'none';
        } else {
            if(subjectFields) subjectFields.style.display = 'flex';
        }

        if (type === 'email') {
            if (!isEditorInitialized) {
                enEditor = CKEDITOR.replace('body_en', { height: 300 });
                neEditor = CKEDITOR.replace('body_ne', { height: 300 });
                isEditorInitialized = true;
            }
        } else {
            if (isEditorInitialized) {
                if (enEditor) { enEditor.destroy(); enEditor = null; }
                if (neEditor) { neEditor.destroy(); neEditor = null; }
                isEditorInitialized = false;
            }
        }
    }

    // Initial check
    setTimeout(function() {
        toggleEditor();
    }, 500);
</script>
@endpush
