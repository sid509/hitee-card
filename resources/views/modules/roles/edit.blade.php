@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Roles /</span> Edit
</h4>

<div class="row">
    <div class="col-xl">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Role: {{ $role->name }}</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('roles.update', $role->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    @include('modules.roles.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
