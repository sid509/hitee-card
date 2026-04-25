@extends('layouts.app')

@section('title', 'System Logs')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">Administration /</span> Laravel Logs
</h4>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <h5 class="mb-0">Logs: <span class="text-primary">{{ $selectedFileName }}</span></h5>
        
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <form action="{{ route('logs.index') }}" method="GET" class="d-flex gap-2 align-items-center">
                <label class="small fw-bold text-muted text-nowrap">File:</label>
                <select name="file" class="form-select form-select-sm" onchange="this.form.submit()">
                    @foreach($logFiles as $file)
                        <option value="{{ $file['name'] }}" {{ $selectedFileName == $file['name'] ? 'selected' : '' }}>
                            {{ $file['name'] }} ({{ number_format($file['size'] / 1024, 2) }} KB)
                        </option>
                    @endforeach
                </select>
                
                <label class="small fw-bold text-muted text-nowrap ms-2">Lines:</label>
                <select name="lines" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="50" {{ $lines == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ $lines == 100 ? 'selected' : '' }}>100</option>
                    <option value="500" {{ $lines == 500 ? 'selected' : '' }}>500</option>
                    <option value="1000" {{ $lines == 1000 ? 'selected' : '' }}>1000</option>
                    <option value="2000" {{ $lines == 2000 ? 'selected' : '' }}>2000</option>
                </select>
            </form>

            <form action="{{ route('logs.clear') }}" method="POST" onsubmit="return confirm('Are you sure you want to clear this log file?')">
                @csrf
                <input type="hidden" name="file" value="{{ $selectedFileName }}">
                <button type="submit" class="btn btn-danger btn-sm">
                    <i class="bx bx-trash me-1"></i> Clear
                </button>
            </form>
            
            <a href="{{ route('logs.index', ['file' => $selectedFileName, 'lines' => $lines]) }}" class="btn btn-primary btn-sm">
                <i class="bx bx-refresh me-1"></i> Refresh
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="bg-dark p-3 rounded" style="max-height: 700px; overflow-y: auto;">
            <pre class="text-white mb-0" style="white-space: pre-wrap; font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Consolas', monospace; font-size: 0.85rem;">{{ $logContent ?: 'No log entries found in ' . $selectedFileName }}</pre>
        </div>
    </div>
</div>
@endsection
