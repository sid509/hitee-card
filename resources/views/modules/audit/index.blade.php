@extends('layouts.app')

@section('title', 'System Audit & Reports')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Administration /</span> Audit Dashboard
</h4>

<div class="row">
    <!-- User Approval -->
    <div class="col-sm-6 col-lg-3 mb-4">
        <div class="card card-border-shadow-warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-warning"><i class="bx bx-user-voice"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0">{{ $stats['pending_approval'] }}</h4>
                </div>
                <p class="mb-1">Pending Approval</p>
                <p class="mb-0">
                    <small class="text-muted">Verified but not active</small>
                </p>
                <a href="{{ route('audit.pending-approval') }}" class="btn btn-sm btn-link px-0 mt-2">View List</a>
            </div>
        </div>
    </div>

    <!-- Users Without Cards -->
    <div class="col-sm-6 col-lg-3 mb-4">
        <div class="card card-border-shadow-danger h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-danger"><i class="bx bx-credit-card-front"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0">{{ $stats['without_cards'] }}</h4>
                </div>
                <p class="mb-1">No Active Cards</p>
                <p class="mb-0">
                    <small class="text-muted">Users without linked cards</small>
                </p>
                <a href="{{ route('audit.without-cards') }}" class="btn btn-sm btn-link px-0 mt-2 text-danger">View List</a>
            </div>
        </div>
    </div>

    <!-- Low Balance -->
    <div class="col-sm-6 col-lg-3 mb-4">
        <div class="card card-border-shadow-info h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-info"><i class="bx bx-wallet"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0">{{ $stats['low_balance'] }}</h4>
                </div>
                <p class="mb-1">Low Balance (< 50)</p>
                <p class="mb-0">
                    <small class="text-muted">Users with points under 50</small>
                </p>
                <a href="{{ route('audit.low-balance') }}" class="btn btn-sm btn-link px-0 mt-2 text-info">View List</a>
            </div>
        </div>
    </div>

    <!-- Unverified Emails -->
    <div class="col-sm-6 col-lg-3 mb-4">
        <div class="card card-border-shadow-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-secondary"><i class="bx bx-envelope"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0">{{ $stats['unverified_email'] }}</h4>
                </div>
                <p class="mb-1">Unverified Emails</p>
                <p class="mb-0">
                    <small class="text-muted">Registered but not validated</small>
                </p>
                <a href="{{ route('audit.unverified-email') }}" class="btn btn-sm btn-link px-0 mt-2 text-secondary">View List</a>
            </div>
        </div>
    </div>

    <!-- Orphan Cards -->
    <div class="col-sm-6 col-lg-3 mb-4">
        <div class="card card-border-shadow-dark h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2 pb-1">
                    <div class="avatar me-2">
                        <span class="avatar-initial rounded bg-label-success"><i class="bx bx-help-circle"></i></span>
                    </div>
                    <h4 class="ms-1 mb-0">{{ $stats['orphan_cards'] }}</h4>
                </div>
                <p class="mb-1">Orphan Cards</p>
                <p class="mb-0">
                    <small class="text-muted">Cards not assigned to users</small>
                </p>
                <a href="{{ route('audit.orphan-cards') }}" class="btn btn-sm btn-link px-0 mt-2 text-success">View List</a>
            </div>
        </div>
    </div>
</div>
@endsection
