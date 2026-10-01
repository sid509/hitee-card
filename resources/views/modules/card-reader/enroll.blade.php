@extends('layouts.app')

@section('title', 'Card Reader - Enroll')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Card Reader /</span> Enroll Card
</h4>

<div class="row">
    <!-- Enrollment Form -->
    <div class="col-xl-5">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-wifi me-2"></i>Tap Card to Enroll</h5>
                <span class="badge bg-label-primary" id="readerStatus">Ready</span>
            </div>
            <div class="card-body">
                <!-- UID Input — auto-fills when reader types in HID keyboard mode -->
                <div class="mb-3">
                    <label class="form-label" for="card_number">Card UID <span class="text-danger">*</span></label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="bx bx-credit-card"></i></span>
                        <input type="text" class="form-control form-control-lg" id="card_number"
                               placeholder="Tap card on reader or type UID..."
                               autocomplete="off" autofocus />
                        <button class="btn btn-outline-secondary" type="button" id="checkUidBtn">
                            <i class="bx bx-search"></i> Check
                        </button>
                    </div>
                    <div class="form-text">Tap the physical card on the ACR1552U reader. The UID will auto-fill if reader is in HID keyboard mode.</div>
                    <div id="uidCheckResult" class="mt-2"></div>
                </div>

                <!-- HWID (auto-generated, editable) -->
                <div class="mb-3">
                    <label class="form-label" for="hwid">Card HWID</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="bx bx-hash"></i></span>
                        <input type="text" class="form-control" id="hwid" placeholder="Auto-generated from UID" />
                    </div>
                    <div class="form-text">Leave empty to auto-generate from UID.</div>
                </div>

                <!-- User Assignment -->
                <div class="mb-3">
                    <label class="form-label" for="user_id">Assign to User (Optional)</label>
                    <select name="user_id" id="user_id" class="form-select select2-users">
                        <option value="">Unassigned (Orphan Card)</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Initial Balance -->
                <div class="mb-3">
                    <label class="form-label" for="initial_balance">Initial Balance (pts)</label>
                    <div class="input-group input-group-merge">
                        <span class="input-group-text"><i class="bx bx-coin"></i></span>
                        <input type="number" class="form-control" id="initial_balance" placeholder="0" min="0" step="1" />
                    </div>
                </div>

                <!-- Status -->
                <div class="mb-3">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" class="form-select">
                        <option value="active" selected>Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="blocked">Blocked</option>
                    </select>
                </div>

                <!-- Checkboxes -->
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_currently_active" value="1">
                        <label class="form-check-label" for="is_currently_active">Set as active card for user</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_physical" value="1" checked>
                        <label class="form-check-label" for="is_physical">Physical card</label>
                    </div>
                </div>

                <!-- Submit -->
                <button type="button" class="btn btn-primary btn-lg w-100" id="enrollBtn">
                    <i class="bx bx-check-circle me-1"></i> Enroll Card
                </button>
            </div>
        </div>
    </div>

    <!-- Result + Recent Cards -->
    <div class="col-xl-7">
        <!-- Enrollment Result -->
        <div class="card mb-4" id="resultCard" style="display:none;">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-check-shield me-2 text-success"></i>Enrollment Result</h5>
            </div>
            <div class="card-body" id="resultBody"></div>
        </div>

        <!-- Recently Enrolled Cards -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recently Enrolled Cards</h5>
                <a href="{{ route('cards.index') }}" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>UID</th>
                            <th>HWID</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Enrolled</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentCards as $card)
                        <tr>
                            <td><code>{{ $card->card_number }}</code></td>
                            <td><small class="text-muted">{{ $card->hwid }}</small></td>
                            <td>@if($card->user){{ $card->user->name }}@else<span class="text-muted">Unassigned</span>@endif</td>
                            <td>
                                @if($card->status === 'active')
                                    <span class="badge bg-label-success">Active</span>
                                @elseif($card->status === 'inactive')
                                    <span class="badge bg-label-secondary">Inactive</span>
                                @else
                                    <span class="badge bg-label-danger">Blocked</span>
                                @endif
                            </td>
                            <td><small>{{ $card->created_at->diffForHumans() }}</small></td>
                        </tr>
                        @endforeach
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
    // Select2 for user search
    $('.select2-users').select2({
        placeholder: 'Search Customer...',
        allowClear: true,
        width: '100%'
    });

    // Auto-generate HWID from UID
    $('#card_number').on('input', function() {
        const uid = $(this).val().trim();
        if (uid && !$('#hwid').val()) {
            $('#hwid').val('CARD-' + uid.toUpperCase());
        }
    });

    // Check if UID already exists
    $('#checkUidBtn').on('click', function() {
        const uid = $('#card_number').val().trim();
        if (!uid) return;

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span>');

        $.get('{{ route("card-reader.check-uid") }}', { card_number: uid })
            .done(function(data) {
                if (data.exists) {
                    $('#uidCheckResult').html(`
                        <div class="alert alert-warning mb-0 py-2">
                            <i class="bx bx-info-circle me-1"></i>
                            Card already registered!
                            <strong>${esc(data.card.card_number)}</strong> —
                            User: ${esc(data.card.user)} —
                            Balance: ${esc(data.card.balance)} pts —
                            Status: ${esc(data.card.status)}
                        </div>
                    `);
                    $('#enrollBtn').prop('disabled', true).addClass('btn-secondary').removeClass('btn-primary');
                } else {
                    $('#uidCheckResult').html(`
                        <div class="alert alert-success mb-0 py-2">
                            <i class="bx bx-check-circle me-1"></i>
                            UID <code>${esc(uid)}</code> is available for enrollment.
                        </div>
                    `);
                    $('#enrollBtn').prop('disabled', false).addClass('btn-primary').removeClass('btn-secondary');
                }
            })
            .fail(function() {
                $('#uidCheckResult').html('<div class="alert alert-danger mb-0 py-2">Error checking UID.</div>');
            })
            .always(function() {
                $btn.prop('disabled', false).html('<i class="bx bx-search"></i> Check');
            });
    });

    // Enroll card
    $('#enrollBtn').on('click', function() {
        const cardNumber = $('#card_number').val().trim();
        if (!cardNumber) {
            Swal.fire('Error', 'Please enter or scan a card UID', 'error');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Enrolling...');

        $.ajax({
            url: '{{ route("card-reader.enroll-store") }}',
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                card_number: cardNumber,
                hwid: $('#hwid').val().trim(),
                user_id: $('#user_id').val() || null,
                status: $('#status').val(),
                is_currently_active: $('#is_currently_active').is(':checked') ? 1 : 0,
                is_physical: $('#is_physical').is(':checked') ? 1 : 0,
                initial_balance: $('#initial_balance').val() || 0,
            },
            success: function(data) {
                $('#resultCard').show();
                $('#resultBody').html(`
                    <div class="alert alert-success">
                        <i class="bx bx-check-circle me-2"></i>${data.message}
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Card UID:</strong> <code>${data.card.card_number}</code></p>
                            <p><strong>HWID:</strong> ${data.card.hwid}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Status:</strong> ${data.card.status}</p>
                            <p><strong>Assigned to:</strong> ${data.card.user}</p>
                        </div>
                    </div>
                `);

                // Reset form
                $('#card_number').val('');
                $('#hwid').val('');
                $('#initial_balance').val('');
                $('#uidCheckResult').empty();
                $('#enrollBtn').prop('disabled', false).html('<i class="bx bx-check-circle me-1"></i> Enroll Card');

                Swal.fire('Success!', data.message, 'success');
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || 'Enrollment failed';
                Swal.fire('Error', msg, 'error');
                $btn.prop('disabled', false).html('<i class="bx bx-check-circle me-1"></i> Enroll Card');
            }
        });
    });

    // Listen for HID keyboard input — when reader "types" the UID
    // The reader typically outputs the UID followed by Enter (CR/LF)
    let uidBuffer = '';
    let uidTimer = null;
    $(document).on('keypress', function(e) {
        // Only capture if card_number field is focused or empty
        if (document.activeElement.id !== 'card_number' && $('#card_number').val()) return;

        // If Enter key is pressed and we have buffered chars, treat as UID from reader
        if (e.which === 13 && uidBuffer.length >= 4) {
            e.preventDefault();
            $('#card_number').val(uidBuffer);
            $('#card_number').trigger('input');
            $('#checkUidBtn').trigger('click');
            uidBuffer = '';
            return;
        }

        // Buffer hex chars (0-9, A-F, a-f) — typical UID output
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
