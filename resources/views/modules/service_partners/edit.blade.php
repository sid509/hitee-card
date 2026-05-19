@extends('layouts.app')

@section('title', 'Edit Service Partner')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Service Partners /</span> Edit
</h4>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Partner: {{ $servicePartner->name }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('service-partners.update', $servicePartner->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('modules.service_partners.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
