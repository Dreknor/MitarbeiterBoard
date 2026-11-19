{{--
    Wiederkehrende Abwesenheit – Zusatzfelder für die Abwesenheits-Formulare (/absences und beide Dashboard-Karten).
    Muss innerhalb eines <form> mit den Feldern start/end/reason stehen. Von/Bis gelten dann als Zeitraum der Serie.
    Logik und Regeln: App\Services\AbsenceSerieService.

    Optional: $serienKlassen = ['label' => …, 'input' => …, 'select' => …, 'hinweis' => …, 'fehler' => …, 'zeile' => …]
--}}
@php
    $k = array_merge([
        'label' => 'block text-xs font-medium text-gray-700 mb-1',
        'select' => 'w-full text-sm border border-gray-300 rounded-lg px-3 py-2',
        'check' => 'inline-flex items-center gap-1 text-sm text-gray-700 cursor-pointer mr-3',
        'hinweis' => 'text-xs text-gray-500',
        'fehler' => 'text-xs text-red-600',
        'zeile' => 'grid grid-cols-2 gap-2',
        'wrapper' => '',
    ], $serienKlassen ?? []);
    $krankGruende = array_values((array) config('absences.absence_sick_note'));
    $alteWochentage = array_map('intval', (array) old('wochentage', []));
@endphp

<div class="{{ $k['wrapper'] }}"
     x-data="{
        wiederkehrend: @js(old('wiederkehrend') == '1'),
        rhythmus: @js(old('rhythmus', 'woechentlich')),
        wochentage: @js($alteWochentage),
        monatWochentag: @js((int) old('monat_wochentag', 1)),
        krank: false,
        krankGruende: @js($krankGruende),
        pruefeGrund() {
            const grund = this.$el.closest('form')?.querySelector('[name=reason]')?.value ?? '';
            this.krank = this.krankGruende.some(g => g !== '' && grund.includes(g));
            if (this.krank) this.wiederkehrend = false;
        },
        einschalten() {
            if (!this.wiederkehrend) return;
            const start = this.$el.closest('form')?.querySelector('[name=start]')?.value;
            if (!start) return;
            const tag = ((new Date(start + 'T12:00:00').getDay() + 6) % 7) + 1;
            if (tag <= 5) {
                if (this.wochentage.length === 0) this.wochentage = [tag];
                this.monatWochentag = tag;
            }
        },
     }"
     x-init="pruefeGrund(); $el.closest('form')?.querySelector('[name=reason]')?.addEventListener('input', () => pruefeGrund())">

    <input type="hidden" name="wiederkehrend" value="0">
    <label class="{{ $k['check'] }}" x-show.important="!krank">
        <input type="checkbox" name="wiederkehrend" value="1" x-model="wiederkehrend" @change="einschalten()">
        <i class="fas fa-redo-alt"></i> Wiederkehrend
    </label>
    <p class="{{ $k['hinweis'] }}" x-show="krank" x-cloak>Krankheit kann nicht wiederkehrend eingetragen werden.</p>

    <div x-show="wiederkehrend && !krank" x-cloak style="margin-top: .5rem">
        <div class="{{ $k['zeile'] }}">
            <div>
                <label class="{{ $k['label'] }}">Rhythmus</label>
                <select name="rhythmus" x-model="rhythmus" class="{{ $k['select'] }}" :disabled="!wiederkehrend">
                    <option value="woechentlich">wöchentlich</option>
                    <option value="monatlich">monatlich (n-ter Wochentag)</option>
                </select>
            </div>
            <div x-show="rhythmus === 'woechentlich'">
                <label class="{{ $k['label'] }}">Wiederholung</label>
                <select name="intervall" class="{{ $k['select'] }}" :disabled="!wiederkehrend || rhythmus !== 'woechentlich'">
                    @foreach([1 => 'jede Woche', 2 => 'alle 2 Wochen', 3 => 'alle 3 Wochen', 4 => 'alle 4 Wochen'] as $wert => $text)
                        <option value="{{ $wert }}" @selected((int) old('intervall', 1) === $wert)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="rhythmus === 'monatlich'">
                <label class="{{ $k['label'] }}">Woche im Monat</label>
                <select name="monat_position" class="{{ $k['select'] }}" :disabled="!wiederkehrend || rhythmus !== 'monatlich'">
                    @foreach(\App\Models\AbsenceSerie::POSITIONEN as $wert => $text)
                        <option value="{{ $wert }}" @selected((int) old('monat_position', 1) === $wert)>{{ $wert === -1 ? 'letzter' : $text }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div x-show="rhythmus === 'woechentlich'" style="margin-top: .5rem">
            <span class="{{ $k['label'] }}">Wochentage</span>
            @foreach(\App\Models\AbsenceSerie::WOCHENTAGE as $wert => $name)
                <label class="{{ $k['check'] }}">
                    <input type="checkbox" name="wochentage[]" value="{{ $wert }}" x-model.number="wochentage"
                           :disabled="!wiederkehrend || rhythmus !== 'woechentlich'">
                    {{ mb_substr($name, 0, 2) }}
                </label>
            @endforeach
        </div>

        <div x-show="rhythmus === 'monatlich'" style="margin-top: .5rem">
            <label class="{{ $k['label'] }}">Wochentag</label>
            <select name="monat_wochentag" x-model.number="monatWochentag" class="{{ $k['select'] }}"
                    :disabled="!wiederkehrend || rhythmus !== 'monatlich'">
                @foreach(\App\Models\AbsenceSerie::WOCHENTAGE as $wert => $name)
                    <option value="{{ $wert }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        @foreach(['rhythmus', 'wochentage', 'intervall', 'monat_position', 'monat_wochentag'] as $feld)
            @error($feld)<p class="{{ $k['fehler'] }}">{{ $message }}</p>@enderror
        @endforeach

        <p class="{{ $k['hinweis'] }}" style="margin-top: .5rem">
            <i class="fas fa-info-circle"></i>
            „Von“ und „Bis“ sind der Zeitraum der Serie (höchstens ein Jahr, Beginn ab heute). Jeder Termin gilt einen Tag;
            Wochenenden, Feiertage und Tage, an denen die Person schon abwesend ist, werden übersprungen.
        </p>
    </div>
</div>
