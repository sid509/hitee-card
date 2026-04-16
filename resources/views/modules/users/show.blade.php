@extends('layouts.app')

@section('title', 'User Details')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Users /</span> View
</h4>

<div class="row">
    <div class="col-xl">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">User Details</h5>
                <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm">Back to List</a>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <label class="col-sm-2 col-form-label fw-bold">Full Name</label>
                    <div class="col-sm-10">
                        <div class="form-control-plaintext">{{ $user->name }}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-sm-2 col-form-label fw-bold">Email</label>
                    <div class="col-sm-10">
                        <div class="form-control-plaintext">{{ $user->email }}</div>
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-sm-2 col-form-label fw-bold">Joined At</label>
                    <div class="col-sm-10">
                        <div class="form-control-plaintext">{{ $user->created_at->format('M d, Y H:i') }}</div>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="{{ route('users.edit', $user->id) }}" class="btn btn-primary me-2">Edit User</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
