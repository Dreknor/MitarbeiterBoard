{{-- Seitennavigation ohne Bootstrap --}}
@if($paginator->hasPages())
    <nav class="flex items-center justify-between gap-2" aria-label="Seiten">
        @if($paginator->onFirstPage())
            <span class="wp-btn wp-btn-secondary wp-btn-sm opacity-50">← Zurück</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="wp-btn wp-btn-secondary wp-btn-sm">← Zurück</a>
        @endif
        <span class="text-xs text-gray-500">Seite {{ $paginator->currentPage() }} von {{ $paginator->lastPage() }} · {{ $paginator->total() }} Pläne</span>
        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="wp-btn wp-btn-secondary wp-btn-sm">Weiter →</a>
        @else
            <span class="wp-btn wp-btn-secondary wp-btn-sm opacity-50">Weiter →</span>
        @endif
    </nav>
@endif
