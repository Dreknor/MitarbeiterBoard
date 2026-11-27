@extends('layouts.app')

@push('css')
    @vite(['resources/css/ergebnisboards.css', 'resources/js/ergebnisboards.js'])
@endpush

@section('content')
<div class="erg-wrapper erg-schmal">
    <a href="{{ route('ergebnisboards.index') }}" class="erg-back mb-3"><i class="fas fa-arrow-left"></i> Boards</a>
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="erg-page-title">Vorlagen</h1>
            <p class="erg-page-sub">Eine Vorlage legt nur das Raster fest. Beim Anlegen eines Boards wird es kopiert – spätere Änderungen betreffen bestehende Boards nicht.</p>
        </div>
        <a href="{{ route('ergebnisboards.vorlagen.create') }}" class="erg-btn erg-btn-primary">
            <i class="fas fa-plus"></i> Neue Vorlage
        </a>
    </div>

    <section class="erg-card mb-5">
        <div class="erg-card-body flex flex-col gap-3">
            <h2 class="erg-card-title"><i class="fas fa-pencil-ruler"></i> Eigene Vorlagen</h2>
            @if($eigene->isEmpty())
                <div class="erg-empty">
                    <i class="fas fa-shapes"></i>
                    <span>Noch keine eigenen Vorlagen. Legen Sie eine neue an, nutzen Sie eine eingebaute als Grundlage oder speichern Sie ein Board über „Einstellungen“ als Vorlage.</span>
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($eigene as $vorlage)
                        @php($v = \App\Support\ErgebnisboardVorlagen::normalisieren($vorlage->definition ?? []))
                        <article class="erg-kachel {{ $vorlage->aktiv ? '' : 'opacity-70' }}">
                            @include('ergebnisboards.vorlagen.partials.mini', ['v' => $v])
                            <div class="flex items-start gap-2">
                                <i class="{{ $v['icon'] }} text-emerald-700 mt-1"></i>
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-bold text-slate-900 leading-snug">{{ $vorlage->titel }}</h3>
                                    @if($vorlage->beschreibung)
                                        <p class="text-xs text-slate-500 leading-snug mt-0.5">{{ $vorlage->beschreibung }}</p>
                                    @endif
                                </div>
                                @if($vorlage->aktiv)
                                    <span class="erg-chip shrink-0 bg-emerald-50 text-emerald-800"><i class="fas fa-check"></i> aktiv</span>
                                @else
                                    <span class="erg-chip shrink-0"><i class="fas fa-pause"></i> inaktiv</span>
                                @endif
                            </div>
                            <div class="erg-meta">
                                <span><i class="far fa-user"></i> {{ $vorlage->ersteller?->name ?? '–' }}</span>
                                <span title="Zuletzt geändert"><i class="far fa-clock"></i> {{ $vorlage->updated_at?->format('d.m.Y') }}</span>
                                <span><i class="fas fa-th"></i> {{ count(array_filter($v['bereiche'], fn ($b) => $b['eltern'] === null)) }} Felder</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 mt-auto pt-2 border-t border-slate-100">
                                <a href="{{ route('ergebnisboards.vorlagen.edit', $vorlage) }}" class="erg-btn erg-btn-secondary erg-btn-sm">
                                    <i class="fas fa-pen"></i> Bearbeiten
                                </a>
                                <a href="{{ route('ergebnisboards.vorlagen.create', ['basis' => $vorlage->schluessel]) }}" class="erg-btn erg-btn-ghost erg-btn-sm" title="Neue Vorlage mit diesem Raster anlegen">
                                    <i class="far fa-copy"></i> Als Grundlage
                                </a>
                                <form method="post" action="{{ route('ergebnisboards.vorlagen.destroy', $vorlage) }}" class="ml-auto"
                                      onsubmit="return confirm('Vorlage „{{ addslashes($vorlage->titel) }}“ löschen? Bestehende Boards bleiben unverändert.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="erg-btn erg-btn-danger erg-btn-sm"><i class="far fa-trash-alt"></i> Löschen</button>
                                </form>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section class="erg-card">
        <div class="erg-card-body flex flex-col gap-3">
            <h2 class="erg-card-title"><i class="fas fa-shapes"></i> Eingebaute Vorlagen</h2>
            <p class="erg-hint mt-0">Eingebaute Vorlagen sind fest. Über „Als Grundlage“ entsteht eine eigene, änderbare Kopie.</p>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($eingebaut as $schluessel => $v)
                    <article class="erg-kachel">
                        @include('ergebnisboards.vorlagen.partials.mini', ['v' => $v])
                        <div class="flex items-start gap-2">
                            <i class="{{ $v['icon'] }} text-emerald-700 mt-1"></i>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-sm font-bold text-slate-900 leading-snug">{{ $v['titel'] }}</h3>
                                @if($v['beschreibung'])
                                    <p class="text-xs text-slate-500 leading-snug mt-0.5">{{ $v['beschreibung'] }}</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-auto pt-2 border-t border-slate-100">
                            <a href="{{ route('ergebnisboards.vorlagen.create', ['basis' => $schluessel]) }}" class="erg-btn erg-btn-secondary erg-btn-sm">
                                <i class="far fa-copy"></i> Als Grundlage
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>
</div>
@endsection
