﻿{{-- Fach-Block mit Aufgabenliste --}}
<div class="bg-white rounded-lg border border-gray-200 mb-4" data-sortable-fach
     x-data="{ open: true, confirmSync: false }">
    {{-- Fach-Header --}}
    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-t-lg border-b border-gray-200 cursor-pointer"
         @click="open = !open">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            <h3 class="font-semibold text-gray-800 text-sm">
                @if($planFach->fach && $planFach->fach->symbol_html)
                    {!! $planFach->fach->symbol_html !!}
                @endif
                {{ $planFach->display_name }}
            </h3>
            <span class="text-xs text-gray-400">({{ $planFach->aufgaben->count() }} Aufgaben)</span>
        </div>
        <div class="flex items-center gap-1" @click.stop>
            {{-- Sync-Button (nur fuer Kinderplaene mit Elternplan) --}}
            @if($plan->isSchuelerplan() && $plan->parent_plan_id)
                <button @click="confirmSync = true"
                        class="inline-flex items-center px-2 py-1 text-xs text-blue-600 hover:text-blue-800 hover:bg-blue-50 rounded transition-colors"
                        title="Aufgaben vom Klassenplan synchronisieren">
                    🔄 Sync
                </button>
                {{-- Bestaetigung --}}
                <div x-show="confirmSync" x-cloak
                     class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
                    <div class="bg-white rounded-lg shadow-xl p-6 max-w-md mx-4" @click.stop>
                        <h3 class="font-semibold text-gray-900 mb-2">Fach synchronisieren?</h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Alle bestehenden Aufgaben in <strong>„{{ $planFach->display_name }}"</strong> werden durch
                            die aktuellen Aufgaben des Klassenplans ersetzt.
                            <strong class="text-red-600">Diese Aktion kann nicht rueckgaengig gemacht werden.</strong>
                        </p>
                        <div class="flex gap-2 justify-end">
                            <button @click="confirmSync = false"
                                    class="px-3 py-2 text-sm text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                                Abbrechen
                            </button>
                            <form action="{{ route('wp.sync.fach', [$plan, $planFach->wp_fach_id]) }}" method="POST">
                                @csrf
                                <button type="submit"
                                        class="px-3 py-2 text-sm bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    Synchronisieren
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
            @canany(['create wochenplan', 'create Wochenplan'])
                {{-- Fach entfernen --}}
                <form method="POST" action="{{ route('wp.fach.remove', $planFach) }}"
                      onsubmit="return confirm('Fach und alle Aufgaben wirklich entfernen?')">
                    @csrf @method('DELETE')
                    <button type="submit"
                            class="p-1.5 text-gray-400 hover:text-red-600 transition-colors rounded hover:bg-red-50"
                            title="Fach entfernen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </form>
            @endcanany
        </div>
    </div>
    {{-- Aufgaben-Liste --}}
    <div x-show="open" class="p-3" data-sortable>
        @forelse($planFach->aufgaben as $aufgabe)
            @include('wochenplan.new.components.aufgabe-item', ['aufgabe' => $aufgabe])
        @empty
            <p class="text-xs text-gray-400 italic py-1">Noch keine Aufgaben.</p>
        @endforelse
        {{-- Inline-Formular --}}
        @include('wochenplan.new.components.aufgabe-form', ['planFach' => $planFach])
    </div>
</div>
