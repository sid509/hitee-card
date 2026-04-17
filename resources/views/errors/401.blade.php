@extends('errors.layout', ['image' => 'page-misc-error-light.png'])

@section('title', 'Unauthorized')
@section('code', '401')
@section('headline', 'Unauthorized 🔐')
@section('message', 'Please log in to access this page.')
