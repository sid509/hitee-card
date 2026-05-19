@extends('layouts.app')

@section('title', 'Partner Details')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Service Partners /</span> Details
</h4>

<div class="row">
    <div class="col-md-5">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ $servicePartner->name }}</h5>
                <span class="badge {{ $servicePartner->status === 'active' ? 'bg-label-success' : 'bg-label-secondary' }}">
                    {{ ucfirst($servicePartner->status) }}
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <label class="col-sm-4 fw-bold">Merchant Owner:</label>
                    <div class="col-sm-8">{{ $servicePartner->merchant->name }} ({{ $servicePartner->merchant->email }})</div>
                </div>
                <div class="row mb-3">
                    <label class="col-sm-4 fw-bold">Service Type:</label>
                    <div class="col-sm-8">{{ $servicePartner->service_type }}</div>
                </div>
                <div class="row mb-3">
                    <label class="col-sm-4 fw-bold">Location:</label>
                    <div class="col-sm-8">
                        @if($servicePartner->latitude && $servicePartner->longitude)
                            {{ $servicePartner->latitude }}, {{ $servicePartner->longitude }}
                        @else
                            <span class="text-muted italic">Not set</span>
                        @endif
                    </div>
                </div>
                <div class="row mb-3">
                    <label class="col-sm-4 fw-bold">Member Since:</label>
                    <div class="col-sm-8">{{ formatDate($servicePartner->created_at, false) }}</div>
                </div>

                <div class="mt-4">
                    <a href="{{ route('service-partners.edit', $servicePartner->id) }}" class="btn btn-primary me-2">
                        <i class="bx bx-edit-alt me-1"></i> Edit Partner
                    </a>
                    <a href="{{ route('service-partners.index') }}" class="btn btn-label-secondary">Back to List</a>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Discount Rules & Matrix</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addDiscountModal">
                    <i class="bx bx-plus me-1"></i> Add Rule
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Target</th>
                                <th>Rule</th>
                                <th>Min. Spend</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($servicePartner->discounts as $discount)
                            <tr>
                                <td>
                                    @if($discount->subscriptionModel)
                                        <span class="badge bg-label-info">{{ $discount->subscriptionModel->name }}</span>
                                    @else
                                        <span class="badge bg-label-secondary">Global (All Cards)</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-bold">
                                        @if($discount->discount_type === 'percentage')
                                            {{ number_format($discount->discount_value, 0) }}% Off
                                        @else
                                            Rs. {{ number_format($discount->discount_value, 0) }} Off
                                        @endif
                                    </span>
                                </td>
                                <td>
                                    @if($discount->min_spend > 0)
                                        Rs. {{ number_format($discount->min_spend, 0) }}
                                    @else
                                        <span class="text-muted italic">No minimum</span>
                                    @endif
                                </td>
                                <td>
                                    <form action="{{ route('service-partners.discounts.destroy', [$servicePartner->id, $discount->id]) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-icon btn-sm btn-danger delete-btn"><i class="bx bx-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-3 text-muted">No discount rules defined.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Discount Modal -->
<div class="modal fade" id="addDiscountModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Discount Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('service-partners.discounts.store', $servicePartner->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="subscription_model_id">Subscription Model (Optional)</label>
                        <select name="subscription_model_id" id="subscription_model_id" class="form-select">
                            <option value="">Global (Apply to all subscription models)</option>
                            @foreach(\App\Models\SubscriptionModel::all() as $sm)
                                <option value="{{ $sm->id }}">{{ $sm->name }} ({{ $sm->category }})</option>
                            @endforeach
                        </select>
                        <div class="form-text">Leave blank to apply this discount to all subscription models.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="discount_type">Discount Type</label>
                            <select name="discount_type" id="discount_type" class="form-select" required>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount (Rs.)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="discount_value">Value</label>
                            <input type="number" step="0.01" name="discount_value" id="discount_value" class="form-control" placeholder="E.g. 10" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="min_spend">Minimum Spend Threshold (Optional)</label>
                        <div class="input-group input-group-merge">
                            <span class="input-group-text">Rs.</span>
                            <input type="number" step="1" name="min_spend" id="min_spend" class="form-control" placeholder="E.g. 1000" value="0">
                        </div>
                        <div class="form-text">The discount will only apply if the spend is above this amount.</div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label" for="description">Note / Description</label>
                        <textarea name="description" id="description" class="form-control-text" rows="2" placeholder="E.g. Seasonal 10% off"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Add Rule</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
