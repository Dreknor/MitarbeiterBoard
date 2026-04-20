{{-- Abwesenheiten-Card v2 – nur Body-Inhalt (cardWrapper übernimmt Header) --}}
{{-- E6: Kompakter Header "Heute abwesend: N Personen" --}}
@php
    $heute = $absences->filter(fn($a) => $a->start->isToday() || ($a->start->isPast() && $a->end->isFuture()) || $a->end->isToday());
@endphp

{{-- Neue Abwesenheit Formular --}}
<div x-data="{ showForm: false }">

    {{-- Heute abwesend Zusammenfassung --}}
    @if($heute->isNotEmpty())
        <div class="px-4 py-2 bg-amber-50 border-b border-amber-100 flex items-center justify-between">
            <span class="text-xs font-semibold text-amber-700">
                <i class="fas fa-user-slash mr-1"></i>
                Heute abwesend: {{ $heute->count() }} {{ $heute->count() === 1 ? 'Person' : 'Personen' }}
            </span>
            <span class="text-xs text-amber-600">
                {{ $heute->pluck('user.name')->implode(', ') }}
            </span>
        </div>
    @endif

    {{-- "Neue Abwesenheit"-Button --}}
    <div class="px-4 py-3 border-b border-gray-100">
        <button @click="showForm = !showForm"
                class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-medium
                       bg-blue-600 text-white hover:bg-blue-700 transition-colors">
            <i class="fas fa-plus"></i>
            <span x-text="showForm ? 'Abbrechen' : 'Neue Abwesenheit eintragen'"></span>
        </button>
    </div>

    {{-- Inline-Formular --}}
    <div x-show="showForm" x-cloak class="px-4 py-3 bg-gray-50 border-b border-gray-100">
        <form action="{{ url('absences') }}" method="post" class="space-y-3">
            @csrf
            @if(auth()->user()->can('create absences'))
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Mitarbeiter</label>
                    <select name="users_id" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @foreach(\App\Models\User::orderBy('name')->get() as $user)
                            <option value="{{ $user->id }}" {{ $user->id == auth()->id() ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <input type="hidden" name="users_id" value="{{ auth()->id() }}">
            @endif

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Von</label>
                    <input type="date" name="start" required
                           value="{{ old('start', now()->format('Y-m-d')) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Bis</label>
                    <input type="date" name="end" required
                           value="{{ old('end', now()->format('Y-m-d')) }}"
                           class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">Vertretungsplan</label>
                    <select name="showVertretungsplan" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                        <option value="1">anzeigen</option>
                        <option value="0">nicht anzeigen</option>
                    </select>
                </div>
                @if(auth()->user()->can('create absences'))
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Krankenschein</label>
                        <select name="sick_note_required" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                            <option value="0">nicht nötig</option>
                            <option value="1">erforderlich</option>
                        </select>
                    </div>
                @endif
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-700 mb-1">Grund</label>
                <input type="text" name="reason" required
                       value="{{ old('reason', settings('absence_reason_default', 'absences')) }}"
                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500">
            </div>

            <button type="submit"
                    class="w-full py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                <i class="fas fa-save mr-1"></i> Speichern
            </button>
        </form>
    </div>

    {{-- Abwesenheits-Liste mit Alpine Suchfilter --}}
    @if($absences->count() > 0)
        <div x-data="{ search: '' }">
            <div class="px-4 py-2 border-b border-gray-100">
                <input x-model="search" type="text" placeholder="Filtern…"
                       class="w-full text-xs border border-gray-200 rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="divide-y divide-gray-100 max-h-64 overflow-y-auto">
                @foreach($absences as $absence)
                    <div class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50"
                         x-show="search === '' || '{{ strtolower($absence->user->name . ' ' . $absence->reason) }}'.includes(search.toLowerCase())">
                        <div class="shrink-0 w-7 h-7 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-xs font-bold">
                            {{ strtoupper(substr($absence->user->name ?? 'U', 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium text-gray-800 truncate">{{ $absence->user->name }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $absence->start->format('d.m.Y') }}
                                @if($absence->end->gt($absence->start))
                                    – {{ $absence->end->format('d.m.Y') }}
                                @endif
                                @if($absence->showVertretungsplan)
                                    <i class="fas fa-columns text-blue-400 ml-1" title="Im Vertretungsplan sichtbar"></i>
                                @endif
                            </div>
                        </div>
                        <div class="shrink-0 flex items-center gap-2">
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs
                                {{ $absence->start->isToday() || ($absence->start->isPast() && $absence->end->gte(now())) ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ \Illuminate\Support\Str::limit($absence->reason, 15) }}
                            </span>
                            @if(auth()->user()->can('delete absences') || $absence->creator_id == auth()->id())
                                <a href="{{ url('absences/' . $absence->id . '/delete') }}"
                                   class="text-red-400 hover:text-red-600 no-underline"
                                   title="Löschen"
                                   onclick="return confirm('Abwesenheit löschen?')">
                                    <i class="fas fa-trash text-xs"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        <div class="px-4 py-8 text-center text-gray-400 text-sm">
            <i class="fas fa-user-check text-2xl mb-2 block opacity-40"></i>
            Keine aktuellen Abwesenheiten
        </div>
    @endif

</div>

{{-- Footer --}}
<div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between">
    <a href="{{ url('absences') }}"
       class="flex items-center gap-1 text-sm text-blue-600 hover:text-blue-800 no-underline font-medium">
        Alle Abwesenheiten →
    </a>
    <div class="flex items-center gap-2">
        <a href="{{ url('absences/abo/' . (auth()->user()->absence_abo_daily ? 'daily' : 'daily')) }}"
           class="text-xs text-gray-500 hover:text-gray-700 no-underline"
           title="{{ auth()->user()->absence_abo_daily ? 'Tägl. E-Mail deaktivieren' : 'Tägl. E-Mail aktivieren' }}">
            <i class="fas fa-bell{{ auth()->user()->absence_abo_daily ? '-slash' : '' }}"></i>
        </a>
        @can('export absence')
            <a href="{{ url('absences/export') }}"
               class="text-xs text-gray-500 hover:text-gray-700 no-underline"
               title="Excel-Export">
                <i class="fas fa-file-export"></i>
            </a>
        @endcan
    </div>
</div>

