@extends('layouts.app')

@push('styles')
<style>
    /* Responsive improvements for grading admin */
    @media (max-width: 767.98px) {
        .sticky-top {
            position: relative !important;
            top: auto !important;
        }

        .list-group-item {
            padding: 0.75rem 0.5rem;
        }

        .btn-group-sm > .btn {
            font-size: 0.75rem;
        }
    }

    @media (max-width: 575.98px) {
        .nav-tabs .nav-link {
            font-size: 0.85rem;
            padding: 0.5rem 0.75rem;
        }

        .card-header h5 {
            font-size: 1.1rem;
        }

        .badge {
            font-size: 0.7rem;
        }
    }

    /* Smooth transitions */
    .list-group-item {
        transition: all 0.2s ease;
    }

    .list-group-item:hover {
        background-color: #f8f9fa;
    }

    .collapse {
        transition: height 0.3s ease;
    }

    /* Better button spacing on mobile */
    @media (max-width: 767.98px) {
        .btn-group .btn {
            margin-bottom: 0.25rem;
        }
    }

    /* Drag handle cursor */
    .fa-grip-vertical {
        cursor: move;
        opacity: 0.5;
        transition: opacity 0.2s;
    }

    .fa-grip-vertical:hover {
        opacity: 1;
    }

    /* Card shadow on hover */
    .card.shadow-sm {
        transition: box-shadow 0.3s ease;
    }

    .card.shadow-sm:hover {
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-2 px-md-3">
    <!-- Header -->
    <div class="row mb-3 mb-md-4">
        <div class="col-12">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-2">
                <div>
                    <h2 class="h4 h3-md mb-1">Graduierungssysteme verwalten</h2>
                    <p class="text-muted small mb-0">Erstelle Systeme und Stufen. Lade optional ein Bild für jede Stufe hoch.</p>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    <ul class="mb-0 pl-3">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <!-- Sidebar: Neues System erstellen -->
        <div class="col-12 col-lg-4 col-xl-3 mb-4">
            <div class="sticky-top" style="top: 20px;">
                <div class="card shadow-sm mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-plus-circle"></i> Neues System
                    </div>
                    <div class="card-body">
                        <form method="post" action="{{ route('admin.grading.system.store') }}">
                            @csrf
                            <div class="form-group">
                                <label class="font-weight-bold small">Name</label>
                                <input name="name" class="form-control" required maxlength="100" placeholder="z.B. Lesestufen">
                            </div>
                            <div class="form-group mb-3">
                                <label class="font-weight-bold small">Slug <span class="text-muted font-weight-normal">(optional)</span></label>
                                <input name="slug" class="form-control" placeholder="z.B. lesestufen">
                            </div>
                            <button class="btn btn-primary btn-block">
                                <i class="fas fa-plus"></i> System anlegen
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card shadow-sm d-none d-lg-block">
                    <div class="card-header bg-info text-white">
                        <i class="fas fa-info-circle"></i> Hinweis
                    </div>
                    <div class="card-body small text-muted">
                        Klassen können pro System konfiguriert werden. Stufen haben eine Reihenfolge (Sort Order) und optional ein Symbol oder ein Bild.
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content: Systeme -->
        <div class="col-12 col-lg-8 col-xl-9">
            @if($systems->isEmpty())
                <div class="card shadow-sm">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-layer-group fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Noch keine Systeme vorhanden</h5>
                        <p class="text-muted small">Erstelle dein erstes Graduierungssystem über das Formular links.</p>
                    </div>
                </div>
            @endif

            @foreach($systems as $system)
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center">
                        <div class="mb-2 mb-md-0">
                            <h5 class="mb-1">
                                <i class="fas fa-layer-group text-primary"></i>
                                <strong>{{ $system->name }}</strong>
                            </h5>
                            @if($system->slug)
                                <span class="badge badge-secondary">{{ $system->slug }}</span>
                            @endif
                        </div>
                        <div>
                            <form method="post" action="{{ route('admin.grading.system.delete', ['system'=>$system->id]) }}" style="display:inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger" onclick="return confirm('System wirklich löschen? Alle Stufen werden ebenfalls gelöscht.')">
                                    <i class="fas fa-trash"></i> <span class="d-none d-md-inline">Löschen</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Tabs für Stufen und Fragen -->
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="stages-tab-{{ $system->id }}" data-toggle="tab"
                               href="#stages-{{ $system->id }}" role="tab">
                                <i class="fas fa-graduation-cap"></i> Stufen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="questions-tab-{{ $system->id }}" data-toggle="tab"
                               href="#questions-{{ $system->id }}" role="tab">
                                <i class="fas fa-question-circle"></i> <span class="d-none d-sm-inline">Fragen für </span>Dokumentation
                            </a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Stufen-Tab -->
                        <div class="tab-pane fade show active" id="stages-{{ $system->id }}" role="tabpanel">
                            <div class="row">
                                <div class="col-12 col-xl-7 mb-4 mb-xl-0">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0"><i class="fas fa-list"></i> Stufen</h6>
                                        <small class="text-muted"><i class="fas fa-arrows-alt"></i> Drag & Drop zum Sortieren</small>
                                    </div>

                                    @if($system->stages->isEmpty())
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> Noch keine Stufen vorhanden. Erstelle die erste Stufe rechts.
                                        </div>
                                    @else
                                        <div class="list-group mb-2" id="stages-list-{{ $system->id }}">
                                            @foreach($system->stages->sortBy('sort_order') as $stage)
                                                <div class="list-group-item" data-stage-id="{{ $stage->id }}" style="cursor: move;">
                                                    <div class="row align-items-center">
                                                        <div class="col-auto">
                                                            <i class="fas fa-grip-vertical text-muted"></i>
                                                        </div>
                                                        <div class="col-auto">
                                                            @if($stage->image_url)
                                                                <img src="{{ $stage->image_url }}" alt="{{ $stage->name }}"
                                                                     class="rounded"
                                                                     style="width:48px;height:48px;object-fit:cover;"
                                                                     id="preview-stage-{{ $stage->id }}">
                                                            @elseif($stage->symbol)
                                                                <div class="text-center" style="width:48px;">
                                                                    <i class="{{ $stage->symbol }} fa-2x text-primary" aria-hidden="true"></i>
                                                                </div>
                                                            @else
                                                                <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                                     style="width:48px;height:48px;">
                                                                    <i class="fas fa-image text-muted"></i>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div class="col">
                                                            <div><strong>{{ $stage->name }}</strong>
                                                                @if($stage->is_default)
                                                                    <span class="badge badge-success badge-sm">Standard</span>
                                                                @endif
                                                            </div>
                                                            <div class="small text-muted">
                                                                @if($stage->slug)
                                                                    <span class="mr-2"><i class="fas fa-tag"></i> {{ $stage->slug }}</span>
                                                                @endif
                                                                <span><i class="fas fa-sort-amount-down"></i> Order: {{ $stage->sort_order }}</span>
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-auto mt-2 mt-md-0">
                                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 w-md-auto"
                                                                    data-toggle="collapse"
                                                                    data-target="#edit-stage-{{ $stage->id }}">
                                                                <i class="fas fa-edit"></i> Bearbeiten
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Kollabiertes Bearbeitungsformular -->
                                                    <div class="collapse mt-3" id="edit-stage-{{ $stage->id }}">
                                                        <div class="border-top pt-3">
                                                            <form method="post" action="{{ route('admin.grading.stage.update', ['stage'=>$stage->id]) }}" enctype="multipart/form-data">
                                                                @csrf
                                                                <div class="row">
                                                                    <div class="col-md-6 mb-2">
                                                                        <label class="small font-weight-bold">Name</label>
                                                                        <input type="text" name="name" class="form-control form-control-sm"
                                                                               value="{{ $stage->name }}" required placeholder="Name">
                                                                    </div>
                                                                    <div class="col-md-6 mb-2">
                                                                        <label class="small font-weight-bold">Slug</label>
                                                                        <input type="text" name="slug" class="form-control form-control-sm"
                                                                               value="{{ $stage->slug }}" placeholder="Slug">
                                                                    </div>
                                                                    <div class="col-md-6 mb-2">
                                                                        <label class="small font-weight-bold">Symbol (CSS-Klasse)</label>
                                                                        <input type="text" name="symbol" class="form-control form-control-sm"
                                                                               value="{{ $stage->symbol }}" placeholder="z.B. fas fa-star">
                                                                    </div>
                                                                    <div class="col-md-3 mb-2">
                                                                        <label class="small font-weight-bold">Reihenfolge</label>
                                                                        <input type="number" name="sort_order" class="form-control form-control-sm"
                                                                               value="{{ $stage->sort_order }}">
                                                                    </div>
                                                                    <div class="col-md-3 mb-2">
                                                                        <label class="small font-weight-bold d-block">&nbsp;</label>
                                                                        <div class="custom-control custom-checkbox">
                                                                            <input type="hidden" name="is_default" value="0">
                                                                            <input type="checkbox" value="1" name="is_default"
                                                                                   class="custom-control-input"
                                                                                   id="is_default_{{ $stage->id }}"
                                                                                   {{ $stage->is_default ? 'checked' : '' }}>
                                                                            <label for="is_default_{{ $stage->id }}" class="custom-control-label small">
                                                                                Standard
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-md-8 mb-2">
                                                                        <label class="small font-weight-bold">Bild hochladen</label>
                                                                        <input type="file" name="image" accept="image/*"
                                                                               class="form-control-file form-control-sm"
                                                                               onchange="previewImage(event, 'preview-stage-{{ $stage->id }}', this)">
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <div class="btn-group btn-group-sm">
                                                                            <button class="btn btn-primary" type="submit">
                                                                                <i class="fas fa-save"></i> Speichern
                                                                            </button>
                                                                            <button type="button" class="btn btn-secondary"
                                                                                    data-toggle="collapse"
                                                                                    data-target="#edit-stage-{{ $stage->id }}">
                                                                                <i class="fas fa-times"></i> Abbrechen
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </form>
                                                            <form method="post" action="{{ route('admin.grading.stage.delete', ['stage'=>$stage->id]) }}"
                                                                  class="mt-2">
                                                                @csrf
                                                                <button class="btn btn-sm btn-outline-danger"
                                                                        onclick="return confirm('Stufe wirklich löschen?')">
                                                                    <i class="fas fa-trash"></i> Stufe löschen
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="col-12 col-xl-5">
                                    <div class="card bg-light">
                                        <div class="card-header bg-white">
                                            <h6 class="mb-0"><i class="fas fa-plus"></i> Neue Stufe</h6>
                                        </div>
                                        <div class="card-body">
                                            <form method="post" action="{{ route('admin.grading.stage.store', ['system'=>$system->id]) }}" enctype="multipart/form-data">
                                                @csrf
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Name</label>
                                                    <input name="name" class="form-control" required maxlength="100"
                                                           placeholder="z.B. Anfänger">
                                                </div>
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Slug <span class="text-muted font-weight-normal">(optional)</span></label>
                                                    <input name="slug" class="form-control" placeholder="z.B. anfaenger">
                                                </div>
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Symbol <span class="text-muted font-weight-normal">(FontAwesome-Klasse)</span></label>
                                                    <input name="symbol" class="form-control" placeholder="z.B. fas fa-star">
                                                    <small class="form-text text-muted">
                                                        <a href="https://fontawesome.com/icons" target="_blank">Icon-Übersicht <i class="fas fa-external-link-alt"></i></a>
                                                    </small>
                                                </div>
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Bild <span class="text-muted font-weight-normal">(optional)</span></label>
                                                    <input type="file" name="image" accept="image/*" class="form-control-file"
                                                           onchange="previewImage(event, 'preview-new-{{ $system->id }}', this)">
                                                    <div class="mt-2">
                                                        <img id="preview-new-{{ $system->id }}" src="" alt="Vorschau Bild"
                                                             class="rounded"
                                                             style="max-width:120px;max-height:80px;object-fit:cover;display:none">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Reihenfolge</label>
                                                    <input type="number" name="sort_order" class="form-control" value="0">
                                                </div>
                                                <div class="form-group mb-3">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="hidden" name="is_default" value="0">
                                                        <input type="checkbox" value="1" name="is_default"
                                                               class="custom-control-input"
                                                               id="default_{{ $system->id }}">
                                                        <label for="default_{{ $system->id }}" class="custom-control-label small">
                                                            Als Standardstufe markieren
                                                        </label>
                                                    </div>
                                                </div>
                                                <button class="btn btn-primary btn-block">
                                                    <i class="fas fa-plus"></i> Stufe anlegen
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Fragen-Tab -->
                        <div class="tab-pane fade" id="questions-{{ $system->id }}" role="tabpanel">
                            <div class="row">
                                <div class="col-12 col-xl-7 mb-4 mb-xl-0">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0"><i class="fas fa-question-circle"></i> Dokumentationsfragen</h6>
                                        <small class="text-muted"><i class="fas fa-arrows-alt"></i> Drag & Drop zum Sortieren</small>
                                    </div>

                                    @if($system->questions->isEmpty())
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> Noch keine Fragen vorhanden. Erstelle die erste Frage rechts.
                                        </div>
                                    @else
                                        <div class="list-group mb-2" id="questions-list-{{ $system->id }}">
                                            @foreach($system->questions->sortBy('sort_order') as $question)
                                                <div class="list-group-item" data-question-id="{{ $question->id }}" style="cursor: move;">
                                                    <div class="row align-items-center">
                                                        <div class="col-auto">
                                                            <i class="fas fa-grip-vertical text-muted"></i>
                                                        </div>
                                                        <div class="col">
                                                            <div class="mb-1">
                                                                <span class="badge badge-primary mr-2">{{ $loop->iteration }}</span>
                                                                <strong>{{ $question->question }}</strong>
                                                                @if(!$question->active)
                                                                    <span class="badge badge-warning ml-2">Inaktiv</span>
                                                                @else
                                                                    <span class="badge badge-success ml-2">Aktiv</span>
                                                                @endif
                                                            </div>
                                                            <div class="small text-muted">
                                                                <i class="fas fa-sort-amount-down"></i> Order: {{ $question->sort_order }}
                                                            </div>
                                                        </div>
                                                        <div class="col-12 col-md-auto mt-2 mt-md-0">
                                                            <button type="button" class="btn btn-sm btn-outline-primary w-100 w-md-auto"
                                                                    data-toggle="collapse"
                                                                    data-target="#edit-question-{{ $question->id }}">
                                                                <i class="fas fa-edit"></i> Bearbeiten
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <!-- Kollabiertes Bearbeitungsformular -->
                                                    <div class="collapse mt-3" id="edit-question-{{ $question->id }}">
                                                        <div class="border-top pt-3">
                                                            <form method="post" action="{{ route('admin.grading.question.update', ['question' => $question->id]) }}">
                                                                @csrf
                                                                <div class="row">
                                                                    <div class="col-md-8 mb-2">
                                                                        <label class="small font-weight-bold">Frage</label>
                                                                        <textarea name="question" class="form-control form-control-sm" rows="2" required>{{ $question->question }}</textarea>
                                                                    </div>
                                                                    <div class="col-md-4 mb-2">
                                                                        <label class="small font-weight-bold">Reihenfolge</label>
                                                                        <input type="number" name="sort_order" class="form-control form-control-sm"
                                                                               value="{{ $question->sort_order }}">
                                                                    </div>
                                                                    <div class="col-12 mb-2">
                                                                        <div class="custom-control custom-checkbox">
                                                                            <input type="hidden" name="active" value="0">
                                                                            <input type="checkbox" class="custom-control-input"
                                                                                   name="active" value="1"
                                                                                   id="active-{{ $question->id }}"
                                                                                   {{ $question->active ? 'checked' : '' }}>
                                                                            <label class="custom-control-label small font-weight-bold" for="active-{{ $question->id }}">
                                                                                Frage ist aktiv
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <div class="col-12">
                                                                        <div class="btn-group btn-group-sm">
                                                                            <button type="submit" class="btn btn-primary">
                                                                                <i class="fas fa-save"></i> Speichern
                                                                            </button>
                                                                            <button type="button" class="btn btn-secondary"
                                                                                    data-toggle="collapse"
                                                                                    data-target="#edit-question-{{ $question->id }}">
                                                                                <i class="fas fa-times"></i> Abbrechen
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </form>

                                                            <form method="post" action="{{ route('admin.grading.question.delete', ['question' => $question->id]) }}"
                                                                  class="mt-2">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                                        onclick="return confirm('Frage wirklich löschen?')">
                                                                    <i class="fas fa-trash"></i> Frage löschen
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="col-12 col-xl-5">
                                    <div class="card bg-light">
                                        <div class="card-header bg-white">
                                            <h6 class="mb-0"><i class="fas fa-plus"></i> Neue Frage</h6>
                                        </div>
                                        <div class="card-body">
                                            <form method="post" action="{{ route('admin.grading.question.store', ['system' => $system->id]) }}">
                                                @csrf
                                                <div class="form-group">
                                                    <label class="small font-weight-bold">Frage</label>
                                                    <textarea name="question" class="form-control" rows="3" required
                                                              placeholder="Z.B.: Wie gut kannst du lesen?"></textarea>
                                                    <small class="form-text text-muted">
                                                        Diese Frage wird in der Dokumentation für Selbst- und Lehrereinschätzungen verwendet.
                                                    </small>
                                                </div>
                                                <div class="form-group mb-3">
                                                    <label class="small font-weight-bold">Reihenfolge</label>
                                                    <input type="number" name="sort_order" class="form-control" value="0">
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-block">
                                                    <i class="fas fa-plus"></i> Frage anlegen
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    <div class="alert alert-info mt-3">
                                        <div class="d-flex">
                                            <div class="mr-2">
                                                <i class="fas fa-info-circle fa-lg"></i>
                                            </div>
                                            <div>
                                                <strong class="d-block mb-1">Hinweis</strong>
                                                <small>
                                                    Fragen werden in der pädagogischen Dokumentation verwendet, um strukturierte
                                                    Einschätzungen von Schülern und Lehrern zu erfassen. Die Reihenfolge bestimmt
                                                    die Darstellung in der Dokumentation.
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
    function previewImage(event, previewId, inputEl){
        const img = document.getElementById(previewId);
        if (!img) return;
        const file = inputEl.files && inputEl.files[0];
        if (!file){ img.style.display = 'none'; img.src = ''; return; }
        img.src = URL.createObjectURL(file);
        img.style.display = '';
    }

    document.addEventListener('DOMContentLoaded', function(){
        // Initialize Sortable for each system list
        document.querySelectorAll('[id^="stages-list-"]').forEach(function(listEl){
            const systemId = listEl.id.replace('stages-list-','');
            new Sortable(listEl, {
                animation: 150,
                handle: '.fa-grip-vertical',
                onEnd: function(evt){
                    const order = Array.from(listEl.querySelectorAll('[data-stage-id]')).map(function(item){
                        return item.getAttribute('data-stage-id');
                    });

                    fetch(`/admin/grading/system/${systemId}/stages/order`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ order })
                    }).then(function(res){
                        if (!res.ok) throw res;
                        return res.json();
                    }).then(function(json){
                        if (json.status === 'ok') {
                            // small visual feedback
                            listEl.style.opacity = '0.6';
                            setTimeout(function(){ listEl.style.opacity = ''; }, 300);
                        } else {
                            alert('Fehler beim Speichern der Reihenfolge');
                        }
                    }).catch(function(){
                        alert('Fehler beim Speichern der Reihenfolge');
                    });
                }
            });
        });

        // Initialize Sortable for questions list
        document.querySelectorAll('[id^="questions-list-"]').forEach(function(listEl){
            const systemId = listEl.id.replace('questions-list-','');
            new Sortable(listEl, {
                animation: 150,
                handle: '.fa-grip-vertical',
                onEnd: function(evt){
                    const order = Array.from(listEl.querySelectorAll('[data-question-id]')).map(function(item){
                        return item.getAttribute('data-question-id');
                    });

                    fetch(`/admin/grading/system/${systemId}/questions/order`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({ order })
                    }).then(function(res){
                        if (!res.ok) throw res;
                        return res.json();
                    }).then(function(json){
                        if (json.status === 'ok') {
                            // small visual feedback
                            listEl.style.opacity = '0.6';
                            setTimeout(function(){ listEl.style.opacity = ''; }, 300);
                        } else {
                            alert('Fehler beim Speichern der Reihenfolge');
                        }
                    }).catch(function(){
                        alert('Fehler beim Speichern der Reihenfolge');
                    });
                }
            });
        });
    });
</script>
@endpush
