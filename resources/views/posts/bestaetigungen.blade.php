@extends('layouts.app')

@push('css')
    @vite(['resources/css/posts.css', 'resources/js/posts.js'])
@endpush

@php
    $statusLabel = \App\Exports\PostBestaetigungenExport::STATUS;
    $statusBadge = ['bestaetigt' => 'nachr-badge-green', 'offen' => 'nachr-badge-gray', 'ueberfaellig' => 'nachr-badge-red'];
    $prozent = $statistik['gesamt'] > 0 ? (int) round($statistik['bestaetigt'] / $statistik['gesamt'] * 100) : 0;
@endphp

@section('content')
<div class="nachr-wrapper" x-data="{ filter: 'alle' }">
    <a href="{{ route('posts.show', $post) }}" class="nachr-back"><i class="fas fa-arrow-left"></i> Zur Nachricht</a>

    <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="nachr-page-title">Lesebestätigungen</h1>
            <p class="nachr-page-sub truncate">{{ $post->header }} · {{ $post->groups->pluck('name')->join(', ') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('posts.confirmations.excel', $post) }}" class="nachr-btn nachr-btn-secondary nachr-btn-sm"><i class="fas fa-file-excel"></i> Excel</a>
            <a href="{{ route('posts.confirmations.pdf', $post) }}" target="_blank" rel="noopener" class="nachr-btn nachr-btn-secondary nachr-btn-sm"><i class="fas fa-file-pdf"></i> PDF</a>
            @can('remind', $post)
                <form method="post" action="{{ route('posts.remind', $post) }}"
                      onsubmit="return confirm('Alle, die noch nicht bestätigt haben, jetzt erinnern?')">
                    @csrf
                    <button type="submit" class="nachr-btn nachr-btn-primary nachr-btn-sm"
                            @disabled(! $darfHeuteErinnern || $statistik['bestaetigt'] === $statistik['gesamt'])
                            title="{{ $darfHeuteErinnern ? 'Erinnerung an alle Offenen senden' : 'Heute wurde bereits erinnert' }}">
                        <i class="fas fa-bell"></i> Offene erinnern
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="grid gap-3 grid-cols-2 lg:grid-cols-4 mb-5">
        <div class="nachr-stat">
            <span class="nachr-stat-icon bg-blue-50 text-blue-600"><i class="fas fa-users"></i></span>
            <div><div class="nachr-stat-value">{{ $statistik['gesamt'] }}</div><div class="nachr-stat-label">Empfänger:innen</div></div>
        </div>
        <div class="nachr-stat">
            <span class="nachr-stat-icon bg-emerald-50 text-emerald-600"><i class="fas fa-check"></i></span>
            <div><div class="nachr-stat-value">{{ $statistik['bestaetigt'] }}</div><div class="nachr-stat-label">bestätigt ({{ $prozent }} %)</div></div>
        </div>
        <div class="nachr-stat">
            <span class="nachr-stat-icon bg-gray-100 text-gray-500"><i class="far fa-clock"></i></span>
            <div><div class="nachr-stat-value">{{ $statistik['offen'] }}</div><div class="nachr-stat-label">offen</div></div>
        </div>
        <div class="nachr-stat">
            <span class="nachr-stat-icon bg-red-50 text-red-600"><i class="fas fa-exclamation"></i></span>
            <div><div class="nachr-stat-value">{{ $statistik['ueberfaellig'] }}</div><div class="nachr-stat-label">überfällig</div></div>
        </div>
    </div>

    <div class="nachr-fortschritt mb-5" role="progressbar" aria-valuenow="{{ $prozent }}" aria-valuemin="0" aria-valuemax="100">
        <span style="width: {{ $prozent }}%"></span>
    </div>

    <section class="nachr-card">
        <div class="nachr-card-head">
            <nav class="nachr-segment" aria-label="Filter">
                <a href="#" @click.prevent="filter = 'alle'" :class="{ 'is-active': filter === 'alle' }">Alle</a>
                <a href="#" @click.prevent="filter = 'offen'" :class="{ 'is-active': filter === 'offen' }">Offen</a>
                <a href="#" @click.prevent="filter = 'bestaetigt'" :class="{ 'is-active': filter === 'bestaetigt' }">Bestätigt</a>
            </nav>
            <span class="text-xs text-gray-500">
                @if($post->manuell_erinnert_at)
                    Zuletzt manuell erinnert: {{ $post->manuell_erinnert_at->format('d.m.Y H:i') }}
                @endif
            </span>
        </div>

        @if($liste->isEmpty())
            <div class="nachr-empty"><i class="fas fa-users"></i><p>Keine Empfänger:innen.</p></div>
        @else
            <div class="overflow-x-auto">
                <table class="nachr-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Status</th>
                            <th>bestätigt am</th>
                            <th>Frist</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($liste as $zeile)
                            <tr x-show="filter === 'alle' || (filter === 'bestaetigt') === {{ $zeile['status'] === 'bestaetigt' ? 'true' : 'false' }}">
                                <td>
                                    <span class="font-medium text-gray-900">{{ $zeile['user']->name }}</span>
                                    @if($zeile['ausgeschieden'])
                                        <span class="block text-xs text-gray-400">nicht mehr in der Gruppe</span>
                                    @endif
                                </td>
                                <td><span class="nachr-badge {{ $statusBadge[$zeile['status']] }}">{{ $statusLabel[$zeile['status']] }}</span></td>
                                <td class="whitespace-nowrap">{{ $zeile['bestaetigt_at']?->format('d.m.Y H:i') ?? '–' }}</td>
                                <td class="whitespace-nowrap">{{ $zeile['frist']?->format('d.m.Y') ?? '–' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection
