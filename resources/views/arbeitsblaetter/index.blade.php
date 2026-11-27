@extends('layouts.app')

@push('css')
    @vite(['resources/css/arbeitsblaetter.css', 'resources/js/arbeitsblaetter.js'])
@endpush

@section('content')
<div class="ab-wrapper ab-schmal">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="ab-page-title">Angepasste Arbeitsblätter</h1>
            <p class="ab-page-sub">Arbeitsblätter fotografieren oder hochladen, prüfen und pro Kind nach dessen Sehprofil neu setzen lassen.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('edit settings')
                <a href="{{ route('arbeitsblaetter.einstellungen') }}" class="ab-btn ab-btn-ghost" title="Einstellungen"><i class="fas fa-cog"></i><span class="hidden sm:inline">Einstellungen</span></a>
            @endcan
            <a href="{{ route('arbeitsblaetter.profile') }}" class="ab-btn ab-btn-secondary" data-tour="ab-profile">
                <i class="fas fa-user-cog"></i> Sehprofile <span class="ab-chip">{{ $profilkinder }}</span>
            </a>
            @can('create', \App\Models\Arbeitsblaetter\AngepasstesBlatt::class)
                <a href="{{ route('arbeitsblaetter.create') }}" class="ab-btn ab-btn-primary" data-tour="ab-neu">
                    <i class="fas fa-plus"></i> Neues Blatt
                </a>
            @endcan
            <button type="button" class="ab-icon-btn" data-tour-start="angepasste-arbeitsblaetter" title="Rundgang starten"><i class="fas fa-question-circle"></i></button>
        </div>
    </div>

    @if(session('Meldung'))
        <div class="ab-alert ab-alert-{{ session('type') === 'success' ? 'gruen' : 'amber' }} mb-4"><i class="fas fa-info-circle mt-0.5"></i><div>{{ session('Meldung') }}</div></div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <nav class="ab-segment" aria-label="Ansicht" data-tour="ab-ansicht">
            @foreach(['alle' => ['Alle', 'fa-th-large'], 'meine' => ['Meine', 'fa-user'], 'zur_pruefung' => ['Zur Prüfung', 'fa-clipboard-check'], 'freigegeben' => ['Freigegeben', 'fa-check']] as $wert => [$label, $icon])
                <a href="{{ route('arbeitsblaetter.index', array_filter(array_merge($filter, ['ansicht' => $wert === 'alle' ? null : $wert]))) }}"
                   class="{{ $filter['ansicht'] === $wert ? 'is-active' : '' }}" @if($filter['ansicht'] === $wert) aria-current="page" @endif>
                    <i class="fas {{ $icon }}"></i> {{ $label }}
                    @if($wert === 'zur_pruefung' && $offen > 0)<span class="ab-chip" style="background:#fde68a">{{ $offen }}</span>@endif
                </a>
            @endforeach
        </nav>
        <form method="get" action="{{ route('arbeitsblaetter.index') }}" class="flex flex-wrap items-center gap-2 w-full sm:w-auto" role="search">
            @if($filter['ansicht'] !== 'alle')<input type="hidden" name="ansicht" value="{{ $filter['ansicht'] }}">@endif
            <input type="search" name="q" value="{{ $filter['q'] ?? '' }}" class="ab-input sm:w-56" placeholder="Titel suchen …" aria-label="Titel suchen">
            <select name="fach" class="ab-select sm:w-40" aria-label="Fach">
                <option value="">Alle Fächer</option>
                @foreach($faecher as $fach)
                    <option value="{{ $fach->id }}" @selected((int) ($filter['fach'] ?? 0) === $fach->id)>{{ $fach->name }}</option>
                @endforeach
            </select>
            <select name="klassenstufe" class="ab-select sm:w-32" aria-label="Klassenstufe">
                <option value="">Alle Stufen</option>
                @for($i = 1; $i <= 13; $i++)
                    <option value="{{ $i }}" @selected((int) ($filter['klassenstufe'] ?? 0) === $i)>Klasse {{ $i }}</option>
                @endfor
            </select>
            <button type="submit" class="ab-btn ab-btn-secondary">Filtern</button>
        </form>
    </div>

    @if($blaetter->isEmpty())
        <div class="ab-card">
            <div class="ab-empty">
                <i class="fas fa-low-vision"></i>
                <p>Noch keine Arbeitsblätter{{ ($filter['q'] ?? '') !== '' ? ' zu dieser Suche' : '' }}.</p>
                @can('create', \App\Models\Arbeitsblaetter\AngepasstesBlatt::class)
                    <a href="{{ route('arbeitsblaetter.create') }}" class="ab-btn ab-btn-primary mt-2"><i class="fas fa-camera"></i> Erstes Blatt erfassen</a>
                @endcan
            </div>
        </div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" data-tour="ab-liste">
            @foreach($blaetter as $blatt)
                <a href="{{ route('arbeitsblaetter.edit', $blatt) }}" class="ab-kachel">
                    <div class="flex items-start justify-between gap-2">
                        <h2 class="text-base font-bold text-slate-900">{{ $blatt->titel }}</h2>
                        <span class="ab-status ab-status-{{ $blatt->status }}">{{ $blatt->status_text }}</span>
                    </div>
                    <div class="ab-meta">
                        @if($blatt->fach)<span><i class="fas fa-book"></i> {{ $blatt->fach->name }}</span>@endif
                        @if($blatt->klassenstufe)<span><i class="fas fa-layer-group"></i> Klasse {{ $blatt->klassenstufe }}</span>@endif
                        @if($blatt->quelle === 'wochenplan')<span><i class="fas fa-calendar-week"></i> Wochenplan</span>@endif
                        @if($blatt->fassungen_count)<span><i class="fas fa-copy"></i> {{ $blatt->fassungen_count }} {{ $blatt->fassungen_count === 1 ? 'Fassung' : 'Fassungen' }}</span>@endif
                    </div>
                    <div class="ab-meta mt-auto">
                        <span><i class="fas fa-user"></i> {{ $blatt->ersteller?->name ?? 'unbekannt' }}</span>
                        <span>{{ $blatt->updated_at?->format('d.m.Y') }}</span>
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $blaetter->links() }}</div>
    @endif
</div>

<x-tour id="angepasste-arbeitsblaetter" :steps="[
    ['target' => 'ab-neu', 'title' => 'Neues Blatt', 'text' => 'Arbeitsblatt mit dem Handy fotografieren oder als PDF, Bild oder Word-Datei hochladen. Tipp: Blatt flach hinlegen, gleichmäßig ausleuchten, Schatten vermeiden.'],
    ['target' => 'ab-ansicht', 'title' => 'Bibliothek', 'text' => 'Alle Blätter der Schule. Unter „Zur Prüfung“ stehen Ihre erkannten Blätter, die noch freigegeben werden müssen.'],
    ['target' => 'ab-liste', 'title' => 'Blatt öffnen', 'text' => 'Im Prüf-Editor vergleichen Sie das Original mit den erkannten Bausteinen und geben das Blatt frei.'],
    ['target' => 'ab-profile', 'title' => 'Sehprofile', 'text' => 'Schrift, Größe, Abstände und Farben werden je Kind einmal festgelegt. Jede Fassung wird automatisch danach gesetzt.'],
]" />
@endsection
