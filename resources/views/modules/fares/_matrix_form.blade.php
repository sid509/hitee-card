<table class="table table-bordered table-sm text-center fare-matrix-table">
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
                <th class="text-start">{{ $fromStop->stop_name }}</th>
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
                                    name="matrix[{{ $fromStop->id }}][{{ $toStop->id }}]"
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
