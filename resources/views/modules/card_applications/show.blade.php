@extends('layouts.app')

@section('title', 'Card Application Details')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="py-3 mb-4">
        <span class="text-muted fw-light">Card Applications /</span> Details
    </h4>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Application Information</h5>
                    <span class="badge @if($cardApplication->status == 'pending') bg-label-warning @elseif($cardApplication->status == 'approved') bg-label-success @else bg-label-danger @endif">
                        {{ ucfirst($cardApplication->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-sm-4 fw-medium">Applicant:</div>
                        <div class="col-sm-8">{{ $cardApplication->user->name }} ({{ $cardApplication->user->email }})</div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-sm-4 fw-medium">Type:</div>
                        <div class="col-sm-8">{{ ucfirst($cardApplication->type) }}</div>
                    </div>
                    <div class="row mb-4">
                        <div class="col-sm-4 fw-medium">Submitted Date:</div>
                        <div class="col-sm-8">{{ $cardApplication->created_at->format('M d, Y h:i A') }}</div>
                    </div>

                    @if($cardApplication->type == 'personalized')
                        <hr class="my-4">
                        <h6 class="mb-3 text-primary">KYC Details</h6>
                        <div class="row mb-4">
                            <div class="col-sm-4 fw-medium">Full Name:</div>
                            <div class="col-sm-8">{{ $cardApplication->kyc_data['full_name'] ?? 'N/A' }}</div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-sm-4 fw-medium">ID Type:</div>
                            <div class="col-sm-8">{{ ucfirst($cardApplication->kyc_data['id_type'] ?? 'N/A') }}</div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-sm-4 fw-medium">ID Number:</div>
                            <div class="col-sm-8">{{ $cardApplication->kyc_data['id_number'] ?? 'N/A' }}</div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-sm-4 fw-medium">ID Document:</div>
                            <div class="col-sm-8">
                                <a href="{{ $cardApplication->kyc_document_url }}" target="_blank">
                                    <img src="{{ $cardApplication->kyc_document_url }}" alt="KYC Document" class="img-thumbnail" style="max-height: 200px;">
                                </a>
                            </div>
                        </div>
                    @endif

                    @if($cardApplication->status != 'pending')
                        <hr class="my-4">
                        <h6 class="mb-3 text-info">Admin Action</h6>
                        <div class="row mb-4">
                            <div class="col-sm-4 fw-medium">Processed At:</div>
                            <div class="col-sm-8">{{ $cardApplication->processed_at ? $cardApplication->processed_at->format('M d, Y h:i A') : 'N/A' }}</div>
                        </div>
                        <div class="row mb-4">
                            <div class="col-sm-4 fw-medium">Admin Remarks:</div>
                            <div class="col-sm-8">{{ $cardApplication->admin_remarks ?? 'None' }}</div>
                        </div>
                        @if($cardApplication->card)
                            <div class="row mb-4">
                                <div class="col-sm-4 fw-medium">Issued Card:</div>
                                <div class="col-sm-8">
                                    <span class="badge bg-label-primary">{{ $cardApplication->card->card_number }}</span>
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        @if(auth()->user()->hasRole('super-admin') && $cardApplication->status == 'pending')
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header border-bottom">
                        <h5 class="mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body pt-4">
                        <div class="d-grid gap-2">
                            <a href="{{ route('cards.create', ['application_id' => $cardApplication->id]) }}" class="btn btn-primary">
                                <i class="bx bx-credit-card me-1"></i> Issue Card Now
                            </a>
                            <p class="text-muted small text-center mb-0">Or use the form below for manual processing</p>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Process Application</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('card-applications.update', $cardApplication->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            
                            <div class="mb-3">
                                <label class="form-label">Action</label>
                                <select name="status" id="action_status" class="form-select" required>
                                    <option value="">Select Action</option>
                                    <option value="approved">Approve & Issue Card</option>
                                    <option value="rejected">Reject Application</option>
                                </select>
                            </div>

                            <div class="mb-3" id="card_selection" style="display: none;">
                                <label class="form-label">Assign Physical Card</label>
                                <select name="card_number" class="form-select select2-ajax-cards">
                                    <option value="">Search Available Cards...</option>
                                </select>
                                <div class="form-text text-danger small">Only cards NOT assigned to any user will appear here.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Admin Remarks</label>
                                <textarea name="admin_remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Submit Decision</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function() {
        $('#action_status').on('change', function() {
            if ($(this).val() === 'approved') {
                $('#card_selection').slideDown();
                $('.select2-ajax-cards').prop('required', true);
            } else {
                $('#card_selection').slideUp();
                $('.select2-ajax-cards').prop('required', false);
            }
        });

        // Search for unassigned cards
        $('.select2-ajax-cards').select2({
            ajax: {
                url: "{{ route('search.global') }}",
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        type: 'unassigned_cards'
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.map(function(item) {
                            return { id: item.card_number, text: item.card_number + ' (HW: ' + item.hwid + ')' };
                        })
                    };
                },
                cache: true
            },
            placeholder: 'Search Cards...',
            minimumInputLength: 1,
            width: '100%'
        });
    });
</script>
<style>
    .select2-container--open { z-index: 9999 !important; }
</style>
@endpush
