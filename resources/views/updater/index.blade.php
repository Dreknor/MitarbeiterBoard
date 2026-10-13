@extends('layouts.app')

@section('title', 'Updater')
@section('site-title', 'Updater')

@push('css')
    @vite(['resources/css/updater.css'])
@endpush

@push('js')
    @vite(['resources/js/updater.js'])
@endpush

@php
    $passed = collect($checks)->every(fn ($c) => $c['ok'] || ! $c['blocking']);
    $failedChecks = collect($checks)->filter(fn ($c) => ! $c['ok'] && $c['blocking'])->count();
    $warnChecks = collect($checks)->filter(fn ($c) => ! $c['ok'] && ! $c['blocking'])->count();
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value) : null;
@endphp

@section('content')
<div class="upd-wrapper"
     x-data="updater({
        url: @js(route('updater.status')),
        status: @js($status),
        log: @js($log),
     })">

    {{-- Kopfzeile --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <div class="min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Anwendungsupdater</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Neue Versionen vom Git-Server einspielen – mit Wartungsmodus, Abhängigkeiten und Migrationen.
            </p>
        </div>
        <form action="{{ route('updater.check') }}" method="post">
            @csrf
            <button type="submit" class="upd-btn upd-btn-secondary" @disabled($active)>
                <i class="fas fa-sync-alt"></i> Nach Updates suchen
            </button>
        </form>
    </div>

    {{-- Zusammenfassung --}}
    @if($active)
        <div class="upd-hero upd-hero-running mb-6">
            <div class="upd-hero-icon"><i class="fas fa-cog fa-spin"></i></div>
            <div class="min-w-0 flex-1 basis-60">
                <h2 class="text-lg text-indigo-900">Update läuft</h2>
                <p class="text-sm text-indigo-800/80">
                    Die Anwendung ist für alle anderen im Wartungsmodus. Diese Seite aktualisiert sich automatisch.
                </p>
            </div>
        </div>
    @elseif(! $version)
        <div class="upd-hero upd-hero-error mb-6">
            <div class="upd-hero-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="min-w-0 flex-1 basis-60">
                <h2 class="text-lg text-red-900">Installierter Stand unbekannt</h2>
                <p class="text-sm text-red-800/80">Der Stand konnte nicht ermittelt werden (kein Git-Checkout?).</p>
            </div>
        </div>
    @elseif(count($commits))
        <div class="upd-hero upd-hero-pending mb-6">
            <div class="upd-hero-icon"><i class="fas fa-download"></i></div>
            <div class="min-w-0 flex-1 basis-60">
                <h2 class="text-lg text-blue-900">
                    {{ count($commits) }} {{ count($commits) === 1 ? 'neue Änderung' : 'neue Änderungen' }} verfügbar
                </h2>
                <p class="text-sm text-blue-800/80">
                    @if($passed)
                        Die Vorabprüfung ist bestanden – das Update kann gestartet werden.
                    @else
                        Vor dem Update müssen {{ $failedChecks }} Punkt(e) der Vorabprüfung behoben werden.
                    @endif
                </p>
            </div>
            @if($passed)
                <button type="button" class="upd-btn upd-btn-primary w-full sm:w-auto" @click="confirmOpen = true">
                    <i class="fas fa-rocket"></i> Update starten
                </button>
            @endif
        </div>
    @else
        <div class="upd-hero upd-hero-current mb-6">
            <div class="upd-hero-icon"><i class="fas fa-check"></i></div>
            <div class="min-w-0 flex-1 basis-60">
                <h2 class="text-lg text-emerald-900">Die Anwendung ist aktuell</h2>
                <p class="text-sm text-emerald-800/80">
                    Keine neuen Änderungen bekannt. „Nach Updates suchen“ fragt den Server erneut ab.
                </p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 items-start">

        {{-- Linke Spalte: Änderungen & Protokoll --}}
        <div class="lg:col-span-3 flex flex-col gap-6 min-w-0">

            {{-- Verfügbare Änderungen --}}
            <section class="upd-card">
                <div class="upd-card-head">
                    <div class="min-w-0">
                        <h2>Verfügbare Änderungen</h2>
                        @if($remoteRef)
                            <p class="text-xs text-gray-500 mt-0.5">gegenüber <code>{{ $remoteRef }}</code></p>
                        @endif
                    </div>
                    @if(count($commits))
                        <span class="upd-badge upd-badge-running">{{ count($commits) }}</span>
                    @endif
                </div>
                <div class="upd-card-body">
                    @if(count($commits))
                        <ol class="max-h-96 overflow-y-auto pr-1">
                            @foreach($commits as $commit)
                                <li class="upd-commit">
                                    <p class="text-sm font-medium text-gray-900">{{ $commit['subject'] }}</p>
                                    <p class="text-xs text-gray-500 mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <code>{{ $commit['short'] }}</code>
                                        <span>{{ $commit['author'] }}</span>
                                        <span class="text-gray-300">·</span>
                                        <span>{{ $commit['date']?->format('d.m.Y H:i') }}</span>
                                    </p>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="flex flex-col items-center text-center py-8 text-gray-500">
                            <div class="w-12 h-12 rounded-2xl bg-gray-100 flex items-center justify-center text-gray-400 text-lg mb-3">
                                <i class="fas fa-code-branch"></i>
                            </div>
                            <p class="text-sm">Keine neuen Änderungen bekannt.</p>
                        </div>
                    @endif
                </div>
            </section>

            {{-- Letztes Update: Status, Fortschritt, Protokoll --}}
            <section class="upd-card {{ $active ? 'order-first' : '' }}">
                <div class="upd-card-head">
                    <h2>{{ $active ? 'Aktuelles Update' : 'Letztes Update' }}</h2>
                    <span class="upd-badge" :class="'upd-badge-' + status.state">
                        <i class="fas fa-circle-notch fa-spin" x-show="active" x-cloak></i>
                        <span x-text="stateLabel"></span>
                    </span>
                </div>
                <div class="upd-card-body flex flex-col gap-4">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3"
                         x-show.important="active || phase >= 0" x-cloak>
                        <ol>
                            <template x-for="(p, index) in phases" :key="index">
                                <li class="upd-phase" :class="phaseClass(index)">
                                    <span class="upd-phase-dot">
                                        <template x-if="phaseClass(index) === 'upd-phase-done'"><i class="fas fa-check"></i></template>
                                        <template x-if="phaseClass(index) === 'upd-phase-failed'"><i class="fas fa-times"></i></template>
                                        <template x-if="phaseClass(index) === 'upd-phase-active'"><i class="fas fa-circle-notch fa-spin"></i></template>
                                        <template x-if="phaseClass(index) === 'upd-phase-todo'"><span x-text="index + 1"></span></template>
                                    </span>
                                    <span x-text="p.label"></span>
                                </li>
                            </template>
                        </ol>
                        <div class="text-sm text-gray-600 sm:pt-1.5" x-show="active">
                            <p class="text-xs uppercase tracking-wide text-gray-400 font-semibold mb-1">Aktueller Schritt</p>
                            <p class="font-medium text-gray-900" x-text="waiting ? 'Warte auf Antwort der Anwendung …' : (status.step || '…')"></p>
                        </div>
                    </div>

                    <div x-show.important="status.message" x-cloak
                         class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm"
                         :class="status.state === 'failed' ? 'bg-red-50 text-red-800' : (status.state === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-gray-50 text-gray-700')">
                        <i class="fas mt-0.5" :class="status.state === 'failed' ? 'fa-times-circle' : (status.state === 'success' ? 'fa-check-circle' : 'fa-info-circle')"></i>
                        <span x-text="status.message"></span>
                    </div>

                    <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm" x-show.important="status.started_at" x-cloak>
                        <div>
                            <dt class="text-xs text-gray-400">Gestartet</dt>
                            <dd class="m-0 text-gray-800">
                                {{ $date($status['started_at'] ?? null)?->format('d.m.Y H:i') }}
                                @if(! empty($status['user'])) <span class="text-gray-500">von {{ $status['user'] }}</span>@endif
                            </dd>
                        </div>
                        @if(! empty($status['finished_at']))
                            <div>
                                <dt class="text-xs text-gray-400">Beendet</dt>
                                <dd class="m-0 text-gray-800">{{ $date($status['finished_at'])->format('d.m.Y H:i') }}</dd>
                            </div>
                        @endif
                        @if(! empty($status['from']) && ! empty($status['to']))
                            <div>
                                <dt class="text-xs text-gray-400">Versionen</dt>
                                <dd class="m-0">
                                    <code>{{ substr($status['from'], 0, 7) }}</code>
                                    <i class="fas fa-arrow-right text-xs text-gray-400 mx-1"></i>
                                    <code>{{ substr($status['to'], 0, 7) }}</code>
                                </dd>
                            </div>
                        @endif
                    </dl>

                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <h3 class="text-sm text-gray-700">Protokoll</h3>
                            <button type="button" class="text-xs text-gray-500 hover:text-gray-800"
                                    @click="scrollLog(true)" x-show="log">
                                <i class="fas fa-arrow-down"></i> Zum Ende
                            </button>
                        </div>
                        <pre class="upd-console" x-ref="log"
                             x-text="log || 'Noch kein Log vorhanden.'">{{ $log ?: 'Noch kein Log vorhanden.' }}</pre>
                    </div>
                </div>
            </section>
        </div>

        {{-- Rechte Spalte: Installierter Stand, Vorabprüfung --}}
        <div class="lg:col-span-2 flex flex-col gap-6 min-w-0">

            <section class="upd-card">
                <div class="upd-card-head">
                    <h2>Installierter Stand</h2>
                    @if($version && $version['branch'])
                        <span class="upd-badge upd-badge-idle"><i class="fas fa-code-branch"></i> {{ $version['branch'] }}</span>
                    @endif
                </div>
                <div class="upd-card-body">
                    @if($version)
                        <p class="text-sm font-medium text-gray-900">{{ $version['subject'] }}</p>
                        <p class="text-xs text-gray-500 mt-1 flex flex-wrap items-center gap-2">
                            <code>{{ $version['short'] }}</code>
                            <span>{{ $version['date']?->format('d.m.Y H:i') }}</span>
                        </p>
                    @else
                        <p class="text-sm text-red-600">Der Stand konnte nicht ermittelt werden.</p>
                    @endif
                </div>
            </section>

            @unless($active)
                <section class="upd-card">
                    <div class="upd-card-head">
                        <h2>Vorabprüfung</h2>
                        @if(! $passed)
                            <span class="upd-badge upd-badge-failed">{{ $failedChecks }} offen</span>
                        @elseif($warnChecks)
                            <span class="upd-badge bg-amber-50 text-amber-700">{{ $warnChecks }} Hinweis(e)</span>
                        @else
                            <span class="upd-badge upd-badge-success"><i class="fas fa-check"></i> bestanden</span>
                        @endif
                    </div>
                    <div class="upd-card-body">
                        <ul>
                            @foreach($checks as $check)
                                <li class="upd-check">
                                    @if($check['ok'])
                                        <span class="upd-check-icon bg-emerald-50 text-emerald-600"><i class="fas fa-check"></i></span>
                                    @elseif($check['blocking'])
                                        <span class="upd-check-icon bg-red-50 text-red-600"><i class="fas fa-times"></i></span>
                                    @else
                                        <span class="upd-check-icon bg-amber-50 text-amber-600"><i class="fas fa-exclamation"></i></span>
                                    @endif
                                    <div class="min-w-0">
                                        <p class="text-sm {{ $check['ok'] ? 'text-gray-700' : 'text-gray-900 font-medium' }}">{{ $check['label'] }}</p>
                                        @if($check['hint'])
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $check['hint'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </section>

                <section class="upd-card">
                    <div class="upd-card-body flex flex-col gap-4">
                        <div class="flex items-start gap-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            <i class="fas fa-shield-alt mt-0.5 text-amber-600"></i>
                            <p>
                                Vor dem Update sollte ein aktuelles Backup von Datenbank und Dateien vorhanden sein.
                                Schlägt composer oder der Frontend-Build fehl, wird automatisch auf den vorherigen Stand
                                zurückgesetzt; Datenbankmigrationen werden nicht zurückgerollt.
                            </p>
                        </div>
                        <button type="button" class="upd-btn upd-btn-primary w-full"
                                @click="confirmOpen = true" @disabled(! $passed || ! count($commits))>
                            <i class="fas fa-download"></i> Update starten
                        </button>
                        @if($passed && ! count($commits))
                            <p class="text-xs text-gray-500 text-center">Keine neuen Änderungen – zuerst nach Updates suchen.</p>
                        @elseif(! $passed)
                            <p class="text-xs text-red-600 text-center">Bitte zuerst die markierten Punkte beheben.</p>
                        @endif
                    </div>
                </section>
            @endunless
        </div>
    </div>

    {{-- Bestätigungsdialog --}}
    @if(! $active && $passed && count($commits))
        <div class="upd-modal-backdrop" x-show.important="confirmOpen" x-cloak x-transition.opacity
             @keydown.escape.window="confirmOpen = false" @click.self="confirmOpen = false">
            <div class="upd-modal" role="dialog" aria-modal="true" aria-labelledby="upd-confirm-title">
                <div class="px-6 pt-6 pb-4 flex items-start gap-4">
                    <div class="shrink-0 w-11 h-11 rounded-2xl bg-blue-100 text-blue-600 flex items-center justify-center">
                        <i class="fas fa-rocket"></i>
                    </div>
                    <div class="min-w-0">
                        <h2 id="upd-confirm-title" class="text-lg text-gray-900">Update jetzt starten?</h2>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ count($commits) }} {{ count($commits) === 1 ? 'Änderung wird' : 'Änderungen werden' }} eingespielt.
                            Die Anwendung ist währenddessen im Wartungsmodus und für alle anderen nicht erreichbar.
                        </p>
                    </div>
                </div>
                <form action="{{ route('updater.update') }}" method="post"
                      class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 px-6 py-4 bg-gray-50"
                      x-data="{ sending: false }" @submit="sending = true">
                    @csrf
                    <button type="button" class="upd-btn upd-btn-secondary" @click="confirmOpen = false">Abbrechen</button>
                    <button type="submit" class="upd-btn upd-btn-primary" :disabled="sending">
                        <i class="fas" :class="sending ? 'fa-circle-notch fa-spin' : 'fa-download'"></i>
                        Update starten
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
