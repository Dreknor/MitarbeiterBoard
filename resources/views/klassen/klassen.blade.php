@extends('layouts.app')

@section('content')
    <div>
        @include('klassen.create')

        <div class="mt-3 mb-3">
            <a href="{{route('schueler.import.form')}}" class="btn btn-outline-primary btn-sm">Schüler Import</a>
        </div>

    <table class="table table-bordered mt-2">
        <thead>
        <tr>
            <th class="w-50">Klasse</th>
            <th class="w-25">Kürzel</th>
            <th class="w-25">VP-Status</th>
            <th colspan="2" class="w-25">Action</th>
        </tr>
        </thead>
        <tbody>
        @foreach($klassen as $klasse)
            <tr style="background-color: {{$klasse->color}}">
                <td>{{ $klasse->name }}</td>
                <td>{{ $klasse->kuerzel }}</td>
                <td>
                    @if($klasse->show_vertretungen)
                        <span class="badge badge-success" title="Vertretungen öffentlich sichtbar">
                            <i class="fa fa-eye"></i> VP
                        </span>
                    @else
                        <span class="badge badge-secondary" title="Nur Raumbuchungen, kein öffentlicher VP">
                            <i class="fa fa-eye-slash"></i> nur Räume
                        </span>
                    @endif
                </td>
                <td>
                    <a href="{{url('klassen/'.$klasse->id.'/edit')}}" class="btn btn-primary btn-sm">edit</a>
                </td>
                <td>
                    <div class="d-none d-md-flex">
                    <form action="{{url('klassen/'.$klasse->id)}}" method="post">
                        @csrf
                        @method('delete')
                        <button id="" class="btn btn-danger btn-sm">löschen</button>
                    </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection

@push('css')
    <link rel="stylesheet" href="{{ asset('css/own.css') }}">
@endpush
