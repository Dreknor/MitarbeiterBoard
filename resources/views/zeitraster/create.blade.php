@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-10 mx-auto">

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Neues Zeitraster anlegen</h5>
                </div>
                <div class="card-body">

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('zeitraster.store') }}" method="post">
                        @csrf

                        @include('zeitraster._form', ['zeitraster' => null])

                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary">Speichern</button>
                            <a href="{{ route('zeitraster.index') }}" class="btn btn-outline-secondary ml-2">Abbrechen</a>
                        </div>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>
@endsection

