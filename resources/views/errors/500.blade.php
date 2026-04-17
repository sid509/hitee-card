@extends('errors.layout', ['image' => 'page-misc-error-light.png'])

@section('title', 'Server Error')
@section('code', '500')
@section('headline', 'Internal Server Error 🛠️')
@section('message', 'Something went wrong on our end. We are working to fix it.')
