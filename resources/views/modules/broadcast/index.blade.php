@extends('layouts.app')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="fw-bold py-3 mb-0">
            <span class="text-muted fw-light">Administration /</span> Broadcasts
        </h4>
        <div>
            <a href="{{ route('broadcast.send-form') }}" class="btn btn-success me-2">
                <i class="bx bx-send me-1"></i> Send Broadcast
            </a>
            <a href="{{ route('broadcast.create') }}" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New Template
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- Templates Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Notification Templates</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($templates as $template)
                    <tr>
                        <td>{{ $template->id }}</td>
                        <td><strong>{{ $template->name }}</strong></td>
                        <td>
                            @php
                                $badgeClass = match($template->type) {
                                    'email' => 'info',
                                    'fcm' => 'warning',
                                    'sms' => 'success',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-label-{{ $badgeClass }}">{{ strtoupper($template->type) }}</span>
                        </td>
                        <td>{{ formatDate($template->created_at, false) }}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-icon btn-primary me-1" 
                                    data-bs-toggle="modal" data-bs-target="#viewTemplateModal{{ $template->id }}" title="View Content">
                                <i class="bx bx-show"></i>
                            </button>
                            
                            <form action="{{ route('broadcast.destroy', $template->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this template?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-icon btn-danger" title="Delete">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </form>

                            <!-- View Template Modal -->
                            <div class="modal fade" id="viewTemplateModal{{ $template->id }}" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header border-bottom">
                                            <div class="d-flex align-items-center">
                                                <div class="avatar flex-shrink-0 me-2">
                                                    <span class="avatar-initial rounded bg-label-{{ $badgeClass }}"><i class="bx {{ $template->type == 'email' ? 'bx-envelope' : ($template->type == 'fcm' ? 'bx-bell' : 'bx-message-square-dots') }}"></i></span>
                                                </div>
                                                <div>
                                                    <h5 class="modal-title mb-0">{{ $template->name }}</h5>
                                                    <small class="text-muted">Template Type: {{ strtoupper($template->type) }}</small>
                                                </div>
                                            </div>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body bg-light-gray">
                                            <div class="row g-4">
                                                <div class="col-md-6">
                                                    <div class="card shadow-none border h-100">
                                                        <div class="card-header bg-lighter py-2 border-bottom">
                                                            <h6 class="mb-0 fw-bold"><i class="bx bx-world me-1"></i> English (EN)</h6>
                                                        </div>
                                                        <div class="card-body pt-3">
                                                            @if($template->subject_en)
                                                                <p class="mb-1 fw-bold text-dark small text-uppercase">Subject</p>
                                                                <p class="mb-3 p-2 bg-lighter rounded border-start border-primary border-3">{{ $template->subject_en }}</p>
                                                            @endif
                                                            <p class="mb-1 fw-bold text-dark small text-uppercase">Message Body</p>
                                                            <div class="p-3 border rounded bg-white shadow-sm" style="min-height: 150px; font-family: 'Public Sans', sans-serif;">
                                                                {!! $template->type == 'email' ? $template->body_en : nl2br(e($template->body_en)) !!}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card shadow-none border h-100">
                                                        <div class="card-header bg-lighter py-2 border-bottom">
                                                            <h6 class="mb-0 fw-bold"><i class="bx bx-font me-1"></i> Nepali (NE)</h6>
                                                        </div>
                                                        <div class="card-body pt-3">
                                                            @if($template->subject_ne)
                                                                <p class="mb-1 fw-bold text-dark small text-uppercase">Subject</p>
                                                                <p class="mb-3 p-2 bg-lighter rounded border-start border-warning border-3">{{ $template->subject_ne }}</p>
                                                            @endif
                                                            <p class="mb-1 fw-bold text-dark small text-uppercase">Message Body</p>
                                                            <div class="p-3 border rounded bg-white shadow-sm" style="min-height: 150px; font-family: 'Public Sans', sans-serif;">
                                                                {!! $template->type == 'email' ? $template->body_ne : nl2br(e($template->body_ne)) !!}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer border-top">
                                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close Preview</button>
                                            <a href="{{ route('broadcast.send-form') }}?template_id={{ $template->id }}" class="btn btn-primary">
                                                <i class="bx bx-send me-1"></i> Use this Template
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center">No templates found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $templates->appends(['broadcasts_page' => $broadcasts->currentPage()])->links() }}
        </div>
    </div>

    <!-- History Section -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Broadcast History</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Template</th>
                        <th>Type</th>
                        <th>Total</th>
                        <th>Success</th>
                        <th>Fail</th>
                        <th>Sent By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="table-border-bottom-0">
                    @forelse($broadcasts as $broadcast)
                    <tr>
                        <td>{{ formatDate($broadcast->created_at) }}</td>
                        <td>{{ $broadcast->title }}</td>
                        <td>
                            @php
                                $badgeClass = match($broadcast->type) {
                                    'email' => 'info',
                                    'fcm' => 'warning',
                                    'sms' => 'success',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-label-{{ $badgeClass }}">{{ strtoupper($broadcast->type) }}</span>
                        </td>
                        <td>{{ $broadcast->total_count }}</td>
                        <td><span class="text-success">{{ $broadcast->success_count }}</span></td>
                        <td><span class="text-danger">{{ $broadcast->fail_count }}</span></td>
                        <td>{{ $broadcast->sender->name }}</td>
                        <td>
                            <a href="{{ route('broadcast.show', $broadcast->id) }}" class="btn btn-sm btn-icon btn-outline-info" title="View Logs">
                                <i class="bx bx-list-ul"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center">No broadcast history found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            {{ $broadcasts->appends(['templates_page' => $templates->currentPage()])->links() }}
        </div>
    </div>
</div>
@endsection
