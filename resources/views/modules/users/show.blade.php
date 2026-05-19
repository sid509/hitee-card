@extends('layouts.app')

@section('title', 'User Details')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">
        <span class="text-muted fw-light">Users /</span> {{ $user->name }}
    </h4>
    <a href="{{ route('users.index') }}" class="btn btn-secondary">Back to List</a>
</div>

<div class="row">
    <!-- User Sidebar -->
    <div class="col-xl-4 col-lg-5 col-md-5 order-1 order-md-0">
        <!-- User Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="user-avatar-section">
                    <div class="d-flex align-items-center flex-column">
                        <div class="avatar avatar-xl mb-3">
                            <span class="avatar-initial rounded-circle bg-label-primary">{{ substr($user->name, 0, 1) }}</span>
                        </div>
                        <div class="user-info text-center">
                            <h4 class="mb-2">{{ $user->name }}</h4>
                            @foreach($user->roles as $role)
                                <span class="badge bg-label-secondary">{{ $role->name }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-around flex-wrap my-4 py-3">
                    <div class="d-flex align-items-start me-4 mt-3 gap-3">
                        <span class="badge bg-label-primary p-2 rounded"><i class="bx bx-wallet bx-sm"></i></span>
                        <div>
                            <h5 class="mb-0">Rs. {{ number_format($user->balance(), 2) }}</h5>
                            <span>Balance</span>
                        </div>
                    </div>
                </div>
                <h5 class="pb-2 border-bottom mb-4">Details</h5>
                <div class="info-container">
                    <ul class="list-unstyled">
                        <li class="mb-3">
                            <span class="fw-medium me-2">Email:</span>
                            <span>{{ $user->email }}</span>
                        </li>
                        <li class="mb-3">
                            <span class="fw-medium me-2">Phone:</span>
                            <span>{{ $user->phone_number }}</span>
                        </li>
                        <li class="mb-3">
                            <span class="fw-medium me-2">Status:</span>
                            @php
                                $statusLabel = $user->status_label;
                                $badgeClass = match($statusLabel) {
                                    'active' => 'success',
                                    'inactive' => 'danger',
                                    'pending' => 'warning',
                                    default => 'secondary'
                                };
                            @endphp
                            <span class="badge bg-label-{{ $badgeClass }}">{{ ucfirst($statusLabel) }}</span>
                        </li>
                        <li class="mb-3">
                            <span class="fw-medium me-2">Joined:</span>
                            <span>{{ formatDate($user->created_at) }}</span>
                        </li>
                    </ul>
                    <div class="d-flex justify-content-center pt-3">
                        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-primary me-3">Edit</a>
                        @if(auth()->user()->hasRole('super-admin'))
                            <button class="btn btn-label-success" data-bs-toggle="modal" data-bs-target="#addBalanceModal">Add Balance</button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <!-- /User Card -->
    </div>
    <!--/ User Sidebar -->

    <!-- User Content -->
    <div class="col-xl-8 col-lg-7 col-md-7 order-0 order-md-1">
        <!-- User Tabs -->
        <ul class="nav nav-pills flex-column flex-md-row mb-3" role="tablist">
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-account" aria-controls="navs-pills-account" aria-selected="false">
                    <i class="bx bx-user me-1"></i>Account
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-cards" aria-controls="navs-pills-cards" aria-selected="true">
                    <i class="bx bx-credit-card me-1"></i>Cards
                </button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-pills-notifications" aria-controls="navs-pills-notifications" aria-selected="false">
                    <i class="bx bx-bell me-1"></i>{{ __('messages.broadcasts') }}
                </button>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('transactions.logs') }}?user_id={{ $user->id }}"><i class="bx bx-list-ul me-1"></i>Transactions</a>
            </li>
        </ul>
        <!--/ User Tabs -->

        <div class="tab-content p-0" style="background: none; border: none; box-shadow: none;">
            <!-- Account Tab -->
            <div class="tab-pane fade" id="navs-pills-account" role="tabpanel">
                <!-- Activity Timeline -->
                <div class="card mb-4">
                    <h5 class="card-header">Recent Transactions</h5>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover recent-transactions-table w-100">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Card</th>
                                        <th>Type</th>
                                        <th>Activity</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                            </table>                        </div>
                    </div>
                </div>
                <!-- /Activity Timeline -->
            </div>

            <!-- Cards Tab -->
            <div class="tab-pane fade show active" id="navs-pills-cards" role="tabpanel">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Linked Cards</h5>
                        @if(auth()->user()->hasRole('super-admin'))
                            <a href="{{ route('cards.create', ['user_id' => $user->id]) }}" class="btn btn-sm btn-primary">Add Card</a>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Card Number</th>
                                        <th>Status</th>
                                        <th>Usage</th>
                                        <th>Balance</th>
                                        <th>Added</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($user->cards as $card)
                                        <tr>
                                            <td>
                                                <a href="{{ route('cards.show', $card->id) }}" class="fw-medium">
                                                    {{ $card->card_number }}
                                                </a>
                                                <br><small class="text-muted">HW: {{ $card->hwid }}</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-label-{{ $card->status === 'active' ? 'success' : 'secondary' }}">
                                                    {{ ucfirst($card->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                @if($card->is_currently_active)
                                                    <span class="badge bg-label-info">Current Active</span>
                                                @else
                                                    <span class="text-muted small">Inactive</span>
                                                @endif
                                            </td>
                                            <td class="fw-medium text-primary">Rs. {{ number_format($card->balance(), 2) }}</td>
                                            <td>{{ formatDate($card->created_at) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="text-center text-muted py-3">No cards linked to this user.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notifications Tab -->
            <div class="tab-pane fade" id="navs-pills-notifications" role="tabpanel">
                <div class="card mb-4">
                    <h5 class="card-header">{{ __('messages.broadcast_history') }}</h5>
                    <div class="table-responsive text-nowrap">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('messages.date') }} & {{ __('messages.time') }}</th>
                                    <th>{{ __('messages.notification_templates') }}</th>
                                    <th>{{ __('messages.type') }}</th>
                                    <th>{{ __('messages.language') }}</th>
                                    <th>{{ __('messages.status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($notifications as $notif)
                                    <tr>
                                        <td>{{ formatDate($notif->created_at) }}</td>
                                        <td>{{ $notif->template ? $notif->template->name : 'Custom / Deleted' }}</td>
                                        <td>
                                            @php
                                                $badgeClass = match($notif->type) {
                                                    'email' => 'info',
                                                    'fcm' => 'warning',
                                                    'sms' => 'success',
                                                    default => 'secondary'
                                                };
                                            @endphp
                                            <span class="badge bg-label-{{ $badgeClass }}">{{ strtoupper($notif->type) }}</span>
                                        </td>
                                        <td>{{ strtoupper($notif->language) }}</td>
                                        <td>
                                            <span class="badge bg-label-{{ $notif->status == 'sent' ? 'success' : 'danger' }}">
                                                {{ ucfirst($notif->status) }}
                                            </span>
                                            @if($notif->error_message)
                                                <i class="bx bx-help-circle text-danger" data-bs-toggle="tooltip" title="{{ $notif->error_message }}"></i>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-3">No notification history.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($notifications->hasPages())
                        <div class="card-footer">
                            {{ $notifications->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <!--/ User Content -->
</div>

@endsection

@push('page-js')
<script type="module">
    $(function () {
        $('.recent-transactions-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            paging: true,
            pageLength: 5,
            lengthMenu: [5, 10, 25],
            ajax: "{{ route('transactions.logs', $user->id) }}",
            columns: [
                {data: 'display_date', name: 'created_at'},
                {data: 'card_info', name: 'card_info', orderable: false},
                {data: 'direction', name: 'direction', orderable: false, searchable: false},
                {data: 'type', name: 'type'},
                {data: 'amount', name: 'amount'},
            ],
            order: [[0, 'desc']]
        });
    });
</script>
@endpush

@push('modals')
@if(auth()->user()->hasRole('super-admin'))
<!-- Add Balance Modal -->
<div class="modal fade" id="addBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Balance for {{ $user->name }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('transactions.manual-add') }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <input type="hidden" name="card_id" value="{{ $user->activeCard?->id }}">
                <div class="modal-body">
                    @if(!$user->activeCard)
                        <div class="alert alert-warning">
                            This user does not have an active card. Balance might not be usable for transit.
                        </div>
                    @endif
                    <div class="mb-3">
                        <label class="form-label">Current Balance</label>
                        <input type="text" class="form-control" value="Rs. {{ number_format($user->balance(), 2) }}" readonly disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Amount (Rs.)</label>
                        <input type="number" name="amount" class="form-control" placeholder="0.00" step="0.01" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select" required>
                            <option value="manual">Manual Load</option>
                            <option value="cashback">Cashback</option>
                            <option value="penalty_reversal">Penalty Reversal</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control-text" rows="2" placeholder="Reason for adding balance"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Balance</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endpush
