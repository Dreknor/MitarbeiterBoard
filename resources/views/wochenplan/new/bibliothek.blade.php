@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-wrapper" x-data="wpBibliothek(@js(['url' => route('wp.bibliothek.index')]))" x-cloak>
    <a href="{{ route('wp.index') }}" class="wp-back">← Übersicht</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Aufgaben-Bibliothek</h1>
            <p class="wp-subtitle">Wiederverwendbare Aufgaben pro Fach und Klassenstufe. Im Plan fügen Sie sie über 📚 am Fach ein.</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <section class="wp-card lg:col-span-2">
            <div class="wp-card-head">
                <form class="flex flex-1 flex-wrap gap-2" @submit.prevent="suchen()">
                    <input type="search" class="wp-input wp-input-sm flex-1" style="min-width: 12rem" x-model="q" placeholder="Suchen …">
                    <select class="wp-select wp-input-sm" style="max-width: 12rem" x-model="fachId" @change="suchen()" aria-label="Fach">
                        <option value="">alle Fächer</option>
                        @foreach($faecher as $fach)
                            <option value="{{ $fach->id }}">{{ $fach->name }}</option>
                        @endforeach
                    </select>
                    <select class="wp-select wp-input-sm" style="max-width: 9rem" x-model="klassenstufe" @change="suchen()" aria-label="Klassenstufe">
                        <option value="">alle Stufen</option>
                        @for($i = 1; $i <= 13; $i++)<option value="{{ $i }}">Klasse {{ $i }}</option>@endfor
                    </select>
                    <button type="submit" class="wp-btn wp-btn-secondary wp-btn-sm">Suchen</button>
                </form>
            </div>
            <div class="wp-card-body">
                <p x-show.important="laedt" class="text-sm text-gray-500">Lädt …</p>
                <p x-show.important="!laedt && !liste.length" class="text-sm text-gray-500">Keine Aufgaben gefunden.</p>
                <ul class="space-y-2">
                    <template x-for="b in liste" :key="b.id">
                        <li class="flex items-start justify-between gap-2 rounded-xl border border-gray-200 px-3 py-2">
                            <div class="min-w-0">
                                <p class="text-sm text-gray-800 whitespace-pre-line" x-text="b.aufgabe"></p>
                                <p class="text-xs text-gray-500" x-text="[b.fach || 'ohne Fach', b.klassenstufe ? 'Klasse ' + b.klassenstufe : 'alle Stufen', b.dauer, b.von].filter(Boolean).join(' · ')"></p>
                                <template x-if="b.link"><a :href="b.link" target="_blank" rel="noopener" class="text-xs text-blue-600">🔗 Link</a></template>
                            </div>
                            <template x-if="b.darf_loeschen">
                                <button type="button" class="wp-btn-icon is-danger" title="Entfernen" @click="loeschen(b)">🗑</button>
                            </template>
                        </li>
                    </template>
                </ul>
            </div>
        </section>

        <section class="wp-card">
            <div class="wp-card-head"><h2 class="wp-card-title">Neue Aufgabe</h2></div>
            <form class="wp-card-body space-y-3" @submit.prevent="anlegen()">
                <div>
                    <label class="wp-label" for="wp-b-aufgabe">Aufgabe</label>
                    <textarea id="wp-b-aufgabe" class="wp-textarea" rows="3" x-model="neu.aufgabe" maxlength="2000" required></textarea>
                </div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                    <div>
                        <label class="wp-label" for="wp-b-fach">Fach</label>
                        <select id="wp-b-fach" class="wp-select" x-model="neu.wp_fach_id">
                            <option value="">ohne Fach</option>
                            @foreach($faecher as $fach)<option value="{{ $fach->id }}">{{ $fach->name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="wp-label" for="wp-b-stufe">Klassenstufe</label>
                        <select id="wp-b-stufe" class="wp-select" x-model="neu.klassenstufe">
                            <option value="">alle</option>
                            @for($i = 1; $i <= 13; $i++)<option value="{{ $i }}">{{ $i }}</option>@endfor
                        </select>
                    </div>
                    <div>
                        <label class="wp-label" for="wp-b-dauer">Dauer</label>
                        <input id="wp-b-dauer" type="text" class="wp-input" x-model="neu.dauer" maxlength="50">
                    </div>
                    <div>
                        <label class="wp-label" for="wp-b-niveau">Niveau</label>
                        <select id="wp-b-niveau" class="wp-select" x-model="neu.niveau">
                            <option value="">alle</option>
                            @foreach(\App\Models\Wochenplan\WpPlan::NIVEAUS as $n => $name)<option value="{{ $n }}">{{ $name }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="wp-label" for="wp-b-link">Link</label>
                    <input id="wp-b-link" type="url" class="wp-input" x-model="neu.link" placeholder="https://…" maxlength="500">
                </div>
                <button type="submit" class="wp-btn wp-btn-primary w-full" :disabled="!neu.aufgabe.trim()">In die Bibliothek</button>
            </form>
        </section>
    </div>

    <div x-show.important="toast" x-transition class="wp-toast" :class="toast && ('is-' + toast.art)" role="status" aria-live="polite">
        <span x-text="toast?.text"></span>
    </div>
</div>
@endsection

@push('js')
    @vite('resources/js/wochenplan.js')
@endpush
