@extends('layouts.app')

@section('title', 'Global System Settings')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">System /</span> Settings
</h4>

<form action="{{ route('settings.update') }}" method="POST">
    @csrf
    @method('PUT')
    
    <div class="row">
        <!-- Left Column: Contact & Legal -->
        <div class="col-md-6">
            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Contact Information</h5>
                    <i class="bx bx-envelope text-primary"></i>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="support_email">Support Email</label>
                        <input type="email" class="form-control" id="support_email" name="support_email" 
                            value="{{ $settings['support_email'] ?? '' }}" required placeholder="support@hitee.ai" />
                        <div class="form-text text-muted">Primary contact email displayed across the platform.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="support_phone">Support Phone</label>
                        <input type="text" class="form-control" id="support_phone" name="support_phone" 
                            value="{{ $settings['support_phone'] ?? '' }}" required placeholder="+977-1-XXXXXXX" />
                        <div class="form-text text-muted">Customer service hotline for user assistance.</div>
                    </div>
                </div>
            </div>

            <!-- Legal & Policy Links -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Legal & Policies</h5>
                    <i class="bx bx-link text-primary"></i>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="terms_url">Terms & Conditions URL</label>
                        <input type="url" class="form-control" id="terms_url" name="terms_url" 
                            value="{{ $settings['terms_url'] ?? '' }}" required placeholder="https://hitee.ai/terms" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="policy_url">Privacy Policy URL</label>
                        <input type="url" class="form-control" id="policy_url" name="policy_url" 
                            value="{{ $settings['policy_url'] ?? '' }}" required placeholder="https://hitee.ai/privacy" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: System Logic & Actions -->
        <div class="col-md-6">
            <!-- System Logic -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Transaction Logic</h5>
                    <i class="bx bx-cog text-primary"></i>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label" for="negative_allowed_point">Negative Balance Limit (pts)</label>
                        <div class="input-group">
                            <span class="input-group-text">-</span>
                            <input type="number" class="form-control" id="negative_allowed_point" name="negative_allowed_point" 
                                value="{{ $settings['negative_allowed_point'] ?? '0' }}" required min="0" />
                            <span class="input-group-text">pts</span>
                        </div>
                        <div class="form-text text-muted">Maximum points a user can "overdraw" during a trip before the card is blocked.</div>
                    </div>
                </div>
            </div>

            <!-- Save Card -->
            <div class="card border-primary">
                <div class="card-body text-center py-4">
                    <i class="bx bx-info-circle mb-3 text-primary" style="font-size: 2rem;"></i>
                    <p class="mb-4">Changes made here will affect all users across the web and mobile platforms instantly.</p>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg shadow">
                            <i class="bx bx-save me-1"></i> Save All Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection
