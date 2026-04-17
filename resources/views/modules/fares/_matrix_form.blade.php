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
                    <td>
                        @if($fromStop->id == $toStop->id)
                            <input type="text" class="form-control form-control-sm text-center" value="-" disabled>
                        @else
                            <input type="number" 
                                   name="matrix[{{ $fromStop->id }}][{{ $toStop->id }}]" 
                                   class="form-control form-control-sm text-center" 
                                   placeholder="0.00" 
                                   step="0.01" 
                                   min="0">
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
