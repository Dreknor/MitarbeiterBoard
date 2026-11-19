@extends('layouts.app')

@push('css')
    @vite(['resources/css/posts.css', 'resources/js/posts.js'])
@endpush

@php
    $dateien = $post->getMedia('files');
    $bilder = $post->getMedia('images');
    $frist = $status?->frist ?? $post->bestaetigung_frist;
    $darfBearbeiten = auth()->user()->can('update', $post);
@endphp

@section('content')
<div class="nachr-wrapper">
    <a href="{{ $zurueck }}" class="nachr-back"><i class="fas fa-arrow-left"></i> Zurück</a>

    @if($post->archived)
        <div class="nachr-alert nachr-alert-blue mb-4">
            <i class="fas fa-archive mt-0.5"></i>
            <div>Diese Nachricht ist archiviert{{ $post->archiviert_at ? ' (seit '.$post->archiviert_at->format('d.m.Y').')' : '' }}.</div>
        </div>
    @elseif(! $post->released)
        <div class="nachr-alert nachr-alert-amber mb-4">
            <i class="fas fa-pen mt-0.5"></i>
            <div>Entwurf – nur für Sie sichtbar, bis Sie die Nachricht veröffentlichen.</div>
        </div>
    @endif

    <article class="nachr-card">
        <header class="nachr-card-body border-b border-gray-100">
            <div class="flex flex-wrap items-center gap-2 mb-2">
                @foreach($post->groups as $gruppe)
                    <span class="nachr-badge nachr-badge-blue"><i class="fas fa-users"></i> {{ $gruppe->name }}</span>
                @endforeach
                @if($post->bestaetigung_erforderlich)
                    <span class="nachr-badge nachr-badge-amber"><i class="fas fa-signature"></i> Lesebestätigung{{ $post->bestaetigung_frist ? ' bis '.$post->bestaetigung_frist->format('d.m.Y') : '' }}</span>
                @endif
                @if(! $post->auf_dashboard)
                    <span class="nachr-badge nachr-badge-gray" title="Erscheint nicht auf dem Dashboard"><i class="fas fa-eye-slash"></i> nur in der Gruppe</span>
                @endif
                @if($post->sichtbar_bis)
                    <span class="nachr-badge nachr-badge-gray"><i class="far fa-calendar"></i> sichtbar bis {{ $post->sichtbar_bis->format('d.m.Y') }}</span>
                @endif
            </div>
            <h1 class="nachr-page-title">{{ $post->header }}</h1>
            <div class="nachr-meta mt-2">
                <span class="nachr-avatar w-6 h-6 text-[10px]" aria-hidden="true">{{ mb_substr($post->author->name ?? '?', 0, 1) }}</span>
                <span class="font-medium text-gray-700">{{ $post->author->name }}</span>
                <span aria-hidden="true">·</span>
                <span>{{ $post->datum()?->format('d.m.Y H:i') }} Uhr</span>
                @if($post->updated_at && $post->datum() && $post->updated_at->gt($post->datum()->copy()->addMinutes(5)))
                    <span aria-hidden="true">·</span>
                    <span>bearbeitet {{ $post->updated_at->format('d.m.Y H:i') }}</span>
                @endif
            </div>
        </header>

        @if(filled($post->kurzfassung))
            <div class="nachr-card-body border-b border-gray-100 bg-gray-50/60">
                <p class="text-sm sm:text-base font-medium text-gray-700">{{ $post->kurzfassung }}</p>
            </div>
        @endif

        @if($post->text_html !== '')
            <div class="nachr-card-body">
                <div class="nachr-prose">{!! $post->text_html !!}</div>
            </div>
        @endif

        @if($bilder->isNotEmpty() || $dateien->isNotEmpty())
            <div class="nachr-card-body border-t border-gray-100 flex flex-col gap-3">
                <h2 class="text-sm font-semibold text-gray-700"><i class="fas fa-paperclip text-gray-400"></i> Anhänge</h2>
                @if($bilder->isNotEmpty())
                    <div class="nachr-bilder">
                        @foreach($bilder as $bild)
                            <a href="{{ route('posts.files', [$post, $bild]) }}" target="_blank" rel="noopener">
                                <img src="{{ route('posts.files', [$post, $bild]) }}" alt="{{ $bild->name }}" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @endif
                @if($dateien->isNotEmpty())
                    <ul class="grid gap-2 sm:grid-cols-2">
                        @foreach($dateien as $datei)
                            <li>
                                <a href="{{ route('posts.files', [$post, $datei]) }}" class="nachr-file" target="_blank" rel="noopener">
                                    <i class="fas {{ $datei->mime_type === 'application/pdf' ? 'fa-file-pdf' : 'fa-file' }}"></i>
                                    <span class="flex-1 min-w-0 truncate">{{ $datei->file_name }}</span>
                                    <span class="text-xs text-gray-400">{{ $datei->human_readable_size }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- Lesebestätigung der eigenen Person --}}
        @if($post->bestaetigung_erforderlich && $post->released)
            <div class="nachr-card-body border-t border-gray-100">
                @if($status?->bestaetigt_at)
                    <div class="nachr-alert nachr-alert-green">
                        <i class="fas fa-check-circle mt-0.5"></i>
                        <div>Sie haben die Kenntnisnahme am {{ $status->bestaetigt_at->format('d.m.Y') }} um {{ $status->bestaetigt_at->format('H:i') }} Uhr bestätigt.</div>
                    </div>
                @elseif($mussBestaetigen)
                    <form method="post" action="{{ route('posts.confirm', $post) }}" data-nachr-form
                          class="flex flex-col sm:flex-row sm:items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
                        @csrf
                        <div class="flex-1 text-sm text-amber-900">
                            <strong>Bitte bestätigen Sie, dass Sie diese Nachricht gelesen und verstanden haben.</strong>
                            @if($frist)
                                <span class="block mt-0.5">
                                    Frist: {{ $frist->format('d.m.Y') }}
                                    @if($frist->isPast() && ! $frist->isToday())<span class="font-semibold text-red-700"> – überfällig</span>@endif
                                </span>
                            @endif
                        </div>
                        <button type="submit" class="nachr-btn nachr-btn-success">
                            <i class="fas fa-check"></i> Gelesen und verstanden
                        </button>
                    </form>
                @endif
            </div>
        @endif

        @if($darfBearbeiten || $statistik)
            <footer class="nachr-card-foot">
                @if($statistik)
                    <a href="{{ route('posts.confirmations', $post) }}" class="nachr-btn nachr-btn-secondary nachr-btn-sm">
                        <i class="fas fa-tasks"></i>
                        Lesebestätigungen: {{ $statistik['bestaetigt'] }} / {{ $statistik['gesamt'] }}
                        @if($statistik['ueberfaellig'] > 0)
                            <span class="nachr-badge nachr-badge-red">{{ $statistik['ueberfaellig'] }} überfällig</span>
                        @endif
                    </a>
                @endif

                @if($darfBearbeiten)
                    <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
                        @can('release', $post)
                            <form method="post" action="{{ route('posts.release', $post) }}">
                                @csrf
                                <button type="submit" class="nachr-btn nachr-btn-success nachr-btn-sm"><i class="fas fa-paper-plane"></i> Veröffentlichen</button>
                            </form>
                        @endcan
                        <a href="{{ route('posts.edit', $post) }}" class="nachr-btn nachr-btn-secondary nachr-btn-sm"><i class="fas fa-pen"></i> Bearbeiten</a>
                        @can('archive', $post)
                            <form method="post" action="{{ route('posts.archive', $post) }}" onsubmit="return confirm('Nachricht archivieren? Sie bleibt im Archiv der Gruppe lesbar.')">
                                @csrf
                                <button type="submit" class="nachr-btn nachr-btn-secondary nachr-btn-sm"><i class="fas fa-archive"></i> Archivieren</button>
                            </form>
                        @endcan
                        @can('restore', $post)
                            <form method="post" action="{{ route('posts.restore', $post) }}">
                                @csrf
                                <button type="submit" class="nachr-btn nachr-btn-secondary nachr-btn-sm"><i class="fas fa-undo"></i> Wiederherstellen</button>
                            </form>
                        @endcan
                        @can('delete', $post)
                            <form method="post" action="{{ route('posts.destroy', $post) }}" onsubmit="return confirm('Entwurf endgültig löschen?')">
                                @csrf
                                @method('delete')
                                <button type="submit" class="nachr-btn nachr-btn-danger nachr-btn-sm"><i class="fas fa-trash"></i> Löschen</button>
                            </form>
                        @endcan
                    </div>
                @endif
            </footer>
        @endif
    </article>
</div>
@endsection
