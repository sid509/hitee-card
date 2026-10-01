@extends('layouts.app')

@section('title', 'Card Reader - Tap Test')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Card Reader /</span> Tap Test
</h4>

<div class="row">
    <!-- Tap Controls -->
    <div class="col-xl-5">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-tap me-2"></i>Simulate Tap</h5>
            </div>
            <div class="card-body">
                <!-- Card Selection -->
                <div class="mb-3">
                    <label class="form-label">Card UID</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="bx bx-credit-card"></i></span>
                        <input type="text" class="form-control form-control-lg" id="tapCardNumber"
                               placeholder="Tap card or select from list..."
                               autocomplete="off" />
                    </div>
                    <div class="form-text">Tap physical card (HID mode) or select a registered card below.</div>
                </div>

                <!-- Quick Card Select -->
                <div class="mb-3">
                    <label class="form-label">Quick Select Registered Card</label>
                    <select id="quickCardSelect" class="form-select">
                        <option value="">-- Select Card --</option>
                        @foreach($cards as $card)
                            <option value="{{ $card->card_number }}" data-user="{{ $card->user ? $card->user->name : 'Unassigned' }}" data-balance="{{ $card->user ? $card->user->balance() : $card->balance() }}">
                                {{ $card->card_number }} — {{ $card->user ? $card->user->name : 'Unassigned' }} ({{ $card->user ? $card->user->balance() : $card->balance() }} pts)
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Asset Selection -->
                <div class="mb-3">
                    <label class="form-label">Asset (Reader Location)</label>
                    <select id="assetSelect" class="form-select">
                        <optgroup label="Buses">
                            @foreach($buses as $bus)
                                <option value="{{ $bus->hwid }}" data-type="bus">{{ $bus->name }} ({{ $bus->bus_number }}) — HWID: {{ $bus->hwid }}</option>
                            @endforeach
                        </optgroup>
                        @if($parkings->isNotEmpty())
                        <optgroup label="Parking Lots">
                            @foreach($parkings as $parking)
                                <option value="{{ $parking->id }}" data-type="parking">{{ $parking->name }} — ID: {{ $parking->id }}</option>
                            @endforeach
                        </optgroup>
                        @endif
                    </select>
                </div>

                <!-- GPS Coordinates -->
                <div class="row mb-3">
                    <div class="col-6">
                        <label class="form-label">Latitude</label>
                        <input type="number" class="form-control" id="tapLat" value="27.7172" step="0.0001" />
                    </div>
                    <div class="col-6">
                        <label class="form-label">Longitude</label>
                        <input type="number" class="form-control" id="tapLon" value="85.3240" step="0.0001" />
                    </div>
                </div>

                <!-- Tap Button -->
                <button type="button" class="btn btn-primary btn-lg w-100 mb-2" id="tapBtn">
                    <i class="bx bx-tap me-2"></i>Process Tap
                </button>
                <button type="button" class="btn btn-outline-secondary w-100" id="getGpsBtn">
                    <i class="bx bx-current-location me-1"></i> Use My GPS Location
                </button>
            </div>
        </div>

        <!-- Card Info Display -->
        <div class="card" id="cardInfoCard" style="display:none;">
            <div class="card-header">
                <h5 class="mb-0">Card Details</h5>
            </div>
            <div class="card-body" id="cardInfoBody"></div>
        </div>
    </div>

    <!-- Tap Result + History -->
    <div class="col-xl-7">
        <!-- Tap Result -->
        <div class="card mb-4" id="tapResultCard" style="display:none;">
            <div class="card-header" id="tapResultHeader">
                <h5 class="mb-0">Tap Result</h5>
            </div>
            <div class="card-body" id="tapResultBody"></div>
        </div>

        <!-- Recent Taps -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Recent Taps (Last 20)</h5>
            </div>
            <div class="table-responsive" style="max-height: 500px; overflow-y: auto;">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Card</th>
                            <th>User</th>
                            <th>Type</th>
                            <th>Location</th>
                            <th>Asset</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody id="recentTapsBody">
                        @foreach($recentTaps as $tap)
                        <tr>
                            <td>{{ $tap->id }}</td>
                            <td><code>{{ $tap->card->card_number ?? 'N/A' }}</code></td>
                            <td>@if($tap->user){{ $tap->user->name }}@else<span class="text-muted">N/A</span>@endif</td>
                            <td>
                                @if($tap->type === 'in')
                                    <span class="badge bg-label-info">TAP IN</span>
                                @else
                                    <span class="badge bg-label-warning">TAP OUT</span>
                                @endif
                            </td>
                            <td>{{ $tap->resolved_location_name ?? 'N/A' }}</td>
                            <td>
                                @if(str_contains($tap->reference_type, 'Bus'))
                                    <i class="bx bx-bus"></i> Bus
                                @elseif(str_contains($tap->reference_type, 'Parking'))
                                    <i class="bx bxs-parking"></i> Parking
                                @else
                                    N/A
                                @endif
                            </td>
                            <td><small>{{ $tap->created_at->diffForHumans() }}</small></td>
                        </tr>
                        @endforeach
                        @if($recentTaps->isEmpty())
                        <tr><td colspan="7" class="text-center text-muted py-4">No taps yet. Process a tap to see results here.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('page-js')
<script type="module">
// Escape user-/server-supplied values before inserting into HTML.
function esc(v) {
    return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
$(function() {
    // Quick card select → fill UID + show info
    $('#quickCardSelect').on('change', function() {
        const uid = $(this).val();
        if (!uid) return;

        $('#tapCardNumber').val(uid);

        const $opt = $(this).find(':selected');
        $('#cardInfoCard').show();
        $('#cardInfoBody').html(`
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <p class="mb-1"><strong>UID:</strong> <code>${esc(uid)}</code></p>
                    <p class="mb-1"><strong>User:</strong> ${esc($opt.data('user'))}</p>
                </div>
                <div class="text-end">
                    <p class="mb-1"><strong>Balance:</strong> <span class="badge bg-label-primary fs-6">${esc($opt.data('balance'))} pts</span></p>
                </div>
            </div>
        `);
    });

    // Use GPS
    $('#getGpsBtn').on('click', function() {
        if (!navigator.geolocation) {
            Swal.fire('Error', 'Geolocation not supported by browser', 'error');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Getting GPS...');

        navigator.geolocation.getCurrentPosition(
            function(pos) {
                $('#tapLat').val(pos.coords.latitude.toFixed(6));
                $('#tapLon').val(pos.coords.longitude.toFixed(6));
                $btn.prop('disabled', false).html('<i class="bx bx-current-location me-1"></i> Use My GPS Location');
            },
            function(err) {
                Swal.fire('GPS Error', err.message, 'error');
                $btn.prop('disabled', false).html('<i class="bx bx-current-location me-1"></i> Use My GPS Location');
            }
        );
    });

    // Process Tap
    $('#tapBtn').on('click', function() {
        const cardNumber = $('#tapCardNumber').val().trim();
        const hwId = $('#assetSelect').val();
        const lat = $('#tapLat').val();
        const lon = $('#tapLon').val();

        if (!cardNumber) {
            Swal.fire('Error', 'Please enter or select a card UID', 'error');
            return;
        }
        if (!hwId) {
            Swal.fire('Error', 'Please select an asset (bus/parking)', 'error');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Processing Tap...');

        $.ajax({
            url: '{{ route("card-reader.tap-test-process") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                card_number: cardNumber,
                hw_id: hwId,
                lat: lat,
                lon: lon,
            },
            success: function(data) {
                const isSuccess = data.status === true;
                const type = data.content?.type || 'unknown';

                // Header
                let headerClass = isSuccess ? 'bg-label-success' : 'bg-label-danger';
                let headerText = isSuccess
                    ? (type === 'in' ? 'TAP IN Successful' : 'TAP OUT Successful')
                    : 'Tap Failed';

                $('#tapResultHeader').html(`<h5 class="mb-0"><i class="bx ${isSuccess ? 'bx-check-circle' : 'bx-x-circle'} me-2"></i>${headerText}</h5>`);

                // Body
                let html = `
                    <div class="alert ${isSuccess ? 'alert-success' : 'alert-danger'}">
                        <strong>${data.message}</strong>
                    </div>
                `;

                if (isSuccess && data.content) {
                    html += '<div class="row">';
                    html += `<div class="col-md-6"><p><strong>Type:</strong> <span class="badge ${type === 'in' ? 'bg-label-info' : 'bg-label-warning'}">${type.toUpperCase()}</span></p></div>`;

                    if (data.content.ride_id) {
                        html += `<div class="col-md-6"><p><strong>Ride ID:</strong> #${data.content.ride_id}</p></div>`;
                    }
                    if (data.content.location) {
                        html += `<div class="col-md-6"><p><strong>Location:</strong> ${data.content.location}</p></div>`;
                    }
                    if (data.content.fare_pts !== undefined) {
                        html += `<div class="col-md-6"><p><strong>Fare:</strong> ${data.content.fare_pts} pts</p></div>`;
                    }
                    if (data.content.new_balance_pts !== undefined) {
                        html += `<div class="col-md-6"><p><strong>New Balance:</strong> ${data.content.new_balance_pts} pts</p></div>`;
                    }
                    html += '</div>';
                }

                // Card details
                if (data.card_details) {
                    html += '<hr><h6>Card Details</h6>';
                    html += `<p><strong>UID:</strong> <code>${data.card_details.card_number}</code> | <strong>User:</strong> ${data.card_details.user} | <strong>Balance:</strong> ${data.card_details.balance} pts</p>`;
                }

                // Tap record
                if (data.tap_record) {
                    html += '<hr><h6>Tap Record (Database)</h6>';
                    html += `<p><strong>Tap ID:</strong> #${data.tap_record.id} | <strong>Type:</strong> ${data.tap_record.type} | <strong>Location:</strong> ${data.tap_record.location} | <strong>Time:</strong> ${data.tap_record.time}</p>`;
                }

                // Ride record
                if (data.ride_record) {
                    html += '<hr><h6>Ride Record (Database)</h6>';
                    html += `<p><strong>Ride ID:</strong> #${data.ride_record.id} | <strong>Status:</strong> ${data.ride_record.status} | <strong>Fare:</strong> ${data.ride_record.fare} pts | <strong>Asset:</strong> ${data.ride_record.asset_type}</p>`;
                }

                $('#tapResultBody').html(html);
                $('#tapResultCard').show();

                // Update card info if balance changed
                if (data.card_details) {
                    $('#cardInfoCard').show();
                    $('#cardInfoBody').html(`
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <p class="mb-1"><strong>UID:</strong> <code>${esc(data.card_details.card_number)}</code></p>
                                <p class="mb-1"><strong>User:</strong> ${esc(data.card_details.user)}</p>
                            </div>
                            <div class="text-end">
                                <p class="mb-1"><strong>Balance:</strong> <span class="badge bg-label-primary fs-6">${esc(data.card_details.balance)} pts</span></p>
                            </div>
                        </div>
                    `);
                }

                $btn.prop('disabled', false).html('<i class="bx bx-tap me-2"></i>Process Tap');

                if (isSuccess) {
                    Swal.fire({
                        title: type === 'in' ? 'Tapped In!' : 'Tapped Out!',
                        text: data.message,
                        icon: 'success',
                        timer: 3000,
                        showConfirmButton: false
                    });
                }
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Tap processing failed';
                $('#tapResultHeader').html('<h5 class="mb-0"><i class="bx bx-x-circle me-2 text-danger"></i>Tap Failed</h5>');
                $('#tapResultBody').html(`<div class="alert alert-danger">${esc(msg)}</div>`);
                $('#tapResultCard').show();
                $btn.prop('disabled', false).html('<i class="bx bx-tap me-2"></i>Process Tap');
            }
        });
    });

    // HID keyboard listener — same as enrollment page
    let uidBuffer = '';
    let uidTimer = null;
    $(document).on('keypress', function(e) {
        if (document.activeElement.id !== 'tapCardNumber' && $('#tapCardNumber').val()) return;

        if (e.which === 13 && uidBuffer.length >= 4) {
            e.preventDefault();
            $('#tapCardNumber').val(uidBuffer);
            uidBuffer = '';
            // Auto-trigger tap if asset is selected
            if ($('#assetSelect').val()) {
                $('#tapBtn').trigger('click');
            }
            return;
        }

        const char = String.fromCharCode(e.which);
        if (/^[0-9A-Fa-f]$/.test(char)) {
            uidBuffer += char;
            clearTimeout(uidTimer);
            uidTimer = setTimeout(() => { uidBuffer = ''; }, 2000);
        }
    });
});
</script>
@endpush
