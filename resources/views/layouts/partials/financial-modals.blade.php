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
                        <textarea name="remarks" class="form-control-text" rows="2" placeholder="Reason for loading funds"></textarea>
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

@if(auth()->user()->hasRole('merchant'))
<!-- Withdraw Modal -->
<div class="modal fade" id="withdrawModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Withdraw Funds</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('merchant.withdraw') }}" method="POST">
                @csrf
                <div class="modal-body text-center">
                    <img src="{{ asset('assets/img/hitee/khalti.png') }}" alt="Khalti" class="mb-4" style="height: 50px;">
                    <div class="mb-3 text-start">
                        <label class="form-label">Available Income Balance</label>
                        <input type="text" class="form-control" value="Rs. {{ number_format(auth()->user()->merchantBalance(), 2) }}" readonly disabled>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label">Withdrawal Amount (Rs.)</label>
                        <input type="number" name="amount" class="form-control form-control-lg" placeholder="100.00" step="0.01" min="100" required>
                        <div class="form-text">Minimum withdrawal amount is Rs. 100. Funds will be loaded to your Khalti wallet.</div>
                    </div>
                    <div class="mb-3 text-start">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control-text" rows="2" placeholder="Optional remarks"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Process Withdrawal</button>
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
