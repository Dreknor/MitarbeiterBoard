@extends('wiki.layout')

@section('wiki')
<div class="flex flex-col gap-5">
    @include('wiki.partials.seitenkopf', ['untertitel' => 'Änderungen vergleichen'])

    <form method="GET" action="{{ route('wiki.vergleich', $seite->slug) }}" class="wiki-karte">
        @php
            $fassung = fn ($r) => $r->created_at?->format($r->created_at?->isCurrentYear() ? 'd.m. H:i' : 'd.m.Y H:i').' · '.$r->user->name;
        @endphp
        <div class="wiki-karte-inhalt flex flex-wrap items-end gap-3">
            <div class="min-w-[13rem] flex-1">
                <label class="wiki-label" for="von">Ältere Fassung</label>
                <select id="von" name="von" class="wiki-select">
                    <option value="">– leere Seite –</option>
                    @foreach($revisionen as $r)
                        <option value="{{ $r->id }}" @selected($von?->id === $r->id)>{{ $fassung($r) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[13rem] flex-1">
                <label class="wiki-label" for="bis">Neuere Fassung</label>
                <select id="bis" name="bis" class="wiki-select">
                    @foreach($revisionen as $r)
                        <option value="{{ $r->id }}" @selected($bis->id === $r->id)>{{ $fassung($r) }}</option>
                    @endforeach
                </select>
            </div>
            <button class="wiki-btn wiki-btn-secondary shrink-0"><i class="fas fa-sync-alt"></i> Vergleichen</button>
        </div>
    </form>

    <div class="wiki-karte">
        <div class="wiki-karte-kopf">
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="wiki-badge wiki-badge-grau">+{{ $diff['hinzu'] }} / −{{ $diff['weg'] }} Wörter</span>
                @if($titelGeaendert)
                    <span class="text-gray-600">Titel: <del class="bg-red-100">{{ $von->titel }}</del> → <ins class="bg-emerald-100 no-underline">{{ $bis->titel }}</ins></span>
                @endif
                @if($bis->notiz)<span class="text-gray-500">„{{ $bis->notiz }}“</span>@endif
            </div>
            @can('update', $seite)
                @if($revisionen->first()?->id !== $bis->id)
                    <form method="POST" action="{{ route('wiki.wiederherstellen', [$seite, $bis]) }}" onsubmit="return confirm('Die neuere der beiden Fassungen wiederherstellen?')">
                        @csrf
                        <button class="wiki-btn wiki-btn-secondary wiki-btn-sm"><i class="fas fa-undo"></i> Diese Fassung wiederherstellen</button>
                    </form>
                @endif
            @endcan
        </div>
        <div class="wiki-karte-inhalt wiki-diff">
            @if($diff['html'] === '')
                <div class="wiki-leer">Keine Unterschiede im Text.</div>
            @else
                {!! $diff['html'] !!}
            @endif
        </div>
    </div>
</div>
@endsection
