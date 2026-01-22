@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <a href="{{url('procedure')}}" class="btn btn-info">zurück</a>
        <div class="card">
            <div class="card-header">
                @if($procedure->started_at != null && ($canEdit ?? false))
                    {{-- Bearbeitbarer Titel für gestartete Prozesse --}}
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1" id="procedure-header-display">
                            <h6>
                                {{$procedure->category->name}}: <span id="procedure-name-display">{{$procedure->name}}</span>
                                <a href="#" class="ml-2 text-primary" id="edit-procedure-btn" title="Prozess bearbeiten">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </h6>
                            <p>
                                <small id="procedure-description-display">
                                    {!! $procedure->description !!}
                                </small>
                            </p>
                        </div>
                    </div>

                    {{-- Bearbeitungsformular (initial versteckt) --}}
                    <div id="procedure-edit-form" style="display: none;">
                        <form action="{{url('procedure/'.$procedure->id.'/update')}}" method="post">
                            @csrf
                            @method('PUT')
                            <div class="form-group">
                                <label for="edit-name">Prozessname:</label>
                                <input type="text" class="form-control" id="edit-name" name="name" value="{{$procedure->name}}" required>
                            </div>
                            <div class="form-group">
                                <label for="edit-description">Beschreibung:</label>
                                <textarea class="form-control" id="edit-description" name="description" rows="3">{{$procedure->description}}</textarea>
                            </div>
                            <div class="form-group mb-0">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-save"></i> Speichern
                                </button>
                                <button type="button" class="btn btn-secondary btn-sm" id="cancel-edit-btn">
                                    <i class="fas fa-times"></i> Abbrechen
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    {{-- Normaler Titel für Templates oder Nutzer ohne Bearbeitungsrechte --}}
                    <h6>
                        {{$procedure->category->name}}: {{$procedure->name}}
                    </h6>
                    <p>
                        <small>
                            {!! $procedure->description !!}
                        </small>
                    </p>
                @endif
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
                                            @if($canEdit ?? false)
                                                <div class="pull-right">
                                                    <small>
                                                        <a href="{{url('procedure/step/'.$step->id."/edit")}}">
                                                            <i class="fas fa-pen"></i>
                                                        </a>
                                                    </small>
                                                </div>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            @if($canEdit ?? false)
                                <div class="btn btn-sm btn-outline-success newStep" data-parent=""  data-target="#stepModal"  data-toggle="modal">
                                    <i class="fas fa-plus" data-parent=""></i> Schritt erstellen
                                </div>
                            @endif
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        // Procedure Name/Description Edit Toggle
        $(document).ready(function() {
            $('#edit-procedure-btn').on('click', function(e) {
                e.preventDefault();
                $('#procedure-header-display').hide();
                $('#procedure-edit-form').show();
                $('#edit-name').focus();
            });

            $('#cancel-edit-btn').on('click', function() {
                $('#procedure-edit-form').hide();
                $('#procedure-header-display').show();
            });
        });

        $('.newStep').on('click', function (e){
            $('#parent').val(e.target.dataset.parent);
            console.log(e.target.dataset.parent)
        })
    </script>
@endpush

@push('modals')
    @if($canEdit ?? false)
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
    @endif
@endpush
