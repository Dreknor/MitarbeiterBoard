@extends('layouts.app')

@push('css')
    @vite(['resources/css/arbeitsblaetter.css', 'resources/js/arbeitsblaetter.js'])
@endpush

@section('content')
<div class="ab-wrapper ab-schmal" x-data="abEinstellungen(@js([
        'ordner' => old('ab_ordner_vorlage', $werte['ab_ordner_vorlage']),
        'datei' => old('ab_datei_vorlage', $werte['ab_datei_vorlage']),
        'status' => $status,
        'vorschauUrl' => route('arbeitsblaetter.einstellungen.vorschau'),
        'pruefenUrl' => route('arbeitsblaetter.einstellungen.pruefen'),
    ]))" x-cloak>
    <a href="{{ route('arbeitsblaetter.index') }}" class="ab-back mb-3"><i class="fas fa-arrow-left"></i> Bibliothek</a>
    <h1 class="ab-page-title">Einstellungen: Angepasste Arbeitsblätter</h1>
    <p class="ab-page-sub mb-5">Ablage in der Nextcloud, Automatik im Wochenplan, Word-Ausgabe und KI-Kontingent. Zugangsdaten (KI, Nextcloud) stehen in der .env (<code>AB_KI_*</code>, <code>AB_NEXTCLOUD_*</code>).</p>

    @if(session('Meldung'))
        <div class="ab-alert ab-alert-gruen mb-4"><i class="fas fa-check-circle mt-0.5"></i><div>{{ session('Meldung') }}</div></div>
    @endif
    @if($errors->any())
        <div class="ab-alert ab-alert-red mb-4" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><div>{{ $errors->first() }}</div></div>
    @endif

    <div class="grid gap-4 lg:grid-cols-3">
        <form method="post" action="{{ route('arbeitsblaetter.einstellungen.update') }}" class="ab-card lg:col-span-2">
            @csrf @method('PUT')
            <div class="ab-card-body grid gap-5">
                <section>
                    <h2 class="ab-card-title mb-3"><i class="fas fa-folder-open"></i> Nextcloud-Ablage</h2>
                    <div class="grid gap-3">
                        <div>
                            <label class="ab-label" for="ab-ordner">Ordner einer Fassung</label>
                            <input id="ab-ordner" name="ab_ordner_vorlage" type="text" class="ab-input" x-model="ordner" @input="vorschau()" maxlength="255" required>
                            <p class="ab-error" x-show="fehler.ab_ordner_vorlage" x-text="fehler.ab_ordner_vorlage"></p>
                        </div>
                        <div>
                            <label class="ab-label" for="ab-datei">Dateiname (ohne Endung)</label>
                            <input id="ab-datei" name="ab_datei_vorlage" type="text" class="ab-input" x-model="datei" @input="vorschau()" maxlength="255" required>
                            <p class="ab-error" x-show="fehler.ab_datei_vorlage" x-text="fehler.ab_datei_vorlage"></p>
                        </div>
                        <p class="ab-hint mt-0">Platzhalter: @foreach($platzhalter as $p)<code>{{ '{'.$p.'}' }}</code> @endforeach</p>
                        <div class="ab-alert ab-alert-blue" x-show.important="beispiel"><i class="fas fa-eye mt-0.5"></i><div>Beispiel: <code x-text="beispiel"></code></div></div>
                    </div>
                </section>

                <section>
                    <h2 class="ab-card-title mb-3"><i class="fas fa-toggle-on"></i> Abläufe</h2>
                    <div class="grid gap-2">
                        <input type="hidden" name="ab_wochenplan_automatisch" value="0">
                        <label class="ab-check"><input type="checkbox" name="ab_wochenplan_automatisch" value="1" @checked(old('ab_wochenplan_automatisch', $werte['ab_wochenplan_automatisch']))>
                            <span><strong>Wochenplan: automatisch anpassen</strong><br><span class="text-slate-500">Neue Arbeitsblätter an Klassenplänen werden erkannt, wenn die Klasse Kinder mit Sehprofil hat. Nach der Freigabe ersetzt die angepasste Fassung das Original im Kinderplan.</span></span></label>
                        <input type="hidden" name="ab_docx_ausgabe" value="0">
                        <label class="ab-check"><input type="checkbox" name="ab_docx_ausgabe" value="1" @checked(old('ab_docx_ausgabe', $werte['ab_docx_ausgabe']))>
                            <span><strong>Zusätzlich Word-Datei (DOCX)</strong><br><span class="text-slate-500">Neben dem PDF auch eine bearbeitbare Word-Datei erzeugen.</span></span></label>
                    </div>
                </section>

                <section>
                    <h2 class="ab-card-title mb-3"><i class="fas fa-robot"></i> KI-Kontingent pro Monat</h2>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label class="ab-label" for="ab-seiten">Seiten (0 = ohne)</label>
                            <input id="ab-seiten" name="ab_ki_limit_seiten" type="number" min="0" class="ab-input" value="{{ old('ab_ki_limit_seiten', $werte['ab_ki_limit_seiten']) }}">
                        </div>
                        <div>
                            <label class="ab-label" for="ab-euro">Euro (0 = ohne)</label>
                            <input id="ab-euro" name="ab_ki_limit_euro" type="text" inputmode="decimal" class="ab-input" value="{{ old('ab_ki_limit_euro', str_replace('.', ',', $werte['ab_ki_limit_euro'])) }}">
                        </div>
                        <div>
                            <label class="ab-label" for="ab-warnung">Warnung ab (%)</label>
                            <input id="ab-warnung" name="ab_ki_warnung_prozent" type="number" min="1" max="100" class="ab-input" value="{{ old('ab_ki_warnung_prozent', $werte['ab_ki_warnung_prozent']) }}">
                        </div>
                    </div>
                    <p class="ab-hint">Ist das Kontingent aufgebraucht, werden Blätter manuell erfasst (Original neben leerem Editor).</p>
                </section>

                <div class="flex justify-end"><button type="submit" class="ab-btn ab-btn-primary"><i class="fas fa-save"></i> Speichern</button></div>
            </div>
        </form>

        <div class="flex flex-col gap-4">
            <div class="ab-card">
                <div class="ab-card-body">
                    <h2 class="ab-card-title mb-3"><i class="fas fa-chart-bar"></i> Verbrauch {{ now()->translatedFormat('F Y') }}</h2>
                    <div class="ab-balken mb-2"><span class="{{ $prozent >= 100 ? 'is-voll' : ($prozent >= (int) $werte['ab_ki_warnung_prozent'] ? 'is-warnung' : '') }}" style="width: {{ min(100, $prozent) }}%"></span></div>
                    <dl class="grid grid-cols-2 gap-1 text-sm">
                        <dt class="text-slate-500">Seiten</dt><dd class="text-right">{{ $verbrauch['seiten'] }}{{ $limits['seiten'] ? ' / '.$limits['seiten'] : '' }}</dd>
                        <dt class="text-slate-500">Kosten</dt><dd class="text-right">{{ number_format($verbrauch['kosten_cent'] / 100, 2, ',', '.') }} €{{ $limits['cent'] ? ' / '.number_format($limits['cent'] / 100, 2, ',', '.').' €' : '' }}</dd>
                        <dt class="text-slate-500">Erkennungen</dt><dd class="text-right">{{ $verbrauch['aufrufe'] }}</dd>
                    </dl>
                    <p class="ab-hint">Kosten sind eine Schätzung aus den gemeldeten Token.</p>
                </div>
            </div>
            <div class="ab-card">
                <div class="ab-card-body">
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <h2 class="ab-card-title"><i class="fas fa-plug"></i> Status</h2>
                        <button type="button" class="ab-btn ab-btn-secondary ab-btn-sm" @click="pruefen()" :disabled="prueft"><i class="fas" :class="prueft ? 'fa-spinner fa-spin' : 'fa-sync'"></i> Verbindungen prüfen</button>
                    </div>
                    <template x-for="(eintrag, schluessel) in status" :key="schluessel">
                        <div class="ab-status-zeile">
                            <i class="fas mt-0.5" :class="eintrag.ok === true ? 'fa-check-circle text-emerald-600' : (eintrag.ok === false ? 'fa-times-circle text-red-600' : 'fa-info-circle text-slate-400')"></i>
                            <div><div class="font-semibold" x-text="eintrag.titel"></div><div class="text-xs text-slate-600" x-text="eintrag.text"></div></div>
                        </div>
                    </template>
                    <p class="ab-hint mt-2">Auf dem Server: <code>php artisan arbeitsblaetter:pruefen --verbindung</code></p>
                </div>
            </div>
            <div class="ab-card">
                <div class="ab-card-body text-sm">
                    <h2 class="ab-card-title mb-2"><i class="fas fa-layer-group"></i> Bibliothek</h2>
                    <p>{{ $statistik['blaetter'] }} Blätter, davon {{ $statistik['zur_pruefung'] }} zur Prüfung und {{ $statistik['fehler'] }} mit Fehler.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
