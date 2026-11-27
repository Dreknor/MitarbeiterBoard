@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-wrapper">
    <a href="{{ route('wp.index') }}" class="wp-back">← Übersicht</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Vorlagen</h1>
            <p class="wp-subtitle">Vorlagen stehen allen Pädagog:innen zur Verfügung. Ändern und löschen kann sie, wer sie angelegt hat.</p>
        </div>
    </div>

    @if($vorlagen->isEmpty())
        <div class="wp-card wp-empty">
            <div class="wp-empty-icon">📄</div>
            <p class="font-semibold text-gray-700">Noch keine Vorlagen.</p>
            <p class="mt-1">Öffnen Sie einen Plan und speichern Sie ihn über „Als Vorlage“.</p>
        </div>
    @else
        <div class="wp-grid">
            @foreach($vorlagen as $vorlage)
                <article class="wp-plan is-vorlage">
                    <div class="flex-1 px-4 pt-3 pb-2">
                        <span class="wp-badge wp-badge-violet">Vorlage</span>
                        <h3 class="mt-1.5 text-base font-bold text-gray-900">{{ $vorlage->vorlage_name ?? $vorlage->name }}</h3>
                        <p class="mt-1 text-xs text-gray-500">
                            {{ $vorlage->planFaecher->count() }} Fächer · {{ $vorlage->planFaecher->sum(fn ($pf) => $pf->aufgaben->count()) }} Aufgaben
                        </p>
                        <p class="mt-0.5 text-xs text-gray-400">
                            {{ $vorlage->created_at?->format('d.m.Y') }}@if($vorlage->creator) · {{ $vorlage->creator->name }}@endif
                        </p>
                        <p class="mt-2 text-xs text-gray-500">{{ $vorlage->planFaecher->map->display_name->join(', ') }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 border-t border-gray-100 px-3 py-2">
                        <a href="{{ route('wp.create', ['vorlage' => $vorlage->id]) }}" class="wp-btn wp-btn-success wp-btn-sm">+ Plan daraus</a>
                        @can('update', $vorlage)
                            <a href="{{ route('wp.edit', $vorlage) }}" class="wp-btn wp-btn-secondary wp-btn-sm">Bearbeiten</a>
                            <form method="POST" action="{{ route('wp.vorlagen.destroy', $vorlage) }}" class="ml-auto"
                                  onsubmit="return confirm('Vorlage „{{ addslashes($vorlage->vorlage_name ?? $vorlage->name) }}“ löschen?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="wp-btn wp-btn-danger-ghost wp-btn-sm">Löschen</button>
                            </form>
                        @else
                            <a href="{{ route('wp.show', $vorlage) }}" class="wp-btn wp-btn-ghost wp-btn-sm">Ansehen</a>
                        @endcan
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</div>
@endsection
