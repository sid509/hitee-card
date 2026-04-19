<table class="table table-bordered table-sm text-center">
    <thead>
        <tr>
            <th style="min-width: 150px;">From \ To</th>
            @foreach($route->stops as $stop)
                <th>{{ $stop->stop_name }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($route->stops as $fromStop)
            <tr>
                <th class="bg-light text-start">{{ $fromStop->stop_name }}</th>
                @foreach($route->stops as $toStop)
                    @php
                        $value = '';
                        if (isset($fare)) {
                            $matrixEntry = $fare->matrices->where('from_stop_id', $fromStop->id)->where('to_stop_id', $toStop->id)->first();
                            $value = $matrixEntry ? $matrixEntry->amount : '';
                        }
                    @endphp
                    <td>
                        @if($fromStop->id == $toStop->id)
                            <input type="text" class="form-control form-control-sm text-center border-0 bg-transparent" value="-" disabled>
                        @else
                            <div class="input-group input-group-sm">
                                <input type="number" 
                                    class="form-control form-control-sm text-center matrix-inline-edit" 
                                    placeholder="0.00" 
                                    value="{{ $value }}"
                                    step="0.01" 
                                    min="0"
                                    data-fare-id="{{ $fare->id ?? '' }}"
                                    data-from-id="{{ $fromStop->id }}"
                                    data-to-id="{{ $toStop->id }}">
                            </div>
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>

<script>
$(function() {
    $('.matrix-inline-edit').on('change', function() {
        const input = $(this);
        const fareId = input.data('fare-id');
        const fromId = input.data('from-id');
        const toId = input.data('to-id');
        const amount = input.val();

        if (!fareId) return; // Only works for existing fares (Edit mode)

        input.addClass('border-primary');

        $.ajax({
            url: "{{ route('fares.update-matrix-cell') }}",
            method: "POST",
            data: {
                _token: "{{ csrf_token() }}",
                fare_id: fareId,
                from_stop_id: fromId,
                to_stop_id: toId,
                amount: amount
            },
            success: function(res) {
                if (res.status) {
                    input.removeClass('border-primary').addClass('border-success');
                    setTimeout(() => input.removeClass('border-success'), 1500);
                    showToast('Price Updated', 'Success', 'success');
                }
            },
            error: function() {
                input.removeClass('border-primary').addClass('border-danger');
                showAlert('Failed to update price. Please try again.', 'error');
            }
        });
    });
});
</script>
