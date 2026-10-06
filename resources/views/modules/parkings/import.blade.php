@extends('layouts.app')

@section('title', 'Import Parking Lots')

@section('content')
<h4 class="py-3 mb-4">
    <span class="text-muted fw-light">{{ __('messages.fleet_management') }} /</span>
    <a href="{{ route('parkings.index') }}" class="text-muted fw-light">{{ __('messages.parkings') }} /</a>
    Import
</h4>

<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <i class="bx bx-import me-2 text-primary"></i>
                <h5 class="mb-0">Import Parking Lots</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-4">
                    Upload a parking-lots JSON export. Each entry is matched by its source
                    <code>id</code>, so re-importing the same file updates existing lots instead
                    of duplicating them. All lots are assigned to
                    <strong>{{ $merchant->name }}</strong>.
                </p>

                <form action="{{ route('parkings.import.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="file">JSON Export File</label>
                        <div class="input-group input-group-merge">
                            <span class="input-group-text"><i class="bx bx-file"></i></span>
                            <input type="file" class="form-control @error('file') is-invalid @enderror"
                                id="file" name="file" accept=".json,application/json" required />
                        </div>
                        @error('file') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="with_images" name="with_images"
                            value="1" {{ old('with_images', true) ? 'checked' : '' }}>
                        <label class="form-check-label" for="with_images">
                            Download pictures — first image becomes the featured photo, the rest go to the gallery
                        </label>
                        <div class="form-text">Large exports take a while; leave checked unless you're retrying a failed run.</div>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-upload me-1"></i> Run Import
                        </button>
                        <a href="{{ route('parkings.index') }}" class="btn btn-label-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header d-flex align-items-center">
                <i class="bx bx-info-circle me-2 text-muted"></i>
                <h6 class="mb-0">What gets imported</h6>
            </div>
            <div class="card-body pt-3">
                <ul class="list-unstyled mb-0 small text-muted">
                    <li class="mb-2"><i class="bx bx-check text-success me-2"></i>Name, location and map coordinates</li>
                    <li class="mb-2"><i class="bx bx-check text-success me-2"></i>Vehicle charges as fee tiers (bike, car, EV, monthly)</li>
                    <li class="mb-2"><i class="bx bx-check text-success me-2"></i>Facilities mapped to parking attributes</li>
                    <li class="mb-2"><i class="bx bx-check text-success me-2"></i>Owner name and phone from the export</li>
                    <li><i class="bx bx-check text-success me-2"></i>Pictures — first becomes the featured image</li>
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
