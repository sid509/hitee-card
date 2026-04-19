@extends('layouts.app')

@section('title', 'Create Bus')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Management / Buses /</span> Create
</h4>

<div class="row">
    <div class="col-xl">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Add New Bus</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('buses.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @include('modules.buses.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
