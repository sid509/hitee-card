@extends('layouts.app')

@section('title', 'Error 403')

@section('content')
<div class="container-xxl container-p-y">
    <div class="misc-wrapper">
        <h1 class="mb-2 mx-2" style="line-height: 6rem; font-size: 6rem;">403</h1>
        <h4 class="mb-2 mx-2">Forbidden ⛔️</h4>
        <p class="mb-6 mx-2">You don't have permission to access this page.</p>
        <a href="{{ url()->previous() }}" class="btn btn-primary">Back to previous page</a>
        <div class="mt-6">
            <img
                src="{{ asset('assets/img/illustrations/page-misc-error-light.png') }}"
                alt="page-misc-error-light"
                width="500"
                class="img-fluid"
                data-app-light-img="illustrations/page-misc-error-light.png"
                data-app-dark-img="illustrations/page-misc-error-dark.png"
            />
        </div>
    </div>
</div>
@endsection
