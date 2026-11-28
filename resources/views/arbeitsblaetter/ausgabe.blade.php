@extends('layouts.app')

@push('css')
    @vite(['resources/css/arbeitsblaetter.css', 'resources/js/arbeitsblaetter.js'])
@endpush

@section('content')
<div class="ab-wrapper ab-schmal" x-data="{ kinder: [], vorlagen: [], alleKlasse(ids, an) { ids.forEach(id => { const i = this.kinder.indexOf(id); if (an && i < 0) this.kinder.push(id); if (!an && i >= 0) this.kinder.splice(i, 1); }); } }">
    <a href="{{ route('arbeitsblaetter.edit', $blatt) }}" class="ab-back mb-3"><i class="fas fa-arrow-left"></i> Zum Blatt</a>
    <h1 class="ab-page-title">Ausgabe: {{ $blatt->titel }}</h1>
    <p class="ab-page-sub mb-5">Das Blatt wird für jedes gewählte Kind nach dessen Sehprofil neu gesetzt. Bereits erzeugte Fassungen werden wiederverwendet, solange sich Blatt und Profil nicht geändert haben.</p>

    @if(session('Meldung'))
        <div class="ab-alert ab-alert-{{ session('type') === 'success' ? 'gruen' : 'amber' }} mb-4"><i class="fas fa-info-circle mt-0.5"></i><div>{{ session('Meldung') }}</div></div>
    @endif
    @if($errors->any())
        <div class="ab-alert ab-alert-red mb-4" role="alert"><i class="fas fa-exclamation-circle mt-0.5"></i><div>{{ $errors->first() }}</div></div>
    @endif
    @if(! $blatt->istFreigegeben())
        <div class="ab-alert ab-alert-amber mb-4"><i class="fas fa-exclamation-triangle mt-0.5"></i><div>Das Blatt ist noch nicht freigegeben. Fassungen sind Entwürfe.</div></div>
    @endif

    <form method="post" action="{{ route('arbeitsblaetter.ausgabe.erzeugen', $blatt) }}">
        @csrf
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="ab-card lg:col-span-2">
                <div class="ab-card-body">
                    <h2 class="ab-card-title mb-3"><i class="fas fa-child"></i> Kinder mit Sehprofil</h2>
                    @forelse($kinder as $klasse => $gruppe)
                        <div class="mb-4">
                            <div class="flex items-center justify-between gap-2 mb-1.5">
                                <h3 class="text-sm font-bold text-slate-700">{{ $klasse }}</h3>
                                <label class="ab-check text-xs"><input type="checkbox" @change="alleKlasse(@js($gruppe->pluck('id')->values()), $event.target.checked)"> alle</label>
                            </div>
                            <div class="grid gap-1.5 sm:grid-cols-2">
                                @foreach($gruppe as $kind)
                                    <label class="ab-check rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                                        <input type="checkbox" name="kinder[]" value="{{ $kind->id }}" x-model.number="kinder">
                                        <span><span class="font-semibold">{{ $kind->vorname }} {{ $kind->nachname }}</span><br><span class="text-xs text-slate-500">{{ $profilKurz[$kind->id] ?? '' }}</span></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">Es gibt noch keine Kinder mit Sehprofil. <a href="{{ route('arbeitsblaetter.profile') }}" class="underline">Sehprofile anlegen</a></p>
                    @endforelse

                    <h2 class="ab-card-title mt-5 mb-3"><i class="fas fa-sliders-h"></i> Profil-Vorlagen <span class="text-xs font-normal text-slate-500">(ohne Kind, z. B. zum Kopieren)</span></h2>
                    <div class="grid gap-1.5 sm:grid-cols-2">
                        @foreach($vorlagen as $vorlage)
                            <label class="ab-check rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">
                                <input type="checkbox" name="vorlagen[]" value="{{ $vorlage->id }}" x-model.number="vorlagen">
                                <span><span class="font-semibold">{{ $vorlage->name }}</span><br><span class="text-xs text-slate-500">{{ \App\Support\AbProfil::kurz($vorlage->wirksameWerte()) }}</span></span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <div class="ab-card">
                    <div class="ab-card-body flex flex-col gap-2">
                        <h2 class="ab-card-title mb-1"><i class="fas fa-download"></i> Erzeugen</h2>
                        <p class="text-xs text-slate-500" x-text="(kinder.length + vorlagen.length) + ' ausgewählt'"></p>
                        <button type="submit" name="aktion" value="einzeln" class="ab-btn ab-btn-primary" :disabled="kinder.length + vorlagen.length !== 1"
                                title="Für genau eine Auswahl" onclick="this.form.querySelector('[name=format]').value='pdf'"><i class="fas fa-file-pdf"></i> PDF herunterladen</button>
                        @if($docx)
                            <button type="submit" name="aktion" value="einzeln" class="ab-btn ab-btn-secondary" :disabled="kinder.length + vorlagen.length !== 1"
                                    onclick="this.form.querySelector('[name=format]').value='docx'"><i class="fas fa-file-word"></i> Word herunterladen</button>
                        @endif
                        <input type="hidden" name="format" value="pdf">
                        <button type="submit" name="aktion" value="sammel" class="ab-btn ab-btn-secondary" :disabled="kinder.length + vorlagen.length < 1"
                                onclick="this.form.querySelector('[name=format]').value='pdf'"><i class="fas fa-copy"></i> Sammel-PDF (alle Auswahl)</button>
                        @if($nextcloud)
                            <button type="submit" name="aktion" value="nextcloud" class="ab-btn ab-btn-secondary" :disabled="kinder.length + vorlagen.length < 1"><i class="fas fa-cloud-upload-alt"></i> In Nextcloud ablegen</button>
                            <p class="ab-hint">Ablage z. B. unter <code class="text-xs">{{ $zielBeispiel }}.pdf</code></p>
                        @endif
                        @foreach($vorlagen as $vorlage)
                            @if(($vorlage->werte['farbschema'] ?? '') === 'invertiert')
                                <p class="ab-hint"><i class="fas fa-tint"></i> Invertierte Fassungen haben einen dunklen Hintergrund – hoher Tonerverbrauch beim Drucken.</p>
                                @break
                            @endif
                        @endforeach
                    </div>
                </div>

                <div class="ab-card">
                    <div class="ab-card-body">
                        <h2 class="ab-card-title mb-2"><i class="fas fa-history"></i> Vorhandene Fassungen</h2>
                        @forelse($fassungen as $fassung)
                            <div class="flex items-center justify-between gap-2 py-1.5 border-b border-slate-100 text-sm">
                                <div class="min-w-0">
                                    <div class="font-medium truncate">{{ $fassung->empfaenger }}</div>
                                    <div class="text-xs text-slate-500">{{ $fassung->updated_at?->format('d.m.Y H:i') }} · {{ $fassung->istAktuell() ? 'aktuell' : 'veraltet' }}@if($fassung->nextcloud_pfad) · <i class="fas fa-cloud"></i>@endif</div>
                                </div>
                                <div class="flex gap-1 shrink-0">
                                    <a href="{{ route('arbeitsblaetter.fassungen.datei', [$blatt, $fassung, 'pdf']) }}" class="ab-icon-btn" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                    @if($fassung->docx_pfad)<a href="{{ route('arbeitsblaetter.fassungen.datei', [$blatt, $fassung, 'docx']) }}" class="ab-icon-btn" title="Word"><i class="fas fa-file-word"></i></a>@endif
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">Noch keine Fassungen.</p>
                        @endforelse
                        @if($wochenplanFassungen->isNotEmpty())
                            <p class="ab-hint mt-2"><i class="fas fa-calendar-week"></i> {{ $wochenplanFassungen->count() }} Fassung(en) hängen an Kinderplänen im Wochenplan.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
