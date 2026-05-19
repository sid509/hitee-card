@extends('layouts.app')

@section('title', 'System Settings')

@section('content')
<h4 class="fw-bold py-3 mb-4">
    <span class="text-muted fw-light">{{ __('messages.system') }} /</span> {{ __('messages.settings') }}
</h4>

<div class="row">
    <div class="col-xl-12">
        <div class="nav-align-top mb-4">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button type="button" class="nav-link active" role="tab" data-bs-toggle="tab" data-bs-target="#navs-general" aria-controls="navs-general" aria-selected="true">
                        <i class="bx bx-home me-1"></i> {{ __('messages.general') }}
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-social" aria-controls="navs-social" aria-selected="false">
                        <i class="bx bxl-facebook-circle me-1"></i> Auth & Social
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-notifications" aria-controls="navs-notifications" aria-selected="false">
                        <i class="bx bx-bell me-1"></i> {{ __('messages.broadcasts') }}
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-payments" aria-controls="navs-payments" aria-selected="false">
                        <i class="bx bx-credit-card me-1"></i> Payments
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-infrastructure" aria-controls="navs-infrastructure" aria-selected="false">
                        <i class="bx bx-server me-1"></i> {{ __('messages.fleet_infrastructure') }}
                    </button>
                </li>
                <li class="nav-item">
                    <button type="button" class="nav-link" role="tab" data-bs-toggle="tab" data-bs-target="#navs-email" aria-controls="navs-email" aria-selected="false">
                        <i class="bx bx-envelope me-1"></i> {{ __('messages.email') }} (SMTP)
                    </button>
                </li>
            </ul>
            <div class="tab-content">
                <!-- General Settings -->
                <div class="tab-pane fade show active" id="navs-general" role="tabpanel">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Support Email</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                                    <input type="email" class="form-control" name="support_email" value="{{ $settings['support_email'] ?? '' }}" placeholder="support@hitee.ai">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Support Phone</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-phone"></i></span>
                                    <input type="text" class="form-control" name="support_phone" value="{{ $settings['support_phone'] ?? '' }}" placeholder="+977-1-XXXXXXX">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Terms & Conditions URL</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-link"></i></span>
                                    <input type="url" class="form-control" name="terms_url" value="{{ $settings['terms_url'] ?? '' }}" placeholder="https://hitee.ai/terms">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Privacy Policy URL</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-link"></i></span>
                                    <input type="url" class="form-control" name="policy_url" value="{{ $settings['policy_url'] ?? '' }}" placeholder="https://hitee.ai/privacy">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Negative Balance Limit (pts)</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-hash"></i></span>
                                    <input type="number" class="form-control" name="negative_allowed_point" value="{{ $settings['negative_allowed_point'] ?? '0' }}" min="0">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary shadow"><i class="bx bx-save me-1"></i> Save General Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Auth & Social -->
                <div class="tab-pane fade" id="navs-social" role="tabpanel">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Facebook Client ID</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-key"></i></span>
                                    <input type="text" class="form-control" name="facebook_client_id" value="{{ $settings['facebook_client_id'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Facebook Client Secret</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                                    <input type="password" class="form-control" name="facebook_client_secret" value="{{ $settings['facebook_client_secret'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Facebook Redirect URL</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-link"></i></span>
                                    <input type="text" class="form-control" name="facebook_redirect_url" value="{{ $settings['facebook_redirect_url'] ?? '' }}" placeholder="https://hitee.ai/auth/facebook/callback">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Google Client ID</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-key"></i></span>
                                    <input type="text" class="form-control" name="google_client_id" value="{{ $settings['google_client_id'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Google Client Secret</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                                    <input type="password" class="form-control" name="google_client_secret" value="{{ $settings['google_client_secret'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Google Redirect URL</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-link"></i></span>
                                    <input type="text" class="form-control" name="google_redirect_url" value="{{ $settings['google_redirect_url'] ?? '' }}" placeholder="https://hitee.ai/auth/google/callback">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary shadow"><i class="bx bx-save me-1"></i> Save Auth Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Notifications -->
                <div class="tab-pane fade" id="navs-notifications" role="tabpanel">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-12 mb-4">
                                <label class="form-label fw-bold text-primary"><i class="bx bxl-firebase me-1"></i> Firebase Service Account JSON (Recommended for HTTP v1)</label>
                                <textarea class="form-control-text" name="FIREBASE_SERVICE_ACCOUNT" rows="15" style="font-family: monospace; font-size: 0.85rem;" placeholder='{ "type": "service_account", ... }'>{{ $settings['FIREBASE_SERVICE_ACCOUNT'] ?? '' }}</textarea>
                                <div class="form-text mt-2">
                                    <i class="bx bx-info-circle me-1"></i> Paste the entire content of your Service Account JSON file here. 
                                    <br>Get this from: <strong>Firebase Console > Project Settings > Service Accounts > Generate New Private Key</strong>.
                                </div>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label text-muted">Legacy Firebase Server Key (Deprecated)</label>
                                <textarea class="form-control-text" name="NOTIFICATION_TOKEN" rows="2">{{ $settings['NOTIFICATION_TOKEN'] ?? '' }}</textarea>
                                <div class="form-text">This legacy key is being phased out by Google in favor of the Service Account JSON above.</div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary shadow"><i class="bx bx-save me-1"></i> Save Notification Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Payments -->
                <div class="tab-pane fade" id="navs-payments" role="tabpanel">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Khalti Secret Key</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                                    <input type="password" class="form-control" name="khalti_secret_key" value="{{ $settings['khalti_secret_key'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Khalti Public Key</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-key"></i></span>
                                    <input type="text" class="form-control" name="khalti_public_key" value="{{ $settings['khalti_public_key'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stripe Secret Key</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                                    <input type="password" class="form-control" name="stripe_secret_key" value="{{ $settings['stripe_secret_key'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stripe Publishable Key</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-key"></i></span>
                                    <input type="text" class="form-control" name="stripe_publishable_key" value="{{ $settings['stripe_publishable_key'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Stripe Currency (lowercase, e.g. usd, inr, npr)</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-money"></i></span>
                                    <input type="text" class="form-control" name="stripe_currency" value="{{ $settings['stripe_currency'] ?? 'usd' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Khalti Mode</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-chevron-right"></i></span>
                                    <select class="form-select" name="khalti_mode">
                                        <option value="test" {{ ($settings['khalti_mode'] ?? '') == 'test' ? 'selected' : '' }}>Test</option>
                                        <option value="live" {{ ($settings['khalti_mode'] ?? '') == 'live' ? 'selected' : '' }}>Live</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary shadow"><i class="bx bx-save me-1"></i> Save Payment Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Infrastructure -->
                <div class="tab-pane fade" id="navs-infrastructure" role="tabpanel">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">AWS Access Key ID</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-key"></i></span>
                                    <input type="text" class="form-control" name="aws_access_key_id" value="{{ $settings['aws_access_key_id'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">AWS Secret Access Key</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                                    <input type="password" class="form-control" name="aws_secret_access_key" value="{{ $settings['aws_secret_access_key'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">AWS Default Region</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-globe"></i></span>
                                    <input type="text" class="form-control" name="aws_default_region" value="{{ $settings['aws_default_region'] ?? 'us-east-1' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">AWS S3 Bucket</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-server"></i></span>
                                    <input type="text" class="form-control" name="aws_bucket" value="{{ $settings['aws_bucket'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary shadow"><i class="bx bx-save me-1"></i> Save Infrastructure Settings</button>
                        </div>
                    </form>
                </div>

                <!-- Email -->
                <div class="tab-pane fade" id="navs-email" role="tabpanel">
                    <form action="{{ route('settings.update') }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Mail Host</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-server"></i></span>
                                    <input type="text" class="form-control" name="mail_host" value="{{ $settings['mail_host'] ?? 'smtp.mailtrap.io' }}">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Mail Port</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-hash"></i></span>
                                    <input type="text" class="form-control" name="mail_port" value="{{ $settings['mail_port'] ?? '2525' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mail Username</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-user"></i></span>
                                    <input type="text" class="form-control" name="mail_username" value="{{ $settings['mail_username'] ?? '' }}">
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mail Password</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-lock-alt"></i></span>
                                    <input type="password" class="form-control" name="mail_password" value="{{ $settings['mail_password'] ?? '' }}">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mail Encryption</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-shield"></i></span>
                                    <select class="form-select" name="mail_encryption">
                                        <option value="tls" {{ ($settings['mail_encryption'] ?? '') == 'tls' ? 'selected' : '' }}>TLS</option>
                                        <option value="ssl" {{ ($settings['mail_encryption'] ?? '') == 'ssl' ? 'selected' : '' }}>SSL</option>
                                        <option value="null" {{ ($settings['mail_encryption'] ?? '') == 'null' ? 'selected' : '' }}>None</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mail From Address</label>
                                <div class="input-group input-group-merge">
                                    <span class="input-group-text"><i class="bx bx-envelope"></i></span>
                                    <input type="email" class="form-control" name="mail_from_address" value="{{ $settings['mail_from_address'] ?? 'no-reply@hitee.ai' }}">
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary shadow"><i class="bx bx-save me-1"></i> Save Email Settings</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
