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
                            {{$procedure->description}}
                        </small>
                    </p>
                @endif
            </div>
            @if($procedure->started_at == null)
                @if($canEdit ?? false)
                    <div class="card-body">
                        <form action="{{url('procedure/'.$procedure->id.'/start')}}" method="post" class="form-horizontal">
                            @csrf
                            <div class="form-row">
                                <label for="name">
                                    Bezeichnung des Prozesses:
                                </label>
                                <input type="text" name="name" id="name" placeholder="Bezeichnung für Prozess eingeben" class="form-control" required>
                            </div>
                            <div class="form-row">
                                <label for="started_at">
                                    Prozess startet am:
                                </label>
                                <input type="date" required name="started_at" id="started_at" value="{{\Carbon\Carbon::now()->format('Y-m-d')}}" class="form-control">
                            </div>

                            <button type="submit" class="btn btn-success">
                                    starten
                                </button>
                        </form>
                    </div>
                @else
                    <div class="card-body">
                        <p class="text-muted">Dieser Prozess wurde noch nicht gestartet.</p>
                    </div>
                @endif
            @else
                <div class="card-body border-top">
                    <div class="container-fluid">
                        <ul class="list-group">
                                @foreach($procedure->steps->where('parent', null) as $step)
                                    @include('procedure.stepStarted', ['step' => $step, 'canEdit' => $canEdit ?? false])
                                @endforeach
                        </ul>
                        @if($canEdit ?? false)
                            <div class="mt-3">
                                <div class="btn btn-sm btn-outline-success newStep" data-parent="" data-target="#stepModal" data-toggle="modal">
                                    <i class="fas fa-plus" data-parent=""></i> Neuer Schritt
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>
@endsection

@push('js')
    <script>
        $('.newStep').on('click', function (e){
            var parentId = $(this).data('parent');
            if (!parentId) {
                parentId = $(e.target).closest('.newStep').data('parent');
            }
            $('#parent').val(parentId);
            console.log(parentId);
        });
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
                        @if($procedure->started_at != null)
                        <div class="form-row mt-2">
                            <div class="col-md-6 col-sm-12">
                                <label for="endDate">
                                    Fälligkeitsdatum
                                </label>
                                <input type="date" class="form-control" name="endDate" value="{{old('endDate')}}">
                                <small class="form-text text-muted">Das konkrete Fälligkeitsdatum für diesen Schritt im laufenden Prozess.</small>
                            </div>
                        </div>
                        @endif
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success" form="stepForm">Speichern</button>
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal" tabindex="-1" role="dialog" id="addUserModal">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Neue zuständige Person</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="{{url('procedure/step/addUser')}}" method="post" class="form-horizontal">
                        <input type="hidden" name="step" value="" id="step">
                        @csrf
                        <div class="form-row">
                            <select name="person_id" class="custom-select mt-2">
                                <option></option>
                                @foreach($users as $user)
                                    <option value="{{$user->id}}">
                                        {{$user->name}}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-success">
                                <i class="far fa-save"> speichern</i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endpush

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

        $(document).on('click', '.addUser', function (e){
            // Verwende die data-Attribute des Buttons selbst; falls das eigentliche Ziel ein Kind-Element ist,
            // lese es von der nächsthöheren .addUser-Element.
            var stepId = $(this).data('step');
            if (!stepId) {
                stepId = $(e.target).closest('.addUser').data('step');
            }
            $('#step').val(stepId);
        });

        // Reset hidden input when modal closes to avoid stale values
        $(document).on('hidden.bs.modal', '#addUserModal', function () {
            $('#step').val('');
            // move focus back to the visible back button for accessibility
            $('.btn-info').first().focus();
        });
    </script>
@endpush
