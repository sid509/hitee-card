@extends('layouts.app')

@section('title', 'User Details')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Users /</span> {{ $user->name }}
</h4>

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
                            <span class="fw-medium me-2">Status:</span>
                            <span class="badge bg-label-{{ $user->status == 'active' ? 'success' : 'danger' }}">{{ ucfirst($user->status) }}</span>
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
        <ul class="nav nav-pills flex-column flex-md-row mb-3">
            <li class="nav-item">
                <a class="nav-link active" href="javascript:void(0);"><i class="bx bx-user me-1"></i>Account</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="{{ route('transactions.logs', $user->id) }}"><i class="bx bx-list-ul me-1"></i>Transactions</a>
            </li>
        </ul>
        <!--/ User Tabs -->

        <!-- Activity Timeline -->
        <div class="card mb-4">
            <h5 class="card-header">Recent Transactions</h5>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover recent-transactions-table w-100">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Activity</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            </div>
        </div>
        <!-- /Activity Timeline -->
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
                {data: 'created_at', name: 'created_at'},
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
            <form action="{{ route('balance.manual-add') }}" method="POST">
                @csrf
                <input type="hidden" name="user_id" value="{{ $user->id }}">
                <div class="modal-body">
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
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Reason for adding balance"></textarea>
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
