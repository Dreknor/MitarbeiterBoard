@extends('layouts.app')

@push('css')
    @vite('resources/css/calendar.css')
@endpush

@section('content')
<div class="px-4 py-4">

    {{-- ─── Seiten-Header ─────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">📋 Sync-Logs</h1>
            <p class="text-sm text-gray-500 mt-0.5">
                <a href="{{ route('calendar.admin') }}" class="text-blue-600 hover:underline">← Zurück zur Kalender-Verwaltung</a>
            </p>
        </div>
    </div>

    {{-- ─── Filter ─────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('calendar.admin.logs') }}"
          class="flex flex-wrap items-end gap-3 mb-5 bg-white border border-gray-200 rounded-lg px-5 py-4 shadow-sm">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">Kalender</label>
            <select name="kalender"
                    class="rounded-md border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300 min-w-[160px]">
                <option value="">Alle Kalender</option>
                @foreach($kalender as $cal)
                    <option value="{{ $cal->id }}" {{ $selectedKalender == $cal->id ? 'selected' : '' }}>
                        {{ $cal->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">Aktion</label>
            <select name="aktion"
                    class="rounded-md border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-300 min-w-[140px]">
                <option value="">Alle Aktionen</option>
                @foreach($aktionen as $aktion)
                    <option value="{{ $aktion }}" {{ $selectedAktion === $aktion ? 'selected' : '' }}>
                        {{ $aktion }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-md transition-colors">
                Filtern
            </button>
            @if($selectedKalender || $selectedAktion)
                <a href="{{ route('calendar.admin.logs') }}"
                   class="px-3.5 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-md border border-gray-300 transition-colors">
                    Filter zurücksetzen
                </a>
            @endif
        </div>
    </form>

    {{-- ─── Logs-Tabelle ───────────────────────────────────────────────── --}}
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
        <div class="px-5 py-3 border-b border-gray-200 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-gray-700">
                {{ $logs->total() }} Einträge
                @if($selectedKalender || $selectedAktion)
                    (gefiltert)
                @endif
            </h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">Datum / Uhrzeit</th>
                        <th class="px-4 py-3 text-left">Kalender</th>
                        <th class="px-4 py-3 text-left">Aktion</th>
                        <th class="px-4 py-3 text-left">Details</th>
                        <th class="px-4 py-3 text-left">Nutzer</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($logs as $log)
                        <tr class="{{ $log->aktion === 'error' ? 'bg-red-50' : 'hover:bg-gray-50' }} transition-colors">
                            <td class="px-4 py-3 text-gray-600 text-xs whitespace-nowrap">
                                <span title="{{ $log->created_at->format('d.m.Y H:i:s') }}">
                                    {{ $log->created_at->format('d.m.Y H:i') }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $log->kalender?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $aktionBadge = match($log->aktion) {
                                        'error'         => 'bg-red-100 text-red-700 border-red-300',
                                        'sync_start'    => 'bg-gray-100 text-gray-600 border-gray-300',
                                        'sync_complete' => 'bg-green-100 text-green-700 border-green-300',
                                        'create'        => 'bg-blue-100 text-blue-700 border-blue-300',
                                        'update'        => 'bg-amber-100 text-amber-700 border-amber-300',
                                        'delete'        => 'bg-orange-100 text-orange-700 border-orange-300',
                                        default         => 'bg-gray-100 text-gray-600 border-gray-300',
                                    };
                                @endphp
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium border {{ $aktionBadge }}">
                                    {{ $log->aktion }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 max-w-[300px]">
                                @if($log->details)
                                    <details class="text-xs">
                                        <summary class="cursor-pointer text-blue-600 hover:text-blue-800">
                                            Details anzeigen
                                        </summary>
                                        <pre class="mt-1 p-2 rounded bg-gray-100 overflow-x-auto text-gray-700 text-xs leading-relaxed">{{ json_encode($log->details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                    </details>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $log->benutzer?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-500 text-xs font-mono">
                                {{ $log->ip_adresse ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-gray-400">
                                Keine Sync-Log-Einträge gefunden.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
            <div class="px-5 py-3 border-t border-gray-200">
                {{ $logs->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

