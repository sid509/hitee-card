@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Users /</span> Edit
</h4>

<div class="row">
    <div class="col-xl">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit User: {{ $user->name }}</h5>
                <small class="text-muted float-end">Update fields</small>
            </div>
            <div class="card-body">
                <form action="{{ route('users.update', $user->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('modules.users.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    document.addEventListener('DOMContentLoaded', function() {
        const $ = window.jQuery;
        if (!$) return;

        const roleCheckboxes = $('.role-checkbox');
        const merchantContainer = $('#merchant-type-container');

        const toggleMerchantType = () => {
            let isMerchantSelected = false;
            roleCheckboxes.each(function() {
                if ($(this).is(':checked') && $(this).data('slug') === 'merchant') {
                    isMerchantSelected = true;
                }
            });

            if (isMerchantSelected) {
                merchantContainer.slideDown();
            } else {
                merchantContainer.slideUp();
                $('#merchant_type').val('');
            }
        };

        roleCheckboxes.on('change', toggleMerchantType);
        toggleMerchantType(); // Initial check
    });
</script>
@endpush
