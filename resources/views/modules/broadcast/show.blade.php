@extends('layouts.app')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold py-3 mb-0">
            <span class="text-muted fw-light">Broadcasts /</span> History Detail
        </h4>
        <a href="{{ route('broadcast.index') }}" class="btn btn-secondary">Back to List</a>
    </div>

    <div class="row">
        <!-- Summary Card -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between">
                    <h5 class="card-title mb-0">Summary</h5>
                    @php
                        $badgeClass = match($broadcast->type) {
                            'email' => 'info',
                            'fcm' => 'warning',
                            'sms' => 'success',
                            default => 'secondary'
                        };
                    @endphp
                    <span class="badge bg-label-{{ $badgeClass }}">{{ strtoupper($broadcast->type) }}</span>
                </div>
                <div class="card-body pt-2">
                    <ul class="list-unstyled mb-0">
                        <li class="d-flex mb-3">
                            <span class="fw-bold me-2">Title:</span>
                            <span>{{ $broadcast->title }}</span>
                        </li>
                        <li class="d-flex mb-3">
                            <span class="fw-bold me-2">Sent By:</span>
                            <span>{{ $broadcast->sender->name }}</span>
                        </li>
                        <li class="d-flex mb-3">
                            <span class="fw-bold me-2">Date:</span>
                            <span>{{ $broadcast->created_at->format('M d, Y H:i:s') }}</span>
                        </li>
                        <li class="d-flex mb-3">
                            <span class="fw-bold me-2">Total Targeted:</span>
                            <span>{{ $broadcast->total_count }}</span>
                        </li>
                        <li class="d-flex mb-3">
                            <span class="fw-bold me-2 text-success">Success:</span>
                            <span class="text-success">{{ $broadcast->success_count }}</span>
                        </li>
                        <li class="d-flex mb-0">
                            <span class="fw-bold me-2 text-danger">Failed:</span>
                            <span class="text-danger">{{ $broadcast->fail_count }}</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Logs Section -->
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recipient Logs</h5>
                </div>
                <div class="table-responsive text-nowrap">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Language</th>
                                <th>Status</th>
                                <th>Info</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($logs as $log)
                            <tr>
                                <td>
                                    <div class="d-flex justify-content-start align-items-center">
                                        <div class="d-flex flex-column">
                                            <span class="fw-semibold">{{ $log->user->name }}</span>
                                            <small class="text-muted">{{ $log->user->email ?? $log->user->phone_number }}</small>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ strtoupper($log->language) }}</td>
                                <td>
                                    <span class="badge bg-label-{{ $log->status == 'sent' ? 'success' : 'danger' }}">
                                        {{ strtoupper($log->status) }}
                                    </span>
                                </td>
                                <td>
                                    @if($log->error_message)
                                        <small class="text-danger" title="{{ $log->error_message }}">{{ \Illuminate\Support\Str::limit($log->error_message, 30) }}</small>
                                    @else
                                        <span class="text-muted small">N/A</span>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center">No logs available.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    {{ $logs->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
