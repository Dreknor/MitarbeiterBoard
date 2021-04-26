@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <a href="{{url(request()->segment(1).'/wochenplan')}}" class="btn btn-primary">zurück</a>

        <div class="card">
            <div class="card-header">
                <h6>
                    neuer Wochenplan
                </h6>
            </div>
           <div class="card-body">
               <form action="{{url(request()->segment(1).'/wochenplan')}}" method="post" class="form-horizontal">
                   @csrf
                   <div class="form-row">
                       <label for="name">
                           Bezeichnung
                       </label>
                       <input type="text" name="name" id="name" class="form-control"  value="{{old('name')}}" required>
                   </div>
                   <div class="form-row">
                       <label for="bewertung">
                           Selbsteinschätzung?
                       </label>
                       <select name="bewertung" class="form-control custom-select">
                           <option value="0">Keine Selbsteinschätzung</option>
                           <option value="1">Smilie</option>
                           <option value="2">Skala</option>
                       </select>
                   </div>
                   <div class="form-row">
                       <div class="col-md-6 col-sm-12">
                           <label for="gueltig_ab">
                               gueltig ab
                           </label>
                           <input type="date" name="gueltig_ab" id="gueltig_ab" class="form-control"  value="{{old('gueltig_ab')}}" required>
                       </div>
                       <div class="col-md-6 col-sm-12">
                           <label for="gueltig_bis">
                               gueltig bis
                           </label>
                           <input type="date" name="gueltig_bis" id="gueltig_bis" class="form-control" value="{{old('gueltig_bis')}}" required>
                       </div>
                   </div>
                   <div class="form-row">
                       <div class="form-group">
                           <label>Für welche Klassen?</label>
                           <br>
                           @foreach($klassen as $klasse)
                                   <div>
                                       <input type="checkbox" name="klassen[]" value="{{$klasse->id}}" id="checkbox{{$klasse->id}}"/>
                                       <label for="checkbox{{$klasse->id}}" id="labelCheck{{$klasse->id}}">{{$klasse->name}}</label>
                                   </div>
                           @endforeach
                       </div>
                   </div>
                   <div class="form-row">
                       <button type="submit" class="btn btn-success btn-block">
                           Wochenplan erstellen
                       </button>
                   </div>
               </form>
           </div>
        </div>

    </div>

@endsection
