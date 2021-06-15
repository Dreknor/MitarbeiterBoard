@extends('layouts.app')
@section('content')
    <div class="card">
        <div class="card-header">
            <div class="container-fluid">
                <h5>
                    {{$item->name}}
                </h5>
                <small>
                    {{$item->descripton}}
                </small>
            </div>
        </div>
    </div>
@endsection
