@extends('layouts.app')

@section('title', 'Apply for Card')

@push('page-css')
<style>
    .card-type-option {
        cursor: pointer;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        border-radius: 12px;
        position: relative;
    }
    .card-type-option:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }
    .card-type-option.active {
        border-color: #696cff;
        background-color: rgba(105, 108, 255, 0.05);
    }
    .card-type-option .selection-indicator {
        position: absolute;
        top: 15px;
        right: 15px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        border: 2px solid #d9dee3;
        display: flex;
        align-items: center;
        justify-content: center;
        background: white;
    }
    .card-type-option.active .selection-indicator {
        background: #696cff;
        border-color: #696cff;
        color: white;
    }
    .card-type-option .selection-indicator i {
        display: none;
        font-size: 14px;
    }
    .card-type-option.active .selection-indicator i {
        display: block;
    }
    .icon-box {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 20px;
    }
    .icon-box i {
        font-size: 32px;
    }
    .bg-label-primary .icon-box { background: rgba(105, 108, 255, 0.1); color: #696cff; }
    .bg-label-secondary .icon-box { background: rgba(133, 146, 163, 0.1); color: #8592a3; }
    
    .kyc-step-card {
        border-left: 4px solid #696cff;
    }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row justify-content-center">
        <div class="col-xl-9 col-lg-10">
            <div class="row mb-4 text-center">
                <div class="col-12">
                    <h4 class="fw-bold py-3 mb-0">Apply for Hitee Smart Card</h4>
                    <p class="text-muted">Choose the card type that best fits your daily commute needs.</p>
                </div>
            </div>

            <form action="{{ route('card-applications.store') }}" method="POST" enctype="multipart/form-data" id="cardApplicationForm">
                @csrf
                
                <!-- Section 1: Card Type Selection -->
                <div class="row mb-5 g-4">
                    <div class="col-md-6">
                        <div class="card h-100 card-type-option active" data-type="non-personalized">
                            <div class="selection-indicator">
                                <i class="bx bx-check"></i>
                            </div>
                            <div class="card-body p-4 text-center">
                                <div class="icon-box bg-label-secondary mx-auto">
                                    <i class="bx bx-credit-card"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Non-Personalized</h5>
                                <p class="text-muted mb-0">Standard travel card. No name printed. Quick issuance without identity verification.</p>
                                <input name="type" type="radio" value="non-personalized" class="d-none" id="radio_non_personalized" checked>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card h-100 card-type-option" data-type="personalized">
                            <div class="selection-indicator">
                                <i class="bx bx-check"></i>
                            </div>
                            <div class="card-body p-4 text-center">
                                <div class="icon-box bg-label-primary mx-auto">
                                    <i class="bx bx-user-pin"></i>
                                </div>
                                <h5 class="fw-bold mb-2">Personalized Card</h5>
                                <p class="text-muted mb-0">Includes your name. Higher transaction limits, travel insurance, and loyalty points.</p>
                                <input name="type" type="radio" value="personalized" class="d-none" id="radio_personalized">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 2: KYC Details (Conditional) -->
                <div id="kyc_section" style="display: none;">
                    <div class="card mb-4 shadow-sm kyc-step-card">
                        <div class="card-header border-bottom bg-light bg-opacity-10">
                            <h5 class="card-title mb-0"><i class="bx bx-shield-quarter me-2 text-primary"></i>Identity Verification (KYC)</h5>
                        </div>
                        <div class="card-body pt-4">
                            <div class="row g-4">
                                <div class="col-12">
                                    <label class="form-label fw-bold" for="full_name">Full Name (Exactly as on ID)</label>
                                    <div class="input-group input-group-merge">
                                        <span class="input-group-text"><i class="bx bx-user"></i></span>
                                        <input type="text" name="full_name" id="full_name" class="form-control form-control-lg" placeholder="e.g. SANDIP KUMAR SHARMA">
                                    </div>
                                    <div class="form-text">This name will be professionally printed on your smart card.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="id_type">Identity Document Type</label>
                                    <div class="input-group input-group-merge">
                                        <span class="input-group-text"><i class="bx bx-id-card"></i></span>
                                        <select name="id_type" id="id_type" class="form-select form-select-lg">
                                            <option value="">Choose Document...</option>
                                            <option value="citizenship">National Citizenship Card</option>
                                            <option value="license">Driving License</option>
                                            <option value="passport">International Passport</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="id_number">Identity Document Number</label>
                                    <div class="input-group input-group-merge">
                                        <span class="input-group-text"><i class="bx bx-hash"></i></span>
                                        <input type="text" name="id_number" id="id_number" class="form-control form-control-lg" placeholder="12-34-56-7890">
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-bold" for="kyc_document">Upload Identity Document Front</label>
                                    <div class="input-group input-group-merge">
                                        <span class="input-group-text"><i class="bx bx-cloud-upload"></i></span>
                                        <input type="file" name="kyc_document" id="kyc_document" class="form-control form-control-lg" accept="image/*">
                                    </div>
                                    <div class="form-text d-flex align-items-center mt-2">
                                        <i class="bx bx-info-circle me-1 text-info"></i> JPG, PNG or PDF. Max 2MB.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Submission -->
                <div class="card bg-label-primary border-0 mb-4 shadow-none">
                    <div class="card-body d-flex align-items-center py-3">
                        <i class="bx bx-info-circle me-3 fs-3"></i>
                        <div>
                            <p class="mb-0 small">By submitting, you agree to our Cardholder Terms & Conditions. Applications are typically processed within 24-48 working hours.</p>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <a href="{{ route('card-applications.index') }}" class="btn btn-label-secondary">
                        <i class="bx bx-chevron-left me-1"></i> Back to History
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="bx bx-send me-1"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
    $(function() {
        const typeCards = $('.card-type-option');
        const kycSection = $('#kyc_section');
        const fullNameInput = $('#full_name');
        const idTypeSelect = $('#id_type');
        const idNumberInput = $('#id_number');
        const kycDocInput = $('#kyc_document');

        typeCards.on('click', function() {
            const type = $(this).data('type');
            
            // UI Updates
            typeCards.removeClass('active');
            $(this).addClass('active');
            
            // Radio Updates
            $(`#radio_${type.replace('-', '_')}`).prop('checked', true);
            
            // KYC Logic
            if (type === 'personalized') {
                kycSection.slideDown();
                setRequired(true);
            } else {
                kycSection.slideUp();
                setRequired(false);
            }
        });

        function setRequired(val) {
            fullNameInput.prop('required', val);
            idTypeSelect.prop('required', val);
            idNumberInput.prop('required', val);
            kycDocInput.prop('required', val);
        }
    });
</script>
@endpush
