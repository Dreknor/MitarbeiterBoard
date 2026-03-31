@extends('layouts.app')
@push('css') @vite('resources/css/personal.css') @endpush
@section('content')
<div class="personal-wrapper">
    @include('personal.orgchart.positions.create')
</div>
@endsection

