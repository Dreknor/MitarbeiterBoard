@extends('layouts.app')

@section('title', 'Neue Planung erstellen')
@section('site-title', 'Hortstunden-Planung')

@push('css')
    @vite(['resources/css/hort-planung.css'])
@endpush

@section('content')
<div class="hort-planung-wrapper">
<div class="max-w-2xl mx-auto px-4 py-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-1.5 text-sm text-gray-500 mb-5">
        <a href="{{ route('hort-planung.index') }}" class="hover:text-blue-600">Hortstunden-Planung</a>
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
        <span class="text-gray-800 font-medium">Neue Planung</span>
    </nav>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50">
            <h1 class="text-lg font-semibold text-gray-800">Neue Hortstunden-Planung erstellen</h1>
            <p class="text-sm text-gray-500 mt-0.5">Die Planung wird mit Standard-Faktoren (§12 SächsKitaG) und zwei Zusatzstunden-Kategorien angelegt.</p>
        </div>

        <form action="{{ route('hort-planung.store') }}" method="POST" class="p-6 space-y-5"
              x-data="{
                  startMonat: '',
                  endMonat: '',
                  get anzahlMonate() {
                      if (!this.startMonat || !this.endMonat) return 0;
                      const s = new Date(this.startMonat + '-01');
                      const e = new Date(this.endMonat + '-01');
                      if (e <= s) return 0;
                      return (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth()) + 1;
                  }
              }">
            @csrf

            {{-- Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Planungsname <span class="text-red-500">*</span>
                </label>
                <input type="text" name="name" value="{{ old('name') }}"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('name') border-red-400 @enderror"
                       placeholder="z. B. Planung 2024–2027" required>
                @error('name')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Beschreibung --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                <textarea name="beschreibung" rows="2"
                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none resize-none"
                          placeholder="Annahmen, Szenarien oder Hinweise zur Planung …">{{ old('beschreibung') }}</textarea>
            </div>

            {{-- Abteilung --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Abteilung <span class="text-red-500">*</span>
                </label>
                <select name="department_id" required
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('department_id') border-red-400 @enderror">
                    <option value="">– Abteilung wählen –</option>
                    @foreach($abteilungen as $abteilung)
                        <option value="{{ $abteilung->id }}" {{ old('department_id') == $abteilung->id ? 'selected' : '' }}>
                            {{ $abteilung->name }}
                        </option>
                    @endforeach
                </select>
                @error('department_id')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Zeitraum --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Startmonat <span class="text-red-500">*</span>
                    </label>
                    <input type="month" name="start_monat"
                           value="{{ old('start_monat', now()->format('Y-m')) }}"
                           x-model="startMonat"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('start_monat') border-red-400 @enderror"
                           required>
                    @error('start_monat')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Endmonat <span class="text-red-500">*</span>
                    </label>
                    <input type="month" name="end_monat"
                           value="{{ old('end_monat', now()->addYears(3)->format('Y-m')) }}"
                           x-model="endMonat"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none @error('end_monat') border-red-400 @enderror"
                           required>
                    @error('end_monat')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Monatsanzahl-Info (Alpine.js) --}}
            <div x-show="anzahlMonate > 0"
                 class="flex items-center gap-2 px-3 py-2 bg-blue-50 text-blue-700 rounded-xl text-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Es werden <strong x-text="anzahlMonate"></strong> Monate generiert.
            </div>
            <div x-show="endMonat && startMonat && anzahlMonate === 0"
                 class="flex items-center gap-2 px-3 py-2 bg-amber-50 text-amber-700 rounded-xl text-sm">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Der Endmonat muss nach dem Startmonat liegen.
            </div>

            {{-- Typ --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Planungstyp</label>
                <div class="flex gap-3">
                    @foreach(['planung' => '📋 Planung (Vorausschau)', 'rueckblick' => '📊 Rückblick (historisch)'] as $val => $label)
                    <label class="flex-1 flex items-center gap-2 px-3 py-2.5 border rounded-xl cursor-pointer hover:bg-gray-50 has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <input type="radio" name="typ" value="{{ $val }}"
                               {{ old('typ', 'planung') === $val ? 'checked' : '' }}
                               class="accent-blue-600">
                        <span class="text-sm">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            {{-- Kinderanzahl --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Start-Kinderanzahl
                    <span class="text-gray-400 font-normal">(für alle Monate, später anpassbar)</span>
                </label>
                <input type="number" name="kinderanzahl" value="{{ old('kinderanzahl', 100) }}"
                       min="0" max="500"
                       class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
            </div>

            {{-- Aus Anstellungen importieren --}}
            <label class="flex items-start gap-3 p-3 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100">
                <input type="checkbox" name="import_employments" value="1"
                       {{ old('import_employments') ? 'checked' : '' }}
                       class="mt-0.5 accent-blue-600">
                <div>
                    <span class="text-sm font-medium text-gray-700">Personen automatisch aus Anstellungen laden</span>
                    <p class="text-xs text-gray-500 mt-0.5">Importiert aktive Mitarbeiter der gewählten Abteilung als Startdaten.</p>
                </div>
            </label>

            {{-- Aktions-Buttons --}}
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        class="flex-1 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm shadow-sm">
                    Planung anlegen
                </button>
                <a href="{{ route('hort-planung.index') }}"
                   class="px-4 py-2.5 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-medium rounded-xl text-sm">
                    Abbrechen
                </a>
            </div>
        </form>
    </div>

</div>
</div>
@endsection

