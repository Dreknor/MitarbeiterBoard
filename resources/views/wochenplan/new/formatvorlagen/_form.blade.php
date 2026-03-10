@php
    $cfg = $fv?->layout_config ?? [];
    $old = fn($k, $d) => old($k, $d);
@endphp

@if($errors->any())
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
        <ul class="list-disc list-inside space-y-1">
            @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
        </ul>
    </div>
@endif

{{-- Basis --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700">Allgemein</h2>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Name <span class="text-red-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $fv?->name) }}" required
               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Beschreibung</label>
        <textarea name="beschreibung" rows="2"
                  class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">{{ old('beschreibung', $fv?->beschreibung) }}</textarea>
    </div>
</div>

{{-- Papier --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700">Papier</h2>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Papiergröße</label>
            <select name="papier_groesse"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                @foreach(['A4' => 'A4 (Standard)', 'A3' => 'A3 (Groß)', 'letter' => 'Letter (US)'] as $val => $label)
                    <option value="{{ $val }}" {{ old('papier_groesse', $cfg['papier']['groesse'] ?? 'A4') === $val ? 'selected' : '' }}>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-2">Ausrichtung</label>
            <div class="flex gap-4">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" name="papier_ausrichtung" value="portrait"
                           {{ old('papier_ausrichtung', $cfg['papier']['ausrichtung'] ?? 'portrait') === 'portrait' ? 'checked' : '' }}
                           class="text-primary-600">
                    📄 Hochformat
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="radio" name="papier_ausrichtung" value="landscape"
                           {{ old('papier_ausrichtung', $cfg['papier']['ausrichtung'] ?? 'portrait') === 'landscape' ? 'checked' : '' }}
                           class="text-primary-600">
                    📄 Querformat
                </label>
            </div>
        </div>
    </div>
</div>

{{-- Schrift --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700">Schrift & Typografie</h2>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Schriftgröße (Voreinstellung)</label>
            <select name="schriftgroesse"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="normal"     {{ old('schriftgroesse', $fv?->schriftgroesse) === 'normal'     ? 'selected' : '' }}>Normal (11pt)</option>
                <option value="gross"      {{ old('schriftgroesse', $fv?->schriftgroesse) === 'gross'      ? 'selected' : '' }}>Groß (14pt)</option>
                <option value="sehr_gross" {{ old('schriftgroesse', $fv?->schriftgroesse) === 'sehr_gross' ? 'selected' : '' }}>Sehr groß (18pt)</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Exakte Schriftgröße (pt)</label>
            <input type="number" name="schriftgroesse_pt"
                   value="{{ old('schriftgroesse_pt', $cfg['typografie']['schriftgroesse_pt'] ?? '') }}"
                   placeholder="Leer = Voreinstellung"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">Überschreibt die Voreinstellung (6–36 pt).</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Schriftart</label>
            <select name="schriftart"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                <option value="Arial, sans-serif"            {{ old('schriftart', $fv?->schriftart) === 'Arial, sans-serif'            ? 'selected' : '' }}>Arial</option>
                <option value="'Comic Sans MS', cursive"     {{ old('schriftart', $fv?->schriftart) === "'Comic Sans MS', cursive"     ? 'selected' : '' }}>Comic Sans</option>
                <option value="'Times New Roman', serif"     {{ old('schriftart', $fv?->schriftart) === "'Times New Roman', serif"     ? 'selected' : '' }}>Times New Roman</option>
                <option value="'Courier New', monospace"     {{ old('schriftart', $fv?->schriftart) === "'Courier New', monospace"     ? 'selected' : '' }}>Courier New</option>
                <option value="OpenDyslexic"                 {{ old('schriftart', $fv?->schriftart) === 'OpenDyslexic'                 ? 'selected' : '' }}>OpenDyslexic (Legasthenie-Unterstützung)</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Zeilenabstand</label>
            <select name="zeilenabstand"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                @foreach(['1.0' => 'Eng (1.0)', '1.2' => 'Schmal (1.2)', '1.4' => 'Normal (1.4)', '1.6' => 'Weit (1.6)', '2.0' => 'Doppelt (2.0)'] as $val => $lbl)
                    <option value="{{ $val }}" {{ (string) old('zeilenabstand', $cfg['typografie']['zeilenabstand'] ?? '1.4') === $val ? 'selected' : '' }}>
                        {{ $lbl }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- Abstände --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700">Abstände</h2>
    <div class="grid grid-cols-4 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Zwischen Fächern (mm)</label>
            <input type="number" name="abstand_faecher"
                   value="{{ old('abstand_faecher', $cfg['abstände']['zwischen_fächern'] ?? 5) }}"
                   min="0" max="30"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Zwischen Aufgaben (mm)</label>
            <input type="number" name="abstand_aufgaben"
                   value="{{ old('abstand_aufgaben', $cfg['abstände']['zwischen_aufgaben'] ?? 2) }}"
                   min="0" max="15"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Mindest-Zeilenhöhe (mm)</label>
            <input type="number" name="min_zeilenhoehe"
                   value="{{ old('min_zeilenhoehe', $cfg['abstände']['min_fach_zeilenhoehe'] ?? 0) }}"
                   min="0" max="50"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">0 = automatisch</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Namenszeile Mindesthöhe (mm)</label>
            <input type="number" name="namenszeile_zeilenhoehe"
                   value="{{ old('namenszeile_zeilenhoehe', $cfg['header']['namenszeile_zeilenhoehe'] ?? 0) }}"
                   min="0" max="80"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">0 = automatisch, z.B. 20 für extra Schreibzeile</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Namenszeile Abstand oben (px)</label>
            <input type="number" name="namenszeile_abstand_oben"
                   value="{{ old('namenszeile_abstand_oben', $cfg['header']['namenszeile_abstand_oben'] ?? 4) }}"
                   min="0" max="60"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">Abstand zwischen Titel und Name-Zeile (Standard: 4)</p>
        </div>
    </div>
</div>

{{-- Layout / PDF-Template --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700">Layout-Template</h2>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">PDF-Template</label>
        <select name="blade_template"
                class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
            <option value="wochenplan.pdf.standard"    {{ old('blade_template', $fv?->blade_template) === 'wochenplan.pdf.standard'    ? 'selected' : '' }}>Standard</option>
            <option value="wochenplan.pdf.gross"       {{ old('blade_template', $fv?->blade_template) === 'wochenplan.pdf.gross'       ? 'selected' : '' }}>Große Schrift</option>
            <option value="wochenplan.pdf.individuell" {{ old('blade_template', $fv?->blade_template) === 'wochenplan.pdf.individuell' ? 'selected' : '' }}>Individuell (Kinderplan)</option>
        </select>
    </div>
</div>

{{-- Seitenränder --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-700">Seitenränder (mm)</h2>
    <div class="grid grid-cols-4 gap-3">
        @foreach([['margin_top','Oben',$cfg['seitenraender']['oben'] ?? 20], ['margin_bottom','Unten',$cfg['seitenraender']['unten'] ?? 20], ['margin_left','Links',$cfg['seitenraender']['links'] ?? 15], ['margin_right','Rechts',$cfg['seitenraender']['rechts'] ?? 15]] as [$name,$label,$default])
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                <input type="number" name="{{ $name }}" value="{{ old($name, $default) }}" min="0" max="50"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
        @endforeach
    </div>
</div>

{{-- Spaltenbreiten --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-700">Spaltenbreiten (%)</h2>
    <div class="grid grid-cols-4 gap-3">
        @foreach([
            ['col_fach','Fach',intval($cfg['spalten']['fach'] ?? '15')],
            ['col_aufgaben','Aufgaben',intval($cfg['spalten']['aufgaben'] ?? '55')],
            ['col_check','Check / Haken',intval($cfg['spalten']['check'] ?? '5')],
            ['col_unterschrift','Unterschrift',intval($cfg['spalten']['unterschrift'] ?? '25')],
            ['col_kontrolliert','Kontrolliert',intval($cfg['spalten']['kontrolliert'] ?? '12')],
        ] as [$name,$label,$default])
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                <input type="number" name="{{ $name }}" value="{{ old($name, $default) }}" min="0" max="100"
                       class="w-full px-2 py-1.5 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-400">Summe sollte 100% ergeben.</p>
</div>

{{-- Sichtbarkeit / Spalten --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-700">Sichtbarkeit & Spalten</h2>
    <div class="grid grid-cols-2 gap-2">
        @foreach([
            ['zeige_name_feld',            'Name-Feld anzeigen',              $cfg['header']['zeige_name_feld'] ?? true],
            ['zeige_klasse',               'Klasse anzeigen',                 $cfg['header']['zeige_klasse']    ?? true],
            ['zeige_zeitraum',             'Zeitraum anzeigen',               $cfg['header']['zeige_zeitraum']  ?? true],
            ['zeige_selbsteinschaetzung',  'Selbsteinschätzung anzeigen',     $cfg['footer']['zeige_selbsteinschaetzung'] ?? true],
            ['zeige_dauer_spalte',         'Dauer-Spalte anzeigen',           $cfg['spalten']['zeige_dauer'] ?? false],
            ['zeige_check_spalte',         '✓ Check-Spalte anzeigen',         $cfg['spalten']['zeige_check_spalte'] ?? true],
            ['zeige_unterschrift_spalte',  'Unterschrift-Spalte anzeigen',    $cfg['spalten']['zeige_unterschrift_spalte'] ?? true],
            ['zeige_kontrolliert_spalte',  'Kontrolliert-Spalte anzeigen',    $cfg['spalten']['zeige_kontrolliert_spalte'] ?? false],
            ['label_trennung_unterschrift','„Unterschrift" umbrechen (Unter-schrift)', $cfg['spalten']['label_trennung_unterschrift'] ?? false],
            ['label_trennung_kontrolliert','„Kontrolliert" umbrechen (Kon-trolliert)', $cfg['spalten']['label_trennung_kontrolliert'] ?? false],
        ] as [$name,$label,$default])
            <label class="flex items-center gap-2 text-sm cursor-pointer">
                <input type="checkbox" name="{{ $name }}" value="1"
                       {{ old($name, $default) ? 'checked' : '' }}
                       class="rounded border-gray-300 text-primary-600">
                {{ $label }}
            </label>
        @endforeach
    </div>
</div>

{{-- Kopfzeile Freitext --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-3">
    <h2 class="text-sm font-semibold text-gray-700">Kopfzeile & Fußzeile (optional)</h2>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Freitext Kopfzeile</label>
        <input type="text" name="header_freitext" value="{{ old('header_freitext', $cfg['header']['freitext'] ?? '') }}"
               placeholder="z.B. Grundschule Musterstadt"
               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-1">Freitext Fußzeile</label>
        <input type="text" name="footer_freitext" value="{{ old('footer_freitext', $cfg['footer']['freitext'] ?? '') }}"
               placeholder="z.B. Viel Spaß beim Lernen!"
               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
    </div>
</div>

{{-- Tägliche Übungen --}}
<div class="bg-white rounded-lg border border-gray-200 p-4 space-y-4">
    <h2 class="text-sm font-semibold text-gray-700">Tägliche Übungen</h2>

    {{-- Layout-Auswahl --}}
    <div>
        <label class="block text-xs font-medium text-gray-700 mb-2">Darstellungsform</label>
        <div class="grid grid-cols-3 gap-3">
            @php $tuLayout = old('tu_layout', $cfg['taegliche_uebungen']['layout'] ?? 'horizontal'); @endphp
            <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer {{ $tuLayout === 'horizontal' ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:bg-gray-50' }}">
                <input type="radio" name="tu_layout" value="horizontal" {{ $tuLayout === 'horizontal' ? 'checked' : '' }} class="mt-0.5 text-primary-600">
                <div>
                    <div class="text-sm font-medium text-gray-800">Horizontal <span class="text-xs text-gray-400">(Standard)</span></div>
                    <div class="text-xs text-gray-500 mt-0.5">Übungen als Zeilen, Tage als Spalten. Gut für kurze Pläne (≤ 10 Tage).</div>
                    <div class="mt-1.5 font-mono text-[9px] text-gray-400 leading-tight">
                        Übung&nbsp;&nbsp;| Mo | Di | Mi<br>
                        Lesen&nbsp;&nbsp;&nbsp;| □ &nbsp;| □ &nbsp;| □<br>
                        Rechnen | □ &nbsp;| □ &nbsp;| □
                    </div>
                </div>
            </label>
            <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer {{ $tuLayout === 'vertikal' ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:bg-gray-50' }}">
                <input type="radio" name="tu_layout" value="vertikal" {{ $tuLayout === 'vertikal' ? 'checked' : '' }} class="mt-0.5 text-primary-600">
                <div>
                    <div class="text-sm font-medium text-gray-800">Vertikal <span class="text-xs text-green-600 font-medium">empfohlen bei &gt; 10 Tagen</span></div>
                    <div class="text-xs text-gray-500 mt-0.5">Tage als Zeilen, Übungen als Spalten. Skaliert für beliebig viele Tage ohne Breitenproblem.</div>
                    <div class="mt-1.5 font-mono text-[9px] text-gray-400 leading-tight">
                        Datum&nbsp;&nbsp; | Lesen | Rechnen<br>
                        Mo 09.03. | □ &nbsp;&nbsp;&nbsp;&nbsp;| □<br>
                        Di 10.03. | □ &nbsp;&nbsp;&nbsp;&nbsp;| □
                    </div>
                </div>
            </label>
            <label class="flex items-start gap-3 p-3 border rounded-lg cursor-pointer {{ $tuLayout === 'wochenweise' ? 'border-primary-500 bg-primary-50' : 'border-gray-200 hover:bg-gray-50' }}">
                <input type="radio" name="tu_layout" value="wochenweise" {{ $tuLayout === 'wochenweise' ? 'checked' : '' }} class="mt-0.5 text-primary-600">
                <div>
                    <div class="text-sm font-medium text-gray-800">Wochenweise <span class="text-xs text-green-600 font-medium">kompakteste Form</span></div>
                    <div class="text-xs text-gray-500 mt-0.5">Wochen als Zeilen, Wochentage als Spalten mit Unter-Spalten pro Übung. Ohne Datumsangaben.</div>
                    <div class="mt-1.5 font-mono text-[9px] text-gray-400 leading-tight">
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;| Montag &nbsp;&nbsp;| Dienstag<br>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;| L&nbsp;R&nbsp;&nbsp;| L&nbsp;R<br>
                        Woche 1 | □ □ | □ □<br>
                        Woche 2 | □ □ | □ □
                    </div>
                </div>
            </label>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Schriftgröße Wochentag (pt)</label>
            <input type="number" name="tu_schriftgroesse_wochentag_pt"
                   value="{{ old('tu_schriftgroesse_wochentag_pt', $cfg['taegliche_uebungen']['schriftgroesse_wochentag_pt'] ?? '') }}"
                   placeholder="Leer = Standard"
                   min="6" max="36"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">Kopfzeile: Mo, Di, Mi …</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Schriftgröße Datum (pt)</label>
            <input type="number" name="tu_schriftgroesse_datum_pt"
                   value="{{ old('tu_schriftgroesse_datum_pt', $cfg['taegliche_uebungen']['schriftgroesse_datum_pt'] ?? '') }}"
                   placeholder="Leer = Standard"
                   min="6" max="36"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">Datumszeile: 09.03., 10.03. …</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Schriftgröße Aufgaben (pt)</label>
            <input type="number" name="tu_schriftgroesse_aufgaben_pt"
                   value="{{ old('tu_schriftgroesse_aufgaben_pt', $cfg['taegliche_uebungen']['schriftgroesse_aufgaben_pt'] ?? '') }}"
                   placeholder="Leer = Standard"
                   min="6" max="36"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">Aufgabentexte in der Tabelle</p>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Max. Tage pro Tabelle</label>
            <input type="number" name="tu_max_tage_pro_tabelle"
                   value="{{ old('tu_max_tage_pro_tabelle', $cfg['taegliche_uebungen']['max_tage_pro_tabelle'] ?? 0) }}"
                   min="0" max="30"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">0 = alle Tage in einer Tabelle. Bei längeren Plänen z.B. 10, damit bei 15 Werktagen zwei Tabellen entstehen.</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-700 mb-1">Breite Aufgaben-Spalte</label>
            <input type="text" name="tu_aufgaben_spalte_breite"
                   value="{{ old('tu_aufgaben_spalte_breite', $cfg['taegliche_uebungen']['aufgaben_spalte_breite'] ?? '') }}"
                   placeholder="auto (z.B. 40% oder 8cm)"
                   class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
            <p class="text-xs text-gray-400 mt-1">Nur bei horizontalem Layout. PDF: %-Wert (z.B. <code>40%</code>). Word: cm-Wert (z.B. <code>7cm</code>). Leer = automatisch.</p>
        </div>
    </div>
</div>

{{-- Standard --}}
<div class="bg-white rounded-lg border border-gray-200 p-4">
    <label class="flex items-center gap-2 text-sm cursor-pointer">
        <input type="checkbox" name="is_default" value="1"
               {{ old('is_default', $fv?->is_default) ? 'checked' : '' }}
               class="rounded border-gray-300 text-primary-600">
        <span class="font-medium">Als Standard-Formatvorlage setzen</span>
    </label>
    <p class="text-xs text-gray-400 mt-1 ml-6">Wird als Fallback für Pläne ohne zugewiesene Formatvorlage verwendet.</p>
</div>

