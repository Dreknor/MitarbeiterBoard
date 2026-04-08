{{-- Vertragsformular (Create & Edit) --}}
@if($errors->any())
<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
    <ul class="list-disc list-inside text-red-700 text-sm">
        @foreach($errors->all() as $error)
        <li>{{ $error }}</li>
        @endforeach
    </ul>
</div>
@endif

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
        <label class="block text-sm font-medium text-gray-700 mb-1">Abteilung</label>
        <select name="department_id" class="input-personal">
            <option value="">— keine —</option>
            @foreach(\App\Models\Group::all() as $group)
            <option value="{{ $group->id }}" {{ old('department_id', $employment->department_id ?? '') == $group->id ? 'selected' : '' }}>
                {{ $group->name }}
            </option>
            @endforeach
        </select>
    </div>

    {{-- Stundenart --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Stundenart</label>
        <select name="hour_type_id" class="input-personal">
            <option value="">— keine —</option>
            @foreach(\App\Models\personal\HourType::all() as $ht)
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

    {{-- Ende (bei Befristung) --}}
    <div x-show="contractType === 'befristet' || contractType === 'befristet_sachgrund'" x-transition>
        <label class="block text-sm font-medium text-gray-700 mb-1">Ende</label>
        <input type="date" name="end" class="input-personal"
               value="{{ old('end', isset($employment) ? $employment->end?->format('Y-m-d') : '') }}">
    </div>

    {{-- Wochenstunden --}}
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Wochenstunden *</label>
        <input type="number" name="hours" step="0.5" min="1" max="168" class="input-personal" required
               value="{{ old('hours', $employment->hours ?? '') }}">
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
            <select name="school_type_id" class="input-personal">
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
                   value="{{ old('deputat_hours', isset($employment) ? $employment->currentTeacherDetail?->deputat_hours : '') }}">
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
            <input type="text" name="salary_group" class="input-personal" placeholder="z.B. E9"
                   value="{{ old('salary_group', $employment->salary_group ?? '') }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Vergütungsstufe</label>
            <input type="text" name="salary_level" class="input-personal" placeholder="z.B. Stufe 3"
                   value="{{ old('salary_level', $employment->salary_level ?? '') }}">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Tarifwerk</label>
            <select name="salary_table_id" class="input-personal">
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

