@extends('layouts.app')

@section('title', 'Create User')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Users /</span> Create
</h4>

<div class="row">
    <div class="col-xl">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Add New User</h5>
                <small class="text-muted float-end">Fill all fields</small>
            </div>
            <div class="card-body">
                <form action="{{ route('users.store') }}" method="POST">
                    @csrf
                    @include('modules.users.main-form')
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
