@extends('layouts.app')

@php use Illuminate\Support\Facades\Storage; @endphp

@push('css')
    @vite('resources/css/wochenplan.css')
    <style>[x-cloak] { display: none !important; }</style>
@endpush

@section('content')
<div class="wp-container p-4 max-w-3xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('wp.index') }}" class="text-sm text-gray-500 hover:text-gray-700">← Übersicht</a>
            <h1 class="text-2xl font-bold text-gray-900 mt-1">Fächer-Katalog</h1>
            <p class="text-xs text-gray-500 mt-0.5">Standard-Fächer werden bei neuen Plänen automatisch vorausgewählt.</p>
        </div>
    </div>

    @if(session('Meldung'))
        <div class="mb-4 px-4 py-3 rounded-lg text-sm
            {{ session('type') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200' }}">
            {{ session('Meldung') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg text-sm bg-red-50 text-red-800 border border-red-200">
            <strong>Fehler:</strong>
            <ul class="mt-1 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Neues Fach --}}
    <div class="bg-white rounded-lg border border-gray-200 p-4 mb-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Neues Fach anlegen</h2>
        <form method="POST" action="{{ route('wp.faecher.store') }}"
              enctype="multipart/form-data"
              x-data="{ symbolTyp: 'keine' }">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Name</label>
                    <input type="text" name="name" placeholder="z.B. Religion" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Sortierung</label>
                    <input type="number" name="sort_order"
                           placeholder="{{ ($faecher->max('sort_order') ?? 0) + 1 }}" min="0"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
                {{-- Symbol-Typ --}}
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Symbol-Typ</label>
                    <select name="symbol_typ" x-model="symbolTyp"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="keine">Kein Symbol</option>
                        <option value="emoji">Emoji</option>
                        <option value="bild">Bild (JPG/PNG)</option>
                        <option value="svg">SVG (inline)</option>
                    </select>
                </div>

                {{-- Emoji / SVG Eingabe --}}
                <div x-show="symbolTyp === 'emoji' || symbolTyp === 'svg'" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Symbol-Wert</label>
                    <input type="text" name="symbol_wert" placeholder="z.B. 📖 oder &lt;svg&gt;..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>

                {{-- Bild-Upload --}}
                <div x-show="symbolTyp === 'bild'" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Bild hochladen</label>
                    <input type="file" name="symbol_bild" accept="image/jpeg,image/png"
                           class="w-full text-sm text-gray-600 border border-gray-300 rounded-md px-2 py-1.5 bg-white
                                  file:mr-2 file:py-1 file:px-2 file:rounded file:border-0
                                  file:text-xs file:font-medium file:bg-primary-50 file:text-primary-700
                                  hover:file:bg-primary-100 cursor-pointer">
                    <p class="text-xs text-gray-400 mt-1">JPG oder PNG, max. 2 MB</p>
                </div>

                {{-- Farbe für Emoji --}}
                <div x-show="symbolTyp === 'emoji'" x-cloak>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Farbe (optional)</label>
                    <input type="color" name="symbol_farbe" value="#3b82f6"
                           class="h-10 w-full px-1 py-1 border border-gray-300 rounded-md cursor-pointer">
                </div>
            </div>

            <div class="flex items-center gap-3">
                <label class="flex items-center gap-1.5 text-sm text-gray-700">
                    <input type="checkbox" name="is_default" value="1"
                           class="rounded border-gray-300 text-primary-600">
                    Standard
                </label>
                <button type="submit"
                        class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-md hover:bg-primary-700">
                    Hinzufügen
                </button>
            </div>
        </form>
    </div>

    {{-- Fächer-Tabelle --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 w-12">#</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600">Name</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 w-24">Symbol</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 w-24">Standard</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 w-28">Verwendet</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 w-24">Aktionen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($faecher as $fach)
                    <tr x-data="{
                            editing: false,
                            name: '{{ addslashes($fach->name) }}',
                            sort: {{ $fach->sort_order }},
                            isDefault: {{ $fach->is_default ? 'true' : 'false' }},
                            symbolTyp: '{{ $fach->symbol_typ ?? 'keine' }}',
                            bildUrl: '{{ $fach->symbol_typ === 'bild' && $fach->symbol_wert ? Storage::url($fach->symbol_wert) : '' }}'
                        }"
                        class="hover:bg-gray-50 transition-colors">

                        {{-- ── Anzeige-Modus ── --}}
                        <td class="px-4 py-3 text-gray-500" x-show="!editing">{{ $fach->sort_order }}</td>
                        <td class="px-4 py-3 font-medium text-gray-900" x-show="!editing">{{ $fach->name }}</td>
                        <td class="px-4 py-3 text-center" x-show="!editing">
                            @if($fach->symbol_typ === 'bild' && $fach->symbol_wert)
                                <img src="{{ Storage::url($fach->symbol_wert) }}" alt="{{ $fach->name }}"
                                     class="w-8 h-8 object-contain mx-auto rounded">
                            @elseif($fach->symbol_typ && $fach->symbol_typ !== 'keine' && $fach->symbol_wert)
                                <span class="text-xl"
                                      @if($fach->symbol_farbe) style="color: {{ $fach->symbol_farbe }}" @endif>
                                    {{ $fach->symbol_wert }}
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">–</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center" x-show="!editing">
                            @if($fach->is_default)
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">
                                    ★ Standard
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">–</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-gray-500" x-show="!editing">
                            {{ $fach->plan_faecher_count }}
                            {{ $fach->plan_faecher_count === 1 ? 'Plan' : 'Pläne' }}
                        </td>
                        <td class="px-4 py-3 text-right" x-show="!editing">
                            <div class="flex items-center justify-end gap-1">
                                <button @click="editing = true"
                                        class="p-1 text-gray-400 hover:text-primary-600 rounded" title="Bearbeiten">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                    </svg>
                                </button>
                                @if($fach->plan_faecher_count === 0)
                                    <form method="POST" action="{{ route('wp.faecher.destroy', $fach) }}"
                                          onsubmit="return confirm('Fach &quot;{{ addslashes($fach->name) }}&quot; wirklich löschen?')"
                                          class="inline">
                                        @csrf @method('DELETE')
                                        <button type="submit"
                                                class="p-1 text-gray-400 hover:text-red-600 rounded" title="Löschen">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                @else
                                    <span class="p-1 text-gray-200 cursor-not-allowed"
                                          title="In {{ $fach->plan_faecher_count }} Plan(en) verwendet – nicht löschbar">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- ── Edit-Modus (volle Breite) ── --}}
                        <td colspan="6" class="px-4 py-3 bg-blue-50" x-show="editing" x-cloak>
                            <form method="POST" action="{{ route('wp.faecher.update', $fach) }}"
                                  enctype="multipart/form-data"
                                  class="flex flex-wrap items-end gap-3">
                                @csrf

                                {{-- Sortierung --}}
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">#</label>
                                    <input type="number" name="sort_order" x-model="sort" min="0"
                                           class="w-16 px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>

                                {{-- Name --}}
                                <div class="flex-1 min-w-36">
                                    <label class="block text-xs text-gray-500 mb-1">Name</label>
                                    <input type="text" name="name" x-model="name" required
                                           class="w-full px-3 py-1.5 border border-primary-400 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>

                                {{-- Standard --}}
                                <div class="flex items-center gap-1.5 pb-1.5">
                                    <input type="checkbox" name="is_default" value="1"
                                           id="is_default_{{ $fach->id }}"
                                           :checked="isDefault"
                                           @change="isDefault = $event.target.checked"
                                           class="rounded border-gray-300 text-primary-600">
                                    <label for="is_default_{{ $fach->id }}" class="text-sm cursor-pointer">Standard</label>
                                </div>

                                {{-- Symbol-Typ --}}
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Symbol-Typ</label>
                                    <select name="symbol_typ" x-model="symbolTyp"
                                            class="px-2 py-1.5 border border-gray-300 rounded text-sm bg-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                                        <option value="keine">Kein Symbol</option>
                                        <option value="emoji">Emoji</option>
                                        <option value="bild">Bild (JPG/PNG)</option>
                                        <option value="svg">SVG</option>
                                    </select>
                                </div>

                                {{-- Emoji / SVG Wert --}}
                                <div x-show="symbolTyp === 'emoji' || symbolTyp === 'svg'" x-cloak>
                                    <label class="block text-xs text-gray-500 mb-1">Wert</label>
                                    <input type="text" name="symbol_wert"
                                           value="{{ $fach->symbol_wert }}"
                                           placeholder="Emoji oder SVG"
                                           class="w-24 px-2 py-1.5 border border-gray-300 rounded text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
                                </div>

                                {{-- Farbe für Emoji --}}
                                <div x-show="symbolTyp === 'emoji'" x-cloak>
                                    <label class="block text-xs text-gray-500 mb-1">Farbe</label>
                                    <input type="color" name="symbol_farbe"
                                           value="{{ $fach->symbol_farbe ?? '#3b82f6' }}"
                                           class="h-9 w-12 px-1 border border-gray-300 rounded cursor-pointer">
                                </div>

                                {{-- Bild-Upload --}}
                                <div x-show="symbolTyp === 'bild'" x-cloak>
                                    <label class="block text-xs text-gray-500 mb-1">Bild</label>
                                    <div class="flex items-center gap-2">
                                        {{-- Vorschau des vorhandenen Bildes --}}
                                        <img :src="bildUrl" alt="" x-show="bildUrl !== ''"
                                             class="w-8 h-8 object-contain rounded border border-gray-200">
                                        <input type="file" name="symbol_bild" accept="image/jpeg,image/png"
                                               class="text-xs text-gray-600 border border-gray-300 rounded px-2 py-1.5 bg-white
                                                      file:mr-2 file:py-0.5 file:px-2 file:rounded file:border-0
                                                      file:text-xs file:font-medium file:bg-primary-50 file:text-primary-700
                                                      hover:file:bg-primary-100 cursor-pointer">
                                    </div>
                                </div>

                                {{-- Buttons --}}
                                <div class="flex gap-2 pb-0.5">
                                    <button type="submit"
                                            class="px-3 py-1.5 bg-primary-600 text-white text-xs font-medium rounded hover:bg-primary-700">
                                        Speichern
                                    </button>
                                    <button type="button" @click="editing = false"
                                            class="px-3 py-1.5 bg-gray-100 text-gray-600 text-xs rounded hover:bg-gray-200">
                                        Abbrechen
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">
                            Noch keine Fächer vorhanden.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush

