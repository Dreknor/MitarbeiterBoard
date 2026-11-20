@extends('layouts.app')


@push('css')
    @vite(['resources/css/posts.css', 'resources/js/posts.js'])
@endpush

@section('content')
<div class="nachr-wrapper">
    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div>
            <h1 class="nachr-page-title">Nachrichten</h1>
            <p class="nachr-page-sub">{{ $group->name }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="nachr-btn nachr-btn-ghost nachr-btn-sm" data-tour-start="nachrichten" title="Kurze Einführung">
                <i class="far fa-question-circle"></i> <span class="hidden sm:inline">Einführung</span>
            </button>
            @if($darfPosten)
                <a href="{{ route('posts.create', ['gruppe' => $group->id, 'zurueck' => url()->full()]) }}"
                   class="nachr-btn nachr-btn-primary" data-tour="nachrichten-neu">
                    <i class="fas fa-plus"></i> Neue Nachricht
                </a>
            @endif
        </div>
    </div>

    @if($zaehler['unbestaetigt'] > 0 && ! $archiv)
        <div class="nachr-alert nachr-alert-amber mb-4">
            <i class="fas fa-signature mt-0.5"></i>
            <div>
                {{ $zaehler['unbestaetigt'] === 1 ? 'Eine Nachricht wartet' : $zaehler['unbestaetigt'].' Nachrichten warten' }}
                auf Ihre Lesebestätigung. Sie sind in der Liste gelb markiert.
            </div>
        </div>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <nav class="nachr-segment" aria-label="Ansicht" data-tour="nachrichten-archiv">
            <a href="{{ route('groups.posts', $group->name) }}" class="{{ $archiv ? '' : 'is-active' }}">
                <i class="far fa-newspaper"></i> Aktuell
            </a>
            <a href="{{ route('groups.posts', [$group->name, 'archiv' => 1]) }}" class="{{ $archiv ? 'is-active' : '' }}">
                <i class="fas fa-archive"></i> Archiv
            </a>
        </nav>
        <span class="text-xs text-gray-500">{{ $posts->total() }} {{ $posts->total() === 1 ? 'Nachricht' : 'Nachrichten' }}</span>
    </div>

    <section class="nachr-card" data-tour="nachrichten-liste">
        @if($posts->isEmpty())
            <div class="nachr-empty">
                <i class="far fa-newspaper"></i>
                <p>{{ $archiv ? 'Keine archivierten Nachrichten.' : 'Noch keine Nachrichten in dieser Gruppe.' }}</p>
            </div>
        @else
            <div class="nachr-list">
                @foreach($posts as $post)
                    @include('posts.partials.eintrag', ['post' => $post, 'zurueck' => url()->full()])
                @endforeach
            </div>
        @endif
    </section>

    @if($posts->hasPages())
        <div class="mt-4">{{ $posts->links() }}</div>
    @endif
</div>

<x-tour id="nachrichten" :version="1" :steps="[
    ['title' => 'Nachrichten der Gruppe', 'text' => 'Hier stehen alle Nachrichten an diese Gruppe – auch ausführliche Informationen mit Anhängen. Neue, noch nicht geöffnete Nachrichten sind blau markiert.'],
    ['target' => 'nachrichten-liste', 'title' => 'Lesebestätigung', 'text' => 'Gelb markierte Nachrichten warten auf Ihre Bestätigung. Öffnen Sie die Nachricht und klicken Sie auf „Gelesen und verstanden“. Wer die Bestätigung angefordert hat, sieht, wer bereits bestätigt hat.'],
    ['target' => 'nachrichten-archiv', 'title' => 'Archiv', 'text' => 'Ältere Nachrichten werden archiviert statt gelöscht und bleiben hier nachlesbar.'],
    $darfPosten ? ['target' => 'nachrichten-neu', 'title' => 'Neue Nachricht', 'text' => 'Verfassen Sie eine Nachricht für eine oder mehrere Gruppen. Sie entscheiden, ob sie zusätzlich auf dem Dashboard erscheint – und, falls Sie das Recht haben, ob eine Lesebestätigung nötig ist.'] : null,
]" />
@endsection
