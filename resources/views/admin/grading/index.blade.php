@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12 mb-3">
            <h4>Graduierungssysteme verwalten</h4>
            <p class="text-muted small">Erstelle Systeme und Stufen. Lade optional ein Bild für jede Stufe hoch.</p>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </div>

    <div class="row">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">Neues System</div>
                <div class="card-body">
                    <form method="post" action="{{ route('admin.grading.system.store') }}">
                        @csrf
                        <div class="form-group">
                            <label>Name</label>
                            <input name="name" class="form-control" required maxlength="100">
                        </div>
                        <div class="form-group">
                            <label>Slug (optional)</label>
                            <input name="slug" class="form-control">
                        </div>
                        <button class="btn btn-primary">Anlegen</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Hinweis</div>
                <div class="card-body small text-muted">
                    Klassen können pro System konfiguriert werden. Stufen haben eine Reihenfolge (Sort Order) und optional ein Symbol oder ein Bild.
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @foreach($systems as $system)
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div><strong>{{ $system->name }}</strong> <span class="text-muted small">{{ $system->slug }}</span></div>
                    <div>
                        <form method="post" action="{{ route('admin.grading.system.delete', ['system'=>$system->id]) }}" style="display:inline">
                            @csrf
                            <button class="btn btn-sm btn-danger" onclick="return confirm('System wirklich löschen? Alle Stufen werden ebenfalls gelöscht.')">Löschen</button>
                        </form>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Tabs für Stufen und Fragen -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="stages-tab-{{ $system->id }}" data-toggle="tab"
                               href="#stages-{{ $system->id }}" role="tab">Stufen</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="questions-tab-{{ $system->id }}" data-toggle="tab"
                               href="#questions-{{ $system->id }}" role="tab">Fragen für Dokumentation</a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Stufen-Tab -->
                        <div class="tab-pane fade show active" id="stages-{{ $system->id }}" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="small mb-2">Stufen</h6>
                                    <div class="list-group mb-2" id="stages-list-{{ $system->id }}">
                                        @foreach($system->stages->sortBy('sort_order') as $stage)
                                            <div class="list-group-item d-flex align-items-center" data-stage-id="{{ $stage->id }}" draggable="true">
                                                <div class="mr-3">
                                                    @if($stage->image_url)
                                                        <img src="{{ $stage->image_url }}" alt="{{ $stage->name }}" style="width:48px;height:48px;object-fit:cover;border-radius:6px;" id="preview-stage-{{ $stage->id }}">
                                                    @elseif($stage->symbol)
                                                        <i class="{{ $stage->symbol }} fa-2x" aria-hidden="true"></i>
                                                    @else
                                                        <div style="width:48px;height:48px;background:#f0f0f0;border-radius:6px"></div>
                                                    @endif
                                                </div>
                                                <div class="flex-grow-1">
                                                    <div><strong>{{ $stage->name }}</strong> <span class="text-muted small">(order: {{ $stage->sort_order }})</span></div>
                                                    <div class="small text-muted">slug: {{ $stage->slug }}</div>
                                                </div>
                                                <div class="ml-3">
                                                    <!-- Vollständiges Edit-Formular für die Stufe -->
                                                    <form method="post" action="{{ route('admin.grading.stage.update', ['stage'=>$stage->id]) }}" enctype="multipart/form-data" class="form-inline">
                                                        @csrf
                                                        <div class="form-row align-items-center">
                                                            <div class="col-auto mb-1">
                                                                <input type="text" name="name" class="form-control form-control-sm" value="{{ $stage->name }}" required placeholder="Name">
                                                            </div>
                                                            <div class="col-auto mb-1">
                                                                <input type="text" name="slug" class="form-control form-control-sm" value="{{ $stage->slug }}" placeholder="Slug">
                                                            </div>
                                                            <div class="col-auto mb-1">
                                                                <input type="text" name="symbol" class="form-control form-control-sm" value="{{ $stage->symbol }}" placeholder="Symbol (CSS)">
                                                            </div>
                                                            <div class="col-auto mb-1">
                                                                <input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $stage->sort_order }}" style="width:80px;" placeholder="Sort">
                                                            </div>
                                                            <div class="col-auto mb-1">
                                                                <div class="d-flex align-items-center position-relative">
                                                                    <input type="hidden" name="is_default" value="0">
                                                                    <input type="checkbox" value="1" name="is_default" class="form-check-input mr-1" id="is_default_{{ $stage->id }}" {{ $stage->is_default ? 'checked' : '' }}>
                                                                    <label for="is_default_{{ $stage->id }}" class="form-check-label small mb-0">Default</label>
                                                                </div>
                                                            </div>
                                                            <div class="col-auto mb-1">
                                                                <input type="file" name="image" accept="image/*" class="form-control-file form-control-sm" onchange="previewImage(event, 'preview-stage-{{ $stage->id }}', this)">
                                                            </div>
                                                            <div class="col-auto mb-1">
                                                                <button class="btn btn-sm btn-outline-secondary" type="submit">Speichern</button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                    <form method="post" action="{{ route('admin.grading.stage.delete', ['stage'=>$stage->id]) }}" style="display:inline">
                                                        @csrf
                                                        <button class="btn btn-sm btn-danger mt-1" onclick="return confirm('Stufe löschen?')">Löschen</button>
                                                    </form>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    <small class="text-muted">Stufen per Drag & Drop neu anordnen.</small>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="small mb-2">Neue Stufe für „{{ $system->name }}"</h6>
                                    <form method="post" action="{{ route('admin.grading.stage.store', ['system'=>$system->id]) }}" enctype="multipart/form-data">
                                        @csrf
                                        <div class="form-group">
                                            <label>Name</label>
                                            <input name="name" class="form-control" required maxlength="100">
                                        </div>
                                        <div class="form-group">
                                            <label>Slug (optional)</label>
                                            <input name="slug" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label>Symbol (z. B. FontAwesome-Klasse)</label>
                                            <input name="symbol" class="form-control" placeholder="fas fa-star">
                                        </div>
                                        <div class="form-group">
                                            <label>Bild (optional)</label>
                                            <input type="file" name="image" accept="image/*" class="form-control-file" onchange="previewImage(event, 'preview-new-{{ $system->id }}', this)">
                                            <div class="mt-2"><img id="preview-new-{{ $system->id }}" src="" alt="Vorschau Bild" style="max-width:120px;max-height:80px;object-fit:cover;border-radius:6px;display:none"></div>
                                        </div>
                                        <div class="form-group">
                                            <label>Sort Order</label>
                                            <input type="number" name="sort_order" class="form-control" value="0">
                                        </div>
                                        <div class="form-group mb-2">
                                            <div class="d-flex align-items-center position-relative">
                                                <input type="hidden" name="is_default" value="0">
                                                <input type="checkbox" value="1" name="is_default" class="form-check-input mr-1" id="default_{{ $system->id }}">
                                                <label for="default_{{ $system->id }}" class="form-check-label small mb-0">Als Standardstufe markieren</label>
                                            </div>
                                        </div>
                                        <button class="btn btn-primary">Stufe anlegen</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Fragen-Tab -->
                        <div class="tab-pane fade" id="questions-{{ $system->id }}" role="tabpanel">
                            <div class="row">
                                <div class="col-md-7">
                                    <h6 class="small mb-2">Fragen</h6>
                                    @if($system->questions->isEmpty())
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i> Noch keine Fragen vorhanden.
                                        </div>
                                    @else
                                        <div class="list-group mb-2" id="questions-list-{{ $system->id }}">
                                            @foreach($system->questions->sortBy('sort_order') as $question)
                                                <div class="list-group-item" data-question-id="{{ $question->id }}" draggable="true">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <div class="mb-2">
                                                                <span class="badge badge-secondary">{{ $loop->iteration }}</span>
                                                                <strong>{{ $question->question }}</strong>
                                                                @if(!$question->active)
                                                                    <span class="badge badge-warning ml-2">Inaktiv</span>
                                                                @endif
                                                            </div>

                                                            <!-- Bearbeitungsformular -->
                                                            <form method="post" action="{{ route('admin.grading.question.update', ['question' => $question->id]) }}" class="mb-2">
                                                                @csrf
                                                                <div class="form-row align-items-end">
                                                                    <div class="col-md-8 mb-2">
                                                                        <input type="text" name="question" class="form-control form-control-sm"
                                                                               value="{{ $question->question }}" required>
                                                                    </div>
                                                                    <div class="col-md-2 mb-2">
                                                                        <input type="number" name="sort_order" class="form-control form-control-sm"
                                                                               value="{{ $question->sort_order }}" placeholder="Order">
                                                                    </div>
                                                                    <div class="col-md-2 mb-2">
                                                                        <div class="custom-control custom-checkbox">
                                                                            <input type="hidden" name="active" value="0">
                                                                            <input type="checkbox" class="custom-control-input"
                                                                                   name="active" value="1"
                                                                                   id="active-{{ $question->id }}"
                                                                                   {{ $question->active ? 'checked' : '' }}>
                                                                            <label class="custom-control-label small" for="active-{{ $question->id }}">
                                                                                Aktiv
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="btn-group btn-group-sm">
                                                                    <button type="submit" class="btn btn-outline-secondary">
                                                                        <i class="fas fa-save"></i> Speichern
                                                                    </button>
                                                                </div>
                                                            </form>

                                                            <form method="post" action="{{ route('admin.grading.question.delete', ['question' => $question->id]) }}"
                                                                  style="display:inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-danger"
                                                                        onclick="return confirm('Frage wirklich löschen?')">
                                                                    <i class="fas fa-trash"></i> Löschen
                                                                </button>
                                                            </form>
                                                        </div>
                                                        <div class="ml-2">
                                                            <i class="fas fa-grip-vertical text-muted" style="cursor: move;"></i>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                        <small class="text-muted">Fragen per Drag & Drop neu anordnen.</small>
                                    @endif
                                </div>

                                <div class="col-md-5">
                                    <h6 class="small mb-2">Neue Frage für „{{ $system->name }}"</h6>
                                    <form method="post" action="{{ route('admin.grading.question.store', ['system' => $system->id]) }}">
                                        @csrf
                                        <div class="form-group">
                                            <label>Frage</label>
                                            <textarea name="question" class="form-control" rows="3" required
                                                      placeholder="Z.B.: Wie gut kannst du lesen?"></textarea>
                                            <small class="form-text text-muted">
                                                Diese Frage wird den Schülern in der Dokumentation gestellt.
                                            </small>
                                        </div>
                                        <div class="form-group">
                                            <label>Reihenfolge</label>
                                            <input type="number" name="sort_order" class="form-control" value="0">
                                        </div>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus"></i> Frage anlegen
                                        </button>
                                    </form>

                                    <div class="alert alert-info mt-3">
                                        <small>
                                            <i class="fas fa-info-circle"></i> <strong>Hinweis:</strong><br>
                                            Fragen werden in der Dokumentation verwendet, um Schüler-Selbsteinschätzungen
                                            und Lehrereinschätzungen zu erfassen.
                                        </small>
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
