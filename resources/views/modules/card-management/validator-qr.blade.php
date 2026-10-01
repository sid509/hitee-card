@extends('layouts.app')

@section('title', 'Validator Provisioning QR')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0">
            <span class="text-muted fw-light">Card Management / Validators /</span> {{ $device->device_id }}
        </h4>
        <a href="{{ route('card-management.validators.index') }}" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to List
        </a>
    </div>

    <div class="row">
        {{-- Device details --}}
        <div class="col-md-5">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0">Device Details</h5></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-muted">Device ID</dt>
                        <dd class="col-sm-7"><code>{{ $device->device_id }}</code></dd>

                        <dt class="col-sm-5 text-muted">Vehicle ID</dt>
                        <dd class="col-sm-7">{{ $device->vehicle_id ?: '—' }}</dd>

                        <dt class="col-sm-5 text-muted">Route ID</dt>
                        <dd class="col-sm-7">{{ $device->route_id ?: '—' }}</dd>

                        <dt class="col-sm-5 text-muted">Terminal #</dt>
                        <dd class="col-sm-7"><code>{{ $device->terminal_number_hex ?: '—' }}</code></dd>

                        <dt class="col-sm-5 text-muted">Status</dt>
                        <dd class="col-sm-7">
                            @php
                                $statusClass = $device->status === 'ACTIVE' ? 'bg-label-success'
                                    : ($device->status === 'SUSPENDED' ? 'bg-label-danger' : 'bg-label-secondary');
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ e($device->status) }}</span>
                        </dd>

                        <dt class="col-sm-5 text-muted">Firmware</dt>
                        <dd class="col-sm-7">{{ $device->firmware_version ?: '—' }}</dd>

                        <dt class="col-sm-5 text-muted">Trips</dt>
                        <dd class="col-sm-7">{{ $tripsCount }}</dd>

                        <dt class="col-sm-5 text-muted">Last Heartbeat</dt>
                        <dd class="col-sm-7">
                            {{ $device->last_heartbeat_at
                                ? \Carbon\Carbon::parse($device->last_heartbeat_at)->diffForHumans()
                                : 'Never' }}
                        </dd>

                        <dt class="col-sm-5 text-muted">Registered</dt>
                        <dd class="col-sm-7">
                            {{ \Carbon\Carbon::parse($device->registered_at)->format('M d, Y H:i') }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h6 class="mb-0">QR Payload (JSON)</h6></div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        This is the exact JSON encoded in the QR. The validator's
                        <code>QrDeviceConfig.parse()</code> accepts this format.
                    </p>
                    <pre class="bg-light p-3 rounded small mb-0" id="qr-payload">{{ json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) }}</pre>
                    <div class="mt-3">
                        <button class="btn btn-sm btn-outline-secondary" onclick="copyPayload(this)">
                            <i class="bx bx-copy me-1"></i> Copy JSON
                        </button>
                        <a href="{{ route('card-management.validators.payload', $device->id) }}"
                           class="btn btn-sm btn-outline-info" target="_blank">
                            <i class="bx bx-code-curly me-1"></i> Raw endpoint
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- QR code --}}
        <div class="col-md-7">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Provisioning QR Code</h5>
                    <button class="btn btn-sm btn-primary" onclick="window.print()">
                        <i class="bx bx-printer me-1"></i> Print
                    </button>
                </div>
                <div class="card-body text-center">
                    <div class="d-inline-block p-3 bg-white rounded shadow-sm" id="qr-image">
                        {!! $qr !!}
                    </div>
                    <p class="mt-3 mb-1 fw-semibold">Scan with the HITEE Validator app</p>
                    <p class="text-muted small mb-0">
                        Device Setup &rarr; Scan QR Code. Scanning this pre-fills the
                        Device ID, Vehicle, Route and API URL and triggers
                        <code>POST /api/v1/devices/register</code>.
                    </p>
                    <div class="alert alert-warning mt-3 mb-0 text-start small">
                        <i class="bx bx-info-circle me-1"></i>
                        This QR carries identity + API endpoint only. It does
                        <strong>not</strong> contain DPK1 / TAC / Session Key material —
                        those are provisioned separately via the lab keystore.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('page-js')
<script>
function copyPayload(btn) {
    const text = document.getElementById('qr-payload').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const original = btn.innerHTML;
        btn.innerHTML = '<i class="bx bx-check me-1"></i> Copied!';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
    });
}
</script>
@endpush
@endsection
