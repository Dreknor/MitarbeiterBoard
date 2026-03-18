{{--
  Zeitraster-Formular-Partial
  Wird von create.blade.php und edit.blade.php eingebunden.
  $zeitraster = null (create) | Zeitraster-Objekt (edit)
--}}

{{-- Stammdaten --}}
<div class="form-row">
    <div class="col-md-6 col-sm-12 mb-3">
        <label for="name">Name <span class="text-danger">*</span></label>
        <input type="text"
               name="name"
               id="name"
               class="form-control @error('name') is-invalid @enderror"
               value="{{ old('name', $zeitraster?->name) }}"
               maxlength="100"
               required>
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6 col-sm-12 mb-3">
        <label for="beschreibung">Beschreibung</label>
        <input type="text"
               name="beschreibung"
               id="beschreibung"
               class="form-control @error('beschreibung') is-invalid @enderror"
               value="{{ old('beschreibung', $zeitraster?->beschreibung) }}"
               maxlength="1000">
        @error('beschreibung')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
</div>

<div class="form-row mb-3">
    <div class="col-12">
        <div class="custom-control custom-checkbox">
            <input type="hidden" name="ist_standard" value="0">
            <input type="checkbox"
                   class="custom-control-input"
                   id="ist_standard"
                   name="ist_standard"
                   value="1"
                   {{ old('ist_standard', $zeitraster?->ist_standard) ? 'checked' : '' }}>
            <label class="custom-control-label" for="ist_standard">
                Als Standard-Zeitraster verwenden
            </label>
        </div>
        <small class="text-muted">
            Das Standard-Zeitraster wird für Klassen ohne eigene Zuordnung verwendet.
        </small>
    </div>
</div>

{{-- Stunden-Tabelle (Alpine.js) --}}
<hr>
<h6>Stundenzeiten</h6>

@php
    // Vorbelegung: entweder gespeicherte LessonTimes oder leeres Array
    $stundenInit = old('stunden');
    if ($stundenInit === null) {
        if ($zeitraster && $zeitraster->lessonTimes->isNotEmpty()) {
            $stundenInit = $zeitraster->lessonTimes->map(fn($lt) => [
                'period' => $lt->period,
                'start'  => $lt->start,
                'end'    => $lt->end,
                'week'   => $lt->week ?? '',
            ])->values()->all();
        } else {
            $stundenInit = [];
        }
    }
@endphp

<div x-data="{
    stunden: {{ json_encode($stundenInit) }},
    addStunde() {
        this.stunden.push({ period: this.stunden.length + 1, start: '', end: '', week: '' });
    },
    removeStunde(index) {
        this.stunden.splice(index, 1);
    }
}">
    <table class="table table-sm table-bordered" x-show="stunden.length > 0">
        <thead class="thead-light">
            <tr>
                <th style="width: 80px">Stunde</th>
                <th>Von</th>
                <th>Bis</th>
                <th style="width: 100px">Woche</th>
                <th style="width: 60px"></th>
            </tr>
        </thead>
        <tbody>
            <template x-for="(stunde, i) in stunden" :key="i">
                <tr>
                    <td>
                        <input type="number"
                               :name="'stunden[' + i + '][period]'"
                               x-model.number="stunde.period"
                               class="form-control form-control-sm"
                               min="1" max="15" required>
                        @error('stunden.*.period')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </td>
                    <td>
                        <input type="time"
                               :name="'stunden[' + i + '][start]'"
                               x-model="stunde.start"
                               class="form-control form-control-sm"
                               required>
                    </td>
                    <td>
                        <input type="time"
                               :name="'stunden[' + i + '][end]'"
                               x-model="stunde.end"
                               class="form-control form-control-sm"
                               required>
                    </td>
                    <td>
                        <input type="text"
                               :name="'stunden[' + i + '][week]'"
                               x-model="stunde.week"
                               class="form-control form-control-sm"
                               placeholder="A / B"
                               maxlength="5">
                    </td>
                    <td class="text-center">
                        <button type="button"
                                @click="removeStunde(i)"
                                class="btn btn-sm btn-outline-danger"
                                title="Stunde entfernen">
                            &times;
                        </button>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>

    <p class="text-muted small" x-show="stunden.length === 0">
        Noch keine Stundenzeiten eingetragen.
    </p>

    {{-- Validierungsfehler für stunden-Array --}}
    @foreach($errors->keys() as $key)
        @if(str_starts_with($key, 'stunden.'))
            <div class="alert alert-danger alert-sm py-1 px-2 mt-1">
                <small>{{ $errors->first($key) }}</small>
            </div>
        @endif
    @endforeach

    <button type="button"
            @click="addStunde()"
            class="btn btn-sm btn-outline-success mt-2">
        <i class="fa fa-plus"></i> Stunde hinzufügen
    </button>
</div>

