@extends('layouts.app')

@section('title', 'Edit Subscription Model')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Subscription Models /</span> Edit
</h4>

<div class="row">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Subscription Model: {{ $subscriptionModel->name }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('subscription-models.update', $subscriptionModel->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('modules.subscription_models.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
