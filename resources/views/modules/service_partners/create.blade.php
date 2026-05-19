@extends('layouts.app')

@section('title', 'Add Service Partner')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Service Partners /</span> Add New
</h4>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Partner Details</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('service-partners.store') }}" method="POST">
                    @csrf
                    @include('modules.service_partners.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
