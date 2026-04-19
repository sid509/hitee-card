@extends('layouts.app')

@section('title', 'Create Parking')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Management / Parkings /</span> Create
</h4>

<div class="row">
    <div class="col-xl">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Add New Parking</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('parkings.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('modules.parkings.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
