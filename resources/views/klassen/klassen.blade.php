@extends('layouts.app')

@section('content')
    <div>
        @include('klassen.create')

    <table class="table table-bordered mt-5">
        <thead>
        <tr>
            <th class="w-50">Klasse</th>
            <th class="w-25">Kürzel</th>
            <th colspan="2" class="w-25">Action</th>
        </tr>
        </thead>
        <tbody>
        @foreach($klassen as $klasse)
            <tr>
                <td>{{ $klasse->name }}</td>
                <td>{{ $klasse->kuerzel }}</td>
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
