{{-- Vertragsformular (Create & Edit) – Validierungsfehler zeigt das Layout oberhalb der Seite an --}}
@include('personal.partials._wirkung')

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    {{-- Anstellungsart --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Anstellungsart *</label>
        <select name="employment_type" x-model="type" class="input-personal" required>
            @foreach(\App\Enums\EmploymentType::cases() as $t)
            <option value="{{ $t->value }}" {{ old('employment_type', $employment->employment_type?->value ?? '') === $t->value ? 'selected' : '' }}>
                {{ $t->label() }}
            </option>
            @endforeach
        </select>
    </div>

    {{-- Vertragsart --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Vertragsart *</label>
        <select name="contract_type" x-model="contractType" class="input-personal" required>
            @foreach(\App\Enums\ContractType::cases() as $ct)
            <option value="{{ $ct->value }}" {{ old('contract_type', $employment->contract_type?->value ?? '') === $ct->value ? 'selected' : '' }}>
                {{ $ct->label() }}
            </option>
            @endforeach
        </select>
    </div>

    {{-- Abteilung --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Bereich *</label>
        <select name="department_id" class="input-personal" required>
            <option value="">— wählen —</option>
            @foreach($departments as $group)
            <option value="{{ $group->id }}" {{ old('department_id', $employment->department_id ?? '') == $group->id ? 'selected' : '' }}>
                {{ $group->name }}
            </option>
            @endforeach
        </select>
    </div>

    {{-- Stundenart --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Stundenart *</label>
        <select name="hour_type_id" class="input-personal" required x-model="hourTypeId">
            <option value="">— wählen —</option>
            @foreach($hourTypes as $ht)
            <option value="{{ $ht->id }}" {{ old('hour_type_id', $employment->hour_type_id ?? '') == $ht->id ? 'selected' : '' }}>
                {{ $ht->name }}
            </option>
            @endforeach
        </select>
    </div>

    {{-- Start --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Beginn *</label>
        <input type="date" name="start" class="input-personal" required
               value="{{ old('start', isset($employment) ? $employment->start?->format('Y-m-d') : '') }}">
    </div>

    {{-- Ende (bei Befristung bzw. vorgemerktem/vollzogenem Austritt) --}}
    <div x-show="showEnd" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-1" x-text="isFixedTerm ? 'Befristet bis *' : 'Austrittsdatum'"></label>
        <input type="date" name="end" class="input-personal" x-bind:required="isFixedTerm" x-bind:disabled="!showEnd"
               value="{{ old('end', isset($employment) ? $employment->end?->format('Y-m-d') : '') }}">
        @if(isset($employment) && $employment->termination_reason && $employment->status?->value !== 'beendet')
            <p class="text-xs text-gray-500 mt-1">Beendigung vorgemerkt ({{ $employment->termination_reason->label() }}). Datum leeren, um die Beendigung zurückzunehmen.</p>
        @endif
    </div>

    {{-- Wochenstunden --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Wochenstunden *</label>
        <input type="number" name="hours" step="0.01" min="1" max="168" class="input-personal" required
               x-model="hours" x-bind:readonly="hoursLocked" x-bind:class="hoursLocked ? 'bg-gray-50' : ''">
        <input type="hidden" name="hours_manual" value="0">
        <template x-if="isTeacher">
            <label class="flex items-center gap-2 text-xs text-gray-600 mt-1">
                <input type="checkbox" name="hours_manual" value="1" x-model="manual">
                Wochenstunden manuell festlegen (statt aus dem Deputat zu berechnen)
            </label>
        </template>
        <p class="text-xs text-gray-500 mt-1" x-show="hoursLocked" x-cloak>
            Berechnet: Deputat ÷ Regeldeputat der Schulart × Vollzeit-Wochenstunden der Stundenart.
        </p>
        <p class="text-xs text-blue-700 mt-1" x-show="percent !== null" x-cloak>
            = <strong x-text="percent"></strong> % Stellenanteil
            → Soll-Arbeitszeit <strong x-text="sollWoche"></strong> Std./Woche
            (bei {{ $vollzeit }} Std. Vollzeit laut Einstellungen).
        </p>
        @include('personal.partials._wirkung', ['keys' => ['hours']])
    </div>

    {{-- Arbeitstage (Arbeitszeitmodell) --}}
    @php($arbeitstage = old('workdays', isset($employment) ? $employment->arbeitstage() : [1, 2, 3, 4, 5]))
    <div>
        <span class="block text-sm font-medium text-gray-700 mb-1">Arbeitstage</span>
        <div class="flex flex-wrap gap-1.5">
            @foreach([1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 7 => 'So'] as $nr => $kurz)
                <label class="inline-flex items-center gap-1 rounded-lg border border-gray-300 px-2.5 py-1.5 text-sm cursor-pointer has-[:checked]:bg-blue-50 has-[:checked]:border-blue-400">
                    <input type="checkbox" name="workdays[]" value="{{ $nr }}" @checked(in_array($nr, array_map('intval', (array) $arbeitstage), true))>
                    {{ $kurz }}
                </label>
            @endforeach
        </div>
        <p class="text-xs text-gray-500 mt-1">Die Soll-Arbeitszeit verteilt sich gleichmäßig auf diese Tage; Urlaub zählt nur an diesen Tagen.</p>
    </div>

    {{-- Probezeit --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Probezeit bis</label>
        <input type="date" name="probation_end" class="input-personal"
               value="{{ old('probation_end', isset($employment) ? $employment->probation_end?->format('Y-m-d') : '') }}">
    </div>

    {{-- Kündigungsfrist --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Kündigungsfrist</label>
        <input type="text" name="notice_period" class="input-personal" placeholder="z.B. 3 Monate"
               value="{{ old('notice_period', $employment->notice_period ?? '') }}">
    </div>

    {{-- Nachfolge-Vertrag (nur beim Anlegen) --}}
    @if(!isset($employment) && isset($replaceable) && $replaceable->isNotEmpty())
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Ersetzt Anstellung</label>
        <select name="replaced_employment_id" class="input-personal">
            <option value="">— keine (zusätzliche Anstellung) —</option>
            @foreach($replaceable as $r)
            <option value="{{ $r->id }}" {{ old('replaced_employment_id') == $r->id ? 'selected' : '' }}>
                {{ $r->department?->name ?? '—' }} · {{ $r->hours }}h · seit {{ $r->start?->format('d.m.Y') }}{{ $r->end ? ' bis ' . $r->end->format('d.m.Y') : '' }}
            </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">Die ausgewählte Anstellung wird automatisch am Vortag des Beginns beendet (ohne Offboarding).</p>
    </div>
    @endif

    {{-- Bemerkung --}}
    <div class="md:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1">Bemerkung</label>
        <textarea name="comment" class="input-personal" rows="2">{{ old('comment', $employment->comment ?? '') }}</textarea>
    </div>

    {{-- Änderungsvertrag --}}
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_amendment" value="0">
        <input type="checkbox" name="is_amendment" value="1" id="is_amendment"
               {{ old('is_amendment', $employment->is_amendment ?? false) ? 'checked' : '' }}>
        <label for="is_amendment" class="text-sm text-gray-700">Änderungsvertrag</label>
    </div>

    <div>
        <input type="hidden" name="is_internal_transfer" value="0">
        <input type="checkbox" name="is_internal_transfer" value="1" id="is_internal_transfer"
               {{ old('is_internal_transfer', $employment->is_internal_transfer ?? false) ? 'checked' : '' }}>
        <label for="is_internal_transfer" class="text-sm text-gray-700 ml-2">Interner Wechsel</label>
    </div>

</div>

{{-- Lehrer-spezifische Felder --}}
<div x-show="type === 'lehrer'" x-transition class="mt-6 pt-6 border-t border-gray-100">
    <h3 class="font-semibold text-gray-900 mb-4">Lehrer-Details</h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Schulart *</label>
            <select name="school_type_id" class="input-personal" x-model="schoolTypeId" x-bind:required="isTeacher">
                <option value="">— wählen —</option>
                @foreach($schoolTypes as $st)
                <option value="{{ $st->id }}" {{ old('school_type_id', isset($employment) ? $employment->currentTeacherDetail?->school_type_id : '') == $st->id ? 'selected' : '' }}>
                    {{ $st->name }} (Deputat: {{ $st->default_deputat }}h)
                </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Deputatstunden *</label>
            <input type="number" name="deputat_hours" step="0.5" min="0" class="input-personal"
                   x-model="deputat" x-bind:required="isTeacher">
            <p class="text-xs text-gray-500 mt-1">
                Vertraglich vereinbarte Unterrichtsstunden pro Woche (Vollzeit = Regeldeputat der Schulart).
                Daraus ergeben sich die Wochenstunden oben.
            </p>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ermäßigung (Std.)</label>
            <input type="number" name="reduction_hours" step="0.5" min="0" class="input-personal"
                   value="{{ old('reduction_hours', isset($employment) ? $employment->currentTeacherDetail?->reduction_hours : 0) }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Ermäßigungsgrund</label>
            <input type="text" name="reduction_reason" class="input-personal"
                   value="{{ old('reduction_reason', isset($employment) ? $employment->currentTeacherDetail?->reduction_reason : '') }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Anrechnungsstunden</label>
            <input type="number" name="anrechnungsstunden" step="0.5" min="0" class="input-personal"
                   value="{{ old('anrechnungsstunden', isset($employment) ? $employment->currentTeacherDetail?->anrechnungsstunden : 0) }}">
        </div>
    </div>
</div>

{{-- Vergütung (nur mit Berechtigung) --}}
@can('view salary')
<div class="mt-6 pt-6 border-t border-gray-100">
    <h3 class="font-semibold text-gray-900 mb-4">Vergütung</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tarifgruppe</label>
            <input type="text" name="salary_group" class="input-personal" @cannot('edit salary') disabled @endcannot placeholder="z.B. E9"
                   value="{{ old('salary_group', $employment->salary_group ?? '') }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Vergütungsstufe</label>
            <input type="text" name="salary_level" class="input-personal" @cannot('edit salary') disabled @endcannot placeholder="z.B. Stufe 3"
                   value="{{ old('salary_level', $employment->salary_level ?? '') }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tarifwerk</label>
            <select name="salary_table_id" class="input-personal" @cannot('edit salary') disabled @endcannot>
                <option value="">— keines —</option>
                @foreach($salaryTables as $st)
                <option value="{{ $st->id }}" {{ old('salary_table_id', $employment->salary_table_id ?? '') == $st->id ? 'selected' : '' }}>
                    {{ $st->name }}
                </option>
                @endforeach
            </select>
        </div>
    </div>
</div>
@endcan

{{-- Submit --}}
<div class="flex gap-3 justify-end mt-6 pt-6 border-t border-gray-100">
    <a href="{{ route('personal.contracts.index', $employe->id) }}"
       class="btn-personal-secondary">Abbrechen</a>
    <button type="submit" class="btn-personal-primary">Speichern</button>
</div>

