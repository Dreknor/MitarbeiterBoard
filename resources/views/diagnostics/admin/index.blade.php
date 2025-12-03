@extends('layouts.app')

@section('content')
<div class="container-fluid" x-data="diagnosticAdmin">
    <div class="row">
        <div class="col-12">
            {{-- Header --}}
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4>
                    <i class="fas fa-cog"></i> Diagnosebögen - Verwaltung
                </h4>
                <div>
                    <a href="{{ route('diagnostic.index') }}" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Zurück zur Übersicht
                    </a>
                    <button type="button" class="btn btn-sm btn-primary" @click="openAreaModal()">
                        <i class="fas fa-plus"></i> Neuer Bereich
                    </button>
                </div>
            </div>

            {{-- Bereiche Liste --}}
            @if($areas->isEmpty())
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    Noch keine Diagnosebereiche vorhanden. Klicken Sie auf "Neuer Bereich" um zu beginnen.
                </div>
            @else
                <div class="accordion" id="areasAccordion">
                    @foreach($areas as $area)
                        <div class="card mb-2" data-area-id="{{ $area->id }}">
                            <div class="card-header" id="heading-{{ $area->id }}">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center flex-grow-1">
                                        <button class="btn btn-link text-left flex-grow-1" type="button"
                                                data-toggle="collapse"
                                                data-target="#collapse-{{ $area->id }}">
                                            <i class="fas fa-folder-open"></i>
                                            <strong>{{ $area->name }}</strong>
                                            @if(!$area->active)
                                                <span class="badge badge-secondary">Inaktiv</span>
                                            @endif
                                            <small class="text-muted">({{ $area->stages->count() }} Stufen)</small>
                                        </button>
                                    </div>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-sm btn-primary"
                                                @click="openStageModal({{ $area->id }}, '{{ addslashes($area->name) }}')">
                                            <i class="fas fa-plus"></i> Stufe
                                        </button>
                                        <button type="button" class="btn btn-sm btn-secondary"
                                                @click="editArea({{ $area->id }}, '{{ addslashes($area->name) }}', '{{ addslashes($area->description) }}', {{ $area->active ? 'true' : 'false' }})">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
                                                @click="deleteArea({{ $area->id }}, '{{ addslashes($area->name) }}')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="collapse-{{ $area->id }}" class="collapse" data-parent="#areasAccordion">
                                <div class="card-body">
                                    {{-- Bereichsziel --}}
                                    @if($area->description)
                                        <div class="alert alert-info mb-3">
                                            <strong>Bereichsziel:</strong> {{ $area->description }}
                                        </div>
                                    @endif

                                    {{-- Stufen --}}
                                    @if($area->stages->isEmpty())
                                        <div class="alert alert-warning">
                                            Noch keine Stufen vorhanden.
                                        </div>
                                    @else
                                        <div class="accordion" id="stagesAccordion-{{ $area->id }}">
                                            @foreach($area->stages as $stage)
                                                <div class="card mb-2 ml-3" data-stage-id="{{ $stage->id }}">
                                                    <div class="card-header bg-light">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <button class="btn btn-link text-left flex-grow-1" type="button"
                                                                    data-toggle="collapse"
                                                                    data-target="#collapse-stage-{{ $stage->id }}">
                                                                <i class="fas fa-layer-group"></i>
                                                                <strong>{{ $stage->name }}</strong> ({{ $stage->code }})
                                                                <small class="text-muted">({{ $stage->goals->count() }} Ziele)</small>
                                                            </button>
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-sm btn-success"
                                                                        @click="openGoalModal({{ $stage->id }}, '{{ addslashes($stage->name) }}')">
                                                                    <i class="fas fa-plus"></i> Ziel
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-secondary"
                                                                        @click="editStage({{ $stage->id }}, {{ $area->id }}, '{{ addslashes($stage->name) }}', '{{ addslashes($stage->code) }}', '{{ addslashes($stage->goal_description) }}')">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-sm btn-danger"
                                                                        @click="deleteStage({{ $stage->id }}, '{{ addslashes($stage->name) }}')">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div id="collapse-stage-{{ $stage->id }}" class="collapse">
                                                        <div class="card-body">
                                                            {{-- Stufenziel --}}
                                                            @if($stage->goal_description)
                                                                <div class="alert alert-secondary mb-3">
                                                                    <strong>Stufenziel:</strong> {{ $stage->goal_description }}
                                                                </div>
                                                            @endif

                                                            {{-- Ziele --}}
                                                            @if($stage->goals->isEmpty())
                                                                <div class="alert alert-warning">
                                                                    Noch keine Ziele vorhanden.
                                                                </div>
                                                            @else
                                                                <table class="table table-sm table-hover">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="width: 80px;">Code</th>
                                                                            <th>Beschreibung</th>
                                                                            <th style="width: 100px;" class="text-right">Aktionen</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach($stage->goals as $goal)
                                                                            <tr data-goal-id="{{ $goal->id }}">
                                                                                <td><strong>{{ $goal->code }}</strong></td>
                                                                                <td>{{ $goal->description }}</td>
                                                                                <td class="text-right">
                                                                                    <div class="btn-group btn-group-sm">
                                                                                        <button type="button" class="btn btn-sm btn-secondary"
                                                                                                @click="editGoal({{ $goal->id }}, {{ $stage->id }}, '{{ addslashes($goal->code) }}', '{{ addslashes($goal->description) }}')">
                                                                                            <i class="fas fa-edit"></i>
                                                                                        </button>
                                                                                        <button type="button" class="btn btn-sm btn-danger"
                                                                                                @click="deleteGoal({{ $goal->id }}, '{{ addslashes($goal->code) }}')">
                                                                                            <i class="fas fa-trash"></i>
                                                                                        </button>
                                                                                    </div>
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Area Modal --}}
    <div class="modal fade" id="areaModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="areaModalTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveArea">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" class="form-control" x-model="areaForm.name" required>
                        </div>
                        <div class="form-group">
                            <label>Bereichsziel (Beschreibung)</label>
                            <textarea class="form-control" rows="3" x-model="areaForm.description"></textarea>
                        </div>
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="areaActive" x-model="areaForm.active">
                                <label class="custom-control-label" for="areaActive">Aktiv</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" @click="saveArea">Speichern</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Stage Modal --}}
    <div class="modal fade" id="stageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="stageModalTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveStage">
                        <div class="form-group">
                            <label>Name *</label>
                            <input type="text" class="form-control" x-model="stageForm.name" placeholder="z.B. Stufe I" required>
                        </div>
                        <div class="form-group">
                            <label>Code *</label>
                            <input type="text" class="form-control" x-model="stageForm.code" placeholder="z.B. I, II, III..." required>
                        </div>
                        <div class="form-group">
                            <label>Stufenziel (Beschreibung)</label>
                            <textarea class="form-control" rows="3" x-model="stageForm.goal_description"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" @click="saveStage">Speichern</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Goal Modal --}}
    <div class="modal fade" id="goalModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" x-text="goalModalTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <form @submit.prevent="saveGoal">
                        <div class="form-group">
                            <label>Code *</label>
                            <input type="text" class="form-control" x-model="goalForm.code" placeholder="z.B. V-1" required>
                        </div>
                        <div class="form-group">
                            <label>Beschreibung *</label>
                            <textarea class="form-control" rows="4" x-model="goalForm.description" required></textarea>
                            <small class="form-text text-muted">
                                Inkl. Modalitäten und Querverweise zu anderen Bereichen
                            </small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" @click="saveGoal">Speichern</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('diagnosticAdmin', () => ({
        areaModalTitle: 'Neuer Bereich',
        areaForm: { id: null, name: '', description: '', active: true },

        stageModalTitle: 'Neue Stufe',
        stageForm: { id: null, area_id: null, name: '', code: '', goal_description: '' },

        goalModalTitle: 'Neues Ziel',
        goalForm: { id: null, stage_id: null, code: '', description: '' },

        // Area Methods
        openAreaModal() {
            this.areaModalTitle = 'Neuer Bereich';
            this.areaForm = { id: null, name: '', description: '', active: true };
            $('#areaModal').modal('show');
        },

        editArea(id, name, description, active) {
            this.areaModalTitle = 'Bereich bearbeiten';
            this.areaForm = { id, name, description, active };
            $('#areaModal').modal('show');
        },

        async saveArea() {
            try {
                const url = this.areaForm.id
                    ? `/admin/diagnostics/areas/${this.areaForm.id}`
                    : '/admin/diagnostics/areas';

                const method = this.areaForm.id ? 'put' : 'post';

                await axios[method](url, this.areaForm);

                $('#areaModal').modal('hide');
                window.location.reload();
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Speichern');
            }
        },

        async deleteArea(id, name) {
            if (!confirm(`Bereich "${name}" wirklich löschen? Alle Stufen und Ziele werden ebenfalls gelöscht!`)) {
                return;
            }

            try {
                await axios.delete(`/admin/diagnostics/areas/${id}`);
                window.location.reload();
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Löschen');
            }
        },

        // Stage Methods
        openStageModal(areaId, areaName) {
            this.stageModalTitle = `Neue Stufe für "${areaName}"`;
            this.stageForm = { id: null, area_id: areaId, name: '', code: '', goal_description: '' };
            $('#stageModal').modal('show');
        },

        editStage(id, areaId, name, code, goalDescription) {
            this.stageModalTitle = 'Stufe bearbeiten';
            this.stageForm = { id, area_id: areaId, name, code, goal_description: goalDescription };
            $('#stageModal').modal('show');
        },

        async saveStage() {
            try {
                const url = this.stageForm.id
                    ? `/admin/diagnostics/stages/${this.stageForm.id}`
                    : `/admin/diagnostics/areas/${this.stageForm.area_id}/stages`;

                const method = this.stageForm.id ? 'put' : 'post';

                await axios[method](url, this.stageForm);

                $('#stageModal').modal('hide');
                window.location.reload();
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Speichern');
            }
        },

        async deleteStage(id, name) {
            if (!confirm(`Stufe "${name}" wirklich löschen? Alle Ziele werden ebenfalls gelöscht!`)) {
                return;
            }

            try {
                await axios.delete(`/admin/diagnostics/stages/${id}`);
                window.location.reload();
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Löschen');
            }
        },

        // Goal Methods
        openGoalModal(stageId, stageName) {
            this.goalModalTitle = `Neues Ziel für "${stageName}"`;
            this.goalForm = { id: null, stage_id: stageId, code: '', description: '' };
            $('#goalModal').modal('show');
        },

        editGoal(id, stageId, code, description) {
            this.goalModalTitle = 'Ziel bearbeiten';
            this.goalForm = { id, stage_id: stageId, code, description };
            $('#goalModal').modal('show');
        },

        async saveGoal() {
            try {
                const url = this.goalForm.id
                    ? `/admin/diagnostics/goals/${this.goalForm.id}`
                    : `/admin/diagnostics/stages/${this.goalForm.stage_id}/goals`;

                const method = this.goalForm.id ? 'put' : 'post';

                await axios[method](url, this.goalForm);

                $('#goalModal').modal('hide');
                window.location.reload();
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Speichern');
            }
        },

        async deleteGoal(id, code) {
            if (!confirm(`Ziel "${code}" wirklich löschen?`)) {
                return;
            }

            try {
                await axios.delete(`/admin/diagnostics/goals/${id}`);
                window.location.reload();
            } catch (error) {
                console.error('Fehler:', error);
                alert('Fehler beim Löschen');
            }
        }
    }));
});
</script>
@endpush
@endsection

