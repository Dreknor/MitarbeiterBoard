{{-- Kompakte, mobilfreundliche Seitennavigation --}}
@if($paginator->hasPages())
    <nav class="flex items-center justify-between gap-2 px-4 py-3 border-t border-gray-100" aria-label="Seiten">
        @if($paginator->onFirstPage())
            <span class="tkt-btn tkt-btn-secondary tkt-btn-sm opacity-50"><i class="fas fa-chevron-left"></i><span class="hidden sm:inline">Zurück</span></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="tkt-btn tkt-btn-secondary tkt-btn-sm"><i class="fas fa-chevron-left"></i><span class="hidden sm:inline">Zurück</span></a>
        @endif
        <span class="text-xs text-gray-500">Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="tkt-btn tkt-btn-secondary tkt-btn-sm"><span class="hidden sm:inline">Weiter</span><i class="fas fa-chevron-right"></i></a>
        @else
            <span class="tkt-btn tkt-btn-secondary tkt-btn-sm opacity-50"><span class="hidden sm:inline">Weiter</span><i class="fas fa-chevron-right"></i></span>
        @endif
    </nav>
@endif
