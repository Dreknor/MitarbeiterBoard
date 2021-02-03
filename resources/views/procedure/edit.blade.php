@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <a href="{{url('procedure')}}" class="btn btn-info">zurück</a>
        <div class="card">
            <div class="card-header">
                <h6>
                    {{$procedure->category->name}}: {{$procedure->name}}
                </h6>
                <p>
                    <small>
                        {{$procedure->description}}
                    </small>
                </p>
            </div>
            <div class="card-body border-top">
                <div class="container-fluid">
                    <div class="row">
                        @if(count($procedure->steps->where('parent', null))>0)
                            @each('procedure.step',$procedure->steps->where('parent', null), 'step')
                        @elseif(count($procedure->steps)>0)
                            <div class="col-12">
                                <p class="p-2 bg-warning">
                                    Es kann kein Start-Schritt gefunden werden. Startschritte dürfen keinen Vorgängerschritt haben.
                                </p>
                                <ul class="list-group">
                                    @foreach($procedure->steps as $step)
                                        <li class="list-group-item">
                                            {{$step->name}}
                                            <div class="pull-right">
                                                <small>
                                                    <a href="{{url('procedure/step/'.$step->id."/edit")}}">
                                                        <i class="fas fa-pen"></i>
                                                    </a>
                                                </small>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <div class="btn btn-sm btn-outline-success newStep" data-parent=""  data-target="#stepModal"  data-toggle="modal">
                                <i class="fas fa-plus" data-parent=""></i> Schritt erstellen
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        $('.newStep').on('click', function (e){
            $('#parent').val(e.target.dataset.parent);
            console.log(e.target.dataset.parent)
        })
    </script>
@endpush

@push('modals')
    <div class="modal" tabindex="-1" role="dialog" id="stepModal">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Neuer Schritt</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">

                    <form action="{{url('procedure/'.$procedure->id.'/step')}}" method="post" class="form-horizontal" id="stepForm">
                        @csrf
                        <input type="hidden" name="parent" id="parent" value="{{old('parent')}}">
                        <div class="form-row">
                            <div class="col-12">
                                <label for="name">
                                    Bezeichnung des Schrittes
                                </label>
                                <input  id="name" name="name" type="text" class="form-control" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <label for="description">
                                Beschreibung
                            </label>
                            <textarea name="description" id="description" rows="6" class="form-control">
                                {{old('description')}}
                            </textarea>
                        </div>
                        <div class="form-row">
                            <div class="col-md-8 col-sm-12">
                                <label for="position_id">
                                    Verantwortliche Position
                                </label>
                                <select name="position_id" class="custom-select" required>
                                    <option disabled selected> </option>
                                    @foreach($positions as $position)
                                        <option value="{{$position->id}}">
                                            {{$position->name}}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4 col-sm-12">
                                <label for="durationDays">
                                    Dauer in Tagen
                                </label>
                                <input type="number" class="form-control" required min="1" step="1" name="durationDays" value="{{old('durationDays')}}">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" form="stepForm">Speichern</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endpush
