@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="py-3 mb-0"><span class="text-muted fw-light">Account /</span> Transactions</h4>
        @if(auth()->user()->hasRole('customers'))
            @if(auth()->user()->is_tourist)
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stripeTopupModal">
                    <i class="bx bx-plus me-1"></i> Add Balance
                </button>
            @else
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#khaltiTopupModal">
                    <i class="bx bx-plus me-1"></i> Add Balance
                </button>
            @endif
        @elseif(auth()->user()->hasRole('super-admin'))
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#quickAddBalanceModal">
                <i class="bx bx-plus me-1"></i> Load Funds
            </button>
        @endif
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <h5 class="card-header">Filters</h5>
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                @if(auth()->user()->hasRole('super-admin'))
                <div class="col-md-3">
                    <label class="form-label">User</label>
                    <select name="user_id" id="user_id_filter" class="form-select filter-input select2-users">
                        <option value="all">All Users</option>
                        @if($userId && $userId !== 'all')
                            @php $selectedUser = \App\Models\User::find($userId); @endphp
                            @if($selectedUser)
                                <option value="{{ $selectedUser->id }}" selected>{{ $selectedUser->name }} ({{ $selectedUser->email }})</option>
                            @endif
                        @endif
                    </select>
                </div>
                @endif
                <div class="{{ auth()->user()->hasRole('super-admin') ? 'col-md-3' : 'col-md-4' }}">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select filter-input">
                        <option value="">All Types</option>
                        <option value="in">Credit (In)</option>
                        <option value="out">Debit (Out)</option>
                    </select>
                </div>
                <div class="{{ auth()->user()->hasRole('super-admin') ? 'col-md-3' : 'col-md-4' }}">
                    <label class="form-label">Activity</label>
                    <select name="activity" class="form-select filter-input">
                        <option value="">All Activities</option>
                        <option value="manual">Manual Load</option>
                        <option value="khalti">Khalti Topup</option>
                        <option value="cashback">Cashback</option>
                        <option value="penalty_reversal">Penalty Reversal</option>
                        <option value="fare_deduction">Bus Fare</option>
                        <option value="parking">Parking Fee</option>
                        <option value="penalty">Penalty</option>
                        <option value="manual_deduction">Manual Deduction</option>
                    </select>
                </div>
                <div class="{{ auth()->user()->hasRole('super-admin') ? 'col-md-3' : 'col-md-4' }} d-flex align-items-end gap-2">
                    <button type="button" id="btnFilter" class="btn btn-primary btn-filter-reset d-none"><i class="bx bx-filter-alt"></i></button>
                    <button type="button" id="resetFilters" class="btn btn-outline-secondary btn-filter-reset"><i class="bx bx-refresh"></i></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <h5 class="card-header">Transaction History</h5>
        <div class="card-body">
            <div class="table-responsive text-nowrap">
                <table class="table table-hover transaction-data-table w-100">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Card</th>
                            <th>Type</th>
                            <th>Activity</th>
                            <th>Amount</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

@push('modals')
@if(auth()->user()->hasRole('super-admin'))
<!-- Quick Load Funds Modal -->
<div class="modal fade" id="quickAddBalanceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Load Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('transactions.manual-add') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Select User</label>
                        <select name="user_id" id="user_search_quick" class="form-select" required>
                            <option value="">Search User...</option>
                        </select>
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
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Reason for loading funds"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Load Funds</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

@if(auth()->user()->hasRole('customers'))
<!-- Khalti Topup Modal -->
<div class="modal fade" id="khaltiTopupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content @if(auth()->user()->is_tourist) position-relative @endif">
            @if(auth()->user()->is_tourist)
                <div class="position-absolute w-100 h-100 d-flex flex-column justify-content-center align-items-center bg-white bg-opacity-75" style="z-index: 10; backdrop-filter: blur(4px); border-radius: inherit;">
                    <i class="bx bx-lock-alt fs-1 text-muted mb-2"></i>
                    <h5 class="text-muted">Available for Local Users</h5>
                    <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#stripeTopupModal">Use Stripe Instead</button>
                </div>
            @endif
            <div class="modal-header">
                <h5 class="modal-title">Topup with Khalti</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('khalti.payment') }}" method="POST">
                @csrf
                <div class="modal-body text-center">
                    <img src="{{ asset('assets/img/hitee/khalti.png') }}" alt="Khalti" class="mb-4" style="height: 50px;">
                    <div class="mb-3 text-start">
                        <label class="form-label">Topup Amount (Rs.)</label>
                        <input type="number" name="amount" class="form-control form-control-lg" placeholder="100.00" step="1" min="10" required @if(auth()->user()->is_tourist) disabled @endif>
                        <div class="form-text">Minimum topup amount is Rs. 10.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" @if(auth()->user()->is_tourist) disabled @endif>Pay with Khalti</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Stripe Topup Modal -->
<div class="modal fade" id="stripeTopupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content @if(!auth()->user()->is_tourist) position-relative @endif">
            @if(!auth()->user()->is_tourist)
                <div class="position-absolute w-100 h-100 d-flex flex-column justify-content-center align-items-center bg-white bg-opacity-75" style="z-index: 10; backdrop-filter: blur(4px); border-radius: inherit;">
                    <i class="bx bx-lock-alt fs-1 text-muted mb-2"></i>
                    <h5 class="text-muted">Available for Tourists</h5>
                    <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#khaltiTopupModal">Use Khalti Instead</button>
                </div>
            @endif
            <div class="modal-header">
                <h5 class="modal-title">Topup with Stripe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('stripe.payment') }}" method="POST">
                @csrf
                <div class="modal-body text-center">
                    <i class="bx bxl-stripe text-primary mb-4" style="font-size: 80px;"></i>
                    <div class="mb-3 text-start">
                        <label class="form-label">Topup Amount (USD)</label>
                        <input type="number" name="amount" class="form-control form-control-lg" placeholder="10.00" step="0.01" min="1" required @if(!auth()->user()->is_tourist) disabled @endif>
                        <div class="form-text">Minimum topup amount is $1.00. 1 USD = 1 Hitee Point.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" @if(!auth()->user()->is_tourist) disabled @endif>Pay with Stripe</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endpush
@endsection

@push('page-js')
<script type="module">
    $(function () {
        var table = $('.transaction-data-table').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: "{{ route('transactions.logs', ['userId' => $userId]) }}",
                data: function (d) {
                    d.user_id = $('select[name="user_id"]').val();
                    d.type = $('select[name="type"]').val();
                    d.activity = $('select[name="activity"]').val();
                }
            },
            columns: [
                {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
                {data: 'display_date', name: 'created_at'},
                {data: 'customer', name: 'customer'},
                {data: 'card_info', name: 'card_info'},
                {data: 'direction', name: 'direction', orderable: false, searchable: false},
                {data: 'type', name: 'type'},
                {data: 'amount', name: 'amount'},
                {data: 'remarks', name: 'remarks'},
            ],
            order: [[1, 'desc']]
        });

        // Initialize Select2 for User Search
        $('.select2-users').select2({
            ajax: {
                url: "{{ route('search.users') }}",
                dataType: 'json',
                delay: 250,
                data: params => ({ q: params.term, page: params.page }),
                processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                cache: true
            },
            placeholder: 'Search User...',
            allowClear: true,
            width: '100%'
        });

        // Initialize Select2 for Quick Load Modal
        if ($('#user_search_quick').length) {
            $('#user_search_quick').select2({
                dropdownParent: $('#quickAddBalanceModal'),
                ajax: {
                    url: "{{ route('search.users') }}",
                    dataType: 'json',
                    delay: 250,
                    data: params => ({ q: params.term, page: params.page }),
                    processResults: (data, params) => ({ results: data.results, pagination: { more: data.pagination.more } }),
                    cache: true
                },
                placeholder: 'Search User...',
                minimumInputLength: 1,
                width: '100%'
            });
        }

        $('.filter-input').on('change', function() {
            table.draw();
            updateUrl();
        });

        $('#resetFilters').on('click', function() {
            $('#filterForm')[0].reset();
            // Explicitly set user_id to all if it exists (for super-admins)
            if ($('.select2-users').length) {
                $('.select2-users').val('all').trigger('change');
            }
            table.draw();
            updateUrl();
        });

        function updateUrl() {
            const params = new URLSearchParams();
            const userId = $('select[name="user_id"]').val();
            const type = $('select[name="type"]').val();
            const activity = $('select[name="activity"]').val();

            if (userId && userId !== 'all') params.set('user_id', userId);
            if (type) params.set('type', type);
            if (activity) params.set('activity', activity);

            const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            window.history.pushState({path: newUrl}, '', newUrl);
        }
    });
</script>
@endpush
