@extends('errors.layout', ['image' => 'page-misc-error-light.png'])

@section('title', 'Access Denied')
@section('code', '403')
@section('headline', 'Forbidden 🚫')
@section('message', 'You do not have permission to access this resource.')
