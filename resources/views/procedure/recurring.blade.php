@extends('layouts.app')

@section('content')

    <div class="container-fluid">
        <div class="card">
            @include('procedure.parts.nav')
            <div class="card-body border-top">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <table class="table table-striped">
                                <thead>
                                <tr>
                                    <th>
                                        Name
                                    </th>
                                    <th>
                                        Kategorie
                                    </th>
                                    <th>
                                        Startet
                                    </th>
                                    <th>
                                        Aktionen
                                    </th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($procedures as $procedure)
                                    <tr>
                                        <td>
                                            {{$procedure->name}}
                                        </td>
                                        <td>
                                            {{$procedure->procedure->category->name}}
                                        </td>
                                        <td>
                                            @if($procedure->faelligkeit_typ == 'datum')
                                                jeweils am 1. {{$monate[$procedure->month]}}
                                            @elseif($procedure->faelligkeit_typ == 'vor_ferien')
                                                {{$procedure->wochen}} Wochen vor den {{$procedure->ferien}}
                                            @elseif($procedure->faelligkeit_typ == 'nach_ferien')
                                                {{$procedure->wochen}} Wochen nach den {{$procedure->ferien}}
                                            @endif
                                        </td>
                                        <td>
                                            <div class="row">
                                                <div class="col-auto">
                                                    <a href="{{url('procedure/recurring/'.$procedure->id.'/start/true')}}" class="btn btn-info btn-sm">
                                                        <i class="fa fa-play"></i>
                                                        starten
                                                    </a>
                                                </div>
                                                <div class="col-auto">
                                                    <form method="post" action="{{url('procedure/recurring/'.$procedure->id)}}">
                                                        @csrf
                                                        @method('delete')
                                                        <button type="submit" class="btn btn-danger btn-sm">
                                                            <i class="fa fa-trash"></i>
                                                            löschen
                                                        </button>
                                                    </form>
                                                </div>

                                            </div>

                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>

                        </div>
                    </div>
                </div>
            </div>
            <div class="card-body border-top" id="">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <h6>
                                Wiederkehrenden Prozess anlegen
                            </h6>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <p>
                                Hier können Sie einen wiederkehrenden Prozess anlegen. Dieser wird dann automatisch zu den gewünschten Zeitpunkten erstellt.
                                Anzugeben ist der Name für diesen Wiederkehrenden Prozess, der Prozess und wann er gestartet werden soll.

                                Die Fälligkeit (der Zeitpunkt des Start) kann entweder ein Datum sein, oder eine Anzahl von Wochen vor oder nach den Ferien.
                                Bitte also angeben, ob der Prozess am 1. welchen Monats starten soll, oder eine Anzahl von Wochen vor oder nach den Ferien.
                            </p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <form method="post" action="{{url('procedure/recurring')}}">
                                @csrf
                                <div class="form-group
                                    @if($errors->has('name'))
                                        has-error
                                    @endif">
                                        <label for="name" class="text-danger">
                                            Name des Prozesses
                                        </label>
                                        <input type="text" name="name" class="form-control" value="{{old('name')}}" required>
                                        @if($errors->has('name'))
                                            <span class="help-block
                                            @if($errors->has('name'))
                                                has-error
                                            @endif">
                                                {{$errors->first('name')}}
                                            </span>
                                        @endif
                                </div>
                                <div class="form-group
                                    @if($errors->has('procedure_id'))
                                        has-error
                                    @endif">
                                    <label for="procedure_id"  class="text-danger">
                                        Prozess
                                    </label>
                                    <select name="procedure_id" class="form-control" required>
                                        <option value="" disabled selected>
                                        </option>
                                        @foreach($templates as $template)
                                            <option value="{{$template->id}}">
                                                {{$template->name}}
                                            </option>
                                        @endforeach
                                    </select>
                                    @if($errors->has('procedure_id'))
                                        <span class="help-block
                                        @if($errors->has('procedure_id'))
                                            has-error
                                        @endif">
                                            {{$errors->first('procedure_id')}}
                                        </span>
                                    @endif
                                </div>
                                <div class="form-group
                                    @if($errors->has('faelligkeit_typ'))
                                        has-error
                                    @endif">
                                    <label for="faelligkeit_typ" class="text-danger">
                                        Fälligkeit
                                    </label>
                                    <select name="faelligkeit_typ" class="form-control" required>
                                        <option value="" disabled selected>
                                        </option>
                                        <option value="datum">
                                            Datum
                                        </option>
                                        <option value="vor_ferien">
                                            Vor Ferien
                                        </option>
                                        <option value="nach_ferien">
                                            Nach Ferien
                                        </option>
                                    </select>
                                    @if($errors->has('faelligkeit_typ'))
                                        <span class="help-block
                                        @if($errors->has('faelligkeit_typ'))
                                            has-error
                                        @endif">
                                            {{$errors->first('faelligkeit_typ')}}
                                        </span>
                                    @endif
                                </div>
                                <div class="form-group
                                    @if($errors->has('month'))
                                        has-error
                                    @endif">
                                    <label for="month">
                                        Monat
                                    </label>
                                    <select name="month" class="form-control">
                                        <option value="" disabled selected>
                                        </option>
                                        @foreach($monate as $key => $monat)
                                            <option value="{{$key}}">
                                                {{$monat}}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group
                                    @if($errors->has('wochen'))
                                        has-error
                                    @endif">
                                    <label for="wochen">
                                        Wochen
                                    </label>
                                    <input type="number" name="wochen" class="form-control" value="{{old('wochen')}}" min="1" max="52">
                                    @if($errors->has('wochen'))
                                        <span class="help-block
                                        @if($errors->has('wochen'))
                                            has-error
                                        @endif">
                                            {{$errors->first('wochen')}}
                                        </span>
                                    @endif
                                </div>
                                <div class="form-group
                                    @if($errors->has('ferien'))
                                        has-error
                                    @endif">
                                    <label for="ferien">
                                        Ferien
                                    </label>
                                    <select name="ferien" class="form-control">
                                        <option value="" disabled selected>
                                            </option>
                                        <option value="Winterferien">
                                            Winterferien
                                        </option>
                                        <option value="Osterferien">
                                            Osterferien
                                        </option>
                                        <option value="Sommerferien">
                                            Sommerferien
                                        </option>
                                        <option value="Herbstferien">
                                            Herbstferien
                                        </option>
                                        <option value="Weihnachtsferien">
                                            Weihnachtsferien
                                        </option>
                                    </select>
                                    @if($errors->has('ferien'))
                                        <span class="help-block
                                        @if($errors->has('ferien'))
                                            has-error
                                        @endif">
                                            {{$errors->first('ferien')}}
                                        </span>
                                    @endif
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    anlegen
                                </button>
                            </form>
                </div>

            </div>
        </div>
    </div>

@endsection

