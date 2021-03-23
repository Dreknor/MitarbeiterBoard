@extends('layouts.app')

@section('content')
    <div>
        @include('klassen.create')

    <table class="table table-bordered mt-5">
        <thead>
        <tr>
            <th>Klasse</th>
            <th width="150px">Action</th>
        </tr>
        </thead>
        <tbody>
        @foreach($klassen as $klasse)
            <tr>
                <td>{{ $klasse->name }}</td>
                <td>
                    <form action="{{url('klassen/'.$klasse->id)}}" method="post">
                        @csrf
                        @method('delete')
                        <button id="" class="btn btn-danger btn-sm">löschen</button>
                    </form>

                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
