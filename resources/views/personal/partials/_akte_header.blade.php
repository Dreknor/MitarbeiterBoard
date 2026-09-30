{{--
    Gemeinsamer Kopf aller Seiten der Personalakte (Übersicht, Stammdaten, Verträge, Dokumente,
    Qualifikationen, Änderungsverlauf) – verbindet die Unterseiten zu einer Akte.

    Parameter:
      $employe  User
      $active   Schlüssel des aktiven Reiters (uebersicht|stammdaten|vertraege|dokumente|qualifikationen|verlauf)
      $actions  optional: HTML für Aktions-Buttons rechts
    Flash-Meldungen und Validierungsfehler zeigt bereits das Layout an.
--}}
@php
    $akteBereiche = $employe->employments()
        ->where('status', \App\Enums\EmploymentStatus::Aktiv->value)
        ->where('start', '<=', today())
        ->where(fn ($q) => $q->whereNull('end')->orWhere('end', '>=', today()))
        ->with('department:id,name')
        ->get()
        ->map(fn ($e) => $e->department?->name)->filter()->unique()->implode(' · ');

    // photo() liefert ohne eigenes Bild den Standard-Avatar → dann Initialen anzeigen
    $akteFoto = $employe->photo();
    if ($akteFoto === asset('img/avatar.png')) {
        $akteFoto = null;
    }
    $akteInitialen = mb_strtoupper(mb_substr($employe->vorname ?? '', 0, 1) . mb_substr($employe->familienname ?? $employe->name, 0, 1));

    $akteTabs = collect([
        'uebersicht'      => ['Übersicht',        'view personal_data',     fn () => route('personal.personalakte.show', $employe->id)],
        'stammdaten'      => ['Stammdaten',       'edit employe',           fn () => route('personal.personalakte.stammdaten', $employe->id)],
        'vertraege'       => ['Verträge',         'view contracts',         fn () => route('personal.contracts.index', $employe->id)],
        'dokumente'       => ['Dokumente',        'view personal_documents', fn () => route('personal.documents.index', $employe->id)],
        'qualifikationen' => ['Qualifikationen',  'view qualifications',    fn () => route('personal.qualifications.index', $employe->id)],
        'verlauf'         => ['Änderungsverlauf', 'view personal_audit',    fn () => route('personal.personalakte.verlauf', $employe->id)],
    ])->filter(fn ($tab) => auth()->user()->can($tab[1]));
@endphp

<div class="mb-6">
    <div class="flex items-center justify-between flex-wrap gap-3 mb-4">
        <div class="flex items-center gap-4 min-w-0">
            @if($akteFoto)
                <img src="{{ $akteFoto }}" alt="" class="w-14 h-14 rounded-full object-cover ring-2 ring-white shadow shrink-0">
            @else
                <div class="personal-avatar shrink-0" aria-hidden="true">{{ $akteInitialen }}</div>
            @endif
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-gray-900 truncate">{{ $employe->vorname }} {{ $employe->familienname }}</h1>
                <p class="text-gray-500 text-sm truncate">
                    {{ $employe->email }}
                    @if($akteBereiche)<span class="text-gray-400"> · {{ $akteBereiche }}</span>@endif
                </p>
            </div>
        </div>
        <div class="flex gap-2 flex-wrap">
            {!! $actions ?? '' !!}
            @can('edit employe')
                <a href="{{ route('employes.index') }}" class="btn-personal-secondary text-sm">← Alle Mitarbeitenden</a>
            @endcan
        </div>
    </div>

    @if($akteTabs->count() > 1)
    <nav class="flex border-b border-gray-200 overflow-x-auto" aria-label="Bereiche der Personalakte">
        @foreach($akteTabs as $key => [$label, $perm, $url])
            <a href="{{ $url() }}"
               class="personal-tab whitespace-nowrap {{ ($active ?? '') === $key ? 'personal-tab-active' : 'personal-tab-inactive' }}"
               @if(($active ?? '') === $key) aria-current="page" @endif>
                {{ $label }}
            </a>
        @endforeach
    </nav>
    @endif
</div>
