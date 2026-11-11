{{--
    Abwesenheit eintragen (Übersicht /absences). Gleiche Felder und Regeln wie die Dashboard-Karte,
    Logik im AbsenceService (Zusammenführen angrenzender Meldungen, Rechte „für andere melden“).
--}}
<form action="{{ route('absences.store') }}" method="post" class="abw-card-body grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
    @csrf

    @if($meldbarePersonen->isNotEmpty())
        <div class="sm:col-span-2 lg:col-span-1">
            <label for="abw-person" class="abw-label">Person</label>
            <select id="abw-person" name="users_id" class="abw-select @error('users_id') is-invalid @enderror">
                @foreach($meldbarePersonen as $person)
                    <option value="{{ $person->id }}" @selected((int) old('users_id', auth()->id()) === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
            @error('users_id')<p class="abw-error">{{ $message }}</p>@enderror
        </div>
    @else
        <input type="hidden" name="users_id" value="{{ auth()->id() }}">
    @endif

    <div>
        <label for="abw-start" class="abw-label">Von</label>
        <input id="abw-start" type="date" name="start" required x-model="start"
               @change="if (ende < start) ende = start"
               class="abw-input @error('start') is-invalid @enderror">
        @error('start')<p class="abw-error">{{ $message }}</p>@enderror
    </div>

    <div>
        <label for="abw-end" class="abw-label">Bis</label>
        <input id="abw-end" type="date" name="end" required x-model="ende" :min="start"
               class="abw-input @error('end') is-invalid @enderror">
        @error('end')<p class="abw-error">{{ $message }}</p>@enderror
    </div>

    <div @class(['lg:col-span-1' => $meldbarePersonen->isNotEmpty(), 'sm:col-span-2' => $meldbarePersonen->isEmpty()])>
        <label for="abw-grund" class="abw-label">Grund</label>
        <input id="abw-grund" type="text" name="reason" required maxlength="64" list="abw-gruende"
               value="{{ old('reason', settings('absence_reason_default', 'absences')) }}"
               class="abw-input @error('reason') is-invalid @enderror">
        <datalist id="abw-gruende">
            @foreach($gruende->merge(config('absences.absence_sick_note'))->unique()->take(40) as $grund)
                <option value="{{ $grund }}"></option>
            @endforeach
        </datalist>
        @error('reason')<p class="abw-error">{{ $message }}</p>@enderror
    </div>

    <div class="sm:col-span-2 lg:col-span-4 flex flex-wrap items-center gap-x-6 gap-y-3">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
            <input type="hidden" name="showVertretungsplan" value="0">
            <input type="checkbox" name="showVertretungsplan" value="1" class="w-4 h-4"
                   @checked(old('showVertretungsplan', '1') == '1')>
            Auf dem Vertretungsplan anzeigen
        </label>

        @if($darfKrankenschein)
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="hidden" name="sick_note_required" value="0">
                <input type="checkbox" name="sick_note_required" value="1" class="w-4 h-4"
                       @checked(old('sick_note_required') == '1')>
                Krankenschein erforderlich
            </label>
        @endif

        <span class="text-xs text-gray-500">
            <i class="fas fa-info-circle mr-1"></i>
            Schließt die Meldung an eine gleiche an (gleiche Person, gleicher Grund), wird diese verlängert.
            @if($darfKrankenschein)
                Bei Krankheit über {{ \App\Models\Absence::krankenscheinTage() }} Tage wird der Krankenschein automatisch verlangt.
            @endif
        </span>

        <div class="flex items-center gap-2 ml-auto">
            <button type="button" class="abw-btn abw-btn-ghost" @click="offen = false">Abbrechen</button>
            <button type="submit" class="abw-btn abw-btn-primary"><i class="fas fa-save"></i><span>Speichern</span></button>
        </div>
    </div>
</form>
