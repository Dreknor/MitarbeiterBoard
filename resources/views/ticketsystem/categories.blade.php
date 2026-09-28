@extends('layouts.app')

@push('css')
    @vite(['resources/css/tickets.css', 'resources/js/tickets.js'])
@endpush

@section('content')
<div class="ticket-wrapper" style="max-width: 56rem"
     x-data="{ del: null }" @keydown.escape.window="del = null">

    <div class="mb-5">
        <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-800 mb-1">
            <i class="fas fa-arrow-left"></i> Ticketsystem
        </a>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">Kategorien</h1>
        <p class="text-sm text-gray-500 mt-0.5">Kategorien helfen, Tickets zu sortieren und die richtige Person zu finden.</p>
    </div>

    <section class="tkt-card mb-5">
        <form method="post" action="{{ route('categories.store') }}" class="tkt-card-body" data-ticket-form>
            @csrf
            <label for="name" class="tkt-label">Neue Kategorie</label>
            <div class="flex flex-col gap-2 sm:flex-row">
                <input type="text" name="name" id="name" class="tkt-input flex-1 @error('name') is-invalid @enderror"
                       placeholder="z. B. IT / Technik, Haustechnik, Verwaltung" maxlength="255" value="{{ old('name') }}" required>
                <button type="submit" class="tkt-btn tkt-btn-primary">
                    <i class="fas fa-plus"></i> Hinzufügen
                </button>
            </div>
            @error('name')<p class="tkt-error">{{ $message }}</p>@enderror
        </form>
    </section>

    <section class="tkt-card">
        <div class="tkt-card-head">
            <h2 class="tkt-card-title"><i class="fas fa-tags"></i> Vorhandene Kategorien</h2>
            <span class="tkt-badge tkt-badge-gray">{{ $categories->count() }}</span>
        </div>
        <ul>
            @forelse($categories as $category)
                <li class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 last:border-b-0 sm:px-5">
                    <span class="w-9 h-9 rounded-xl bg-violet-50 text-violet-600 flex items-center justify-center shrink-0">
                        <i class="fas fa-tag"></i>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-900 truncate">{{ $category->name }}</p>
                        <p class="text-xs text-gray-500">
                            <a href="{{ route('tickets.index', ['category' => $category->id]) }}" class="hover:text-blue-700">
                                {{ $category->open_tickets_count }} offen
                            </a>
                            · {{ $category->tickets_count }} gesamt
                        </p>
                    </div>
                    <button type="button" class="tkt-btn-icon hover:text-red-600 hover:bg-red-50"
                            @click="del = { name: @js($category->name), action: @js(route('categories.destroy', $category)), count: {{ $category->tickets_count }} }"
                            title="Löschen" aria-label="Kategorie {{ $category->name }} löschen">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </li>
            @empty
                <li class="tkt-empty">
                    <span class="tkt-empty-icon"><i class="fas fa-tags"></i></span>
                    <p>Noch keine Kategorien angelegt.</p>
                </li>
            @endforelse
        </ul>
    </section>

    {{-- Lösch-Dialog --}}
    <div class="tkt-modal-backdrop" x-show="del" x-transition.opacity x-cloak @click.self="del = null">
        <form method="post" :action="del?.action" class="tkt-modal" role="dialog" aria-modal="true" aria-labelledby="delTitle" data-ticket-form>
            @csrf
            @method('DELETE')
            <div class="tkt-modal-header">
                <h3 class="tkt-modal-title" id="delTitle">Kategorie löschen?</h3>
                <button type="button" class="tkt-btn-icon" @click="del = null" aria-label="Abbrechen"><i class="fas fa-times"></i></button>
            </div>
            <div class="tkt-modal-body text-sm text-gray-600">
                <p>„<strong x-text="del?.name"></strong>“ wird gelöscht.</p>
                <p class="mt-2 text-amber-700" x-show="del?.count > 0">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span x-text="del?.count"></span> Ticket(s) verlieren dadurch ihre Kategorie.
                </p>
            </div>
            <div class="tkt-modal-footer">
                <button type="button" class="tkt-btn tkt-btn-secondary" @click="del = null">Abbrechen</button>
                <button type="submit" class="tkt-btn tkt-btn-danger"><i class="fas fa-trash-alt"></i> <span data-loading-label="Wird gelöscht …">Löschen</span></button>
            </div>
        </form>
    </div>
</div>
@endsection
