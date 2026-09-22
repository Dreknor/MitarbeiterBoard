{{--
    Dashboard-Card: Anstehende Mitarbeitergespräche
    Wird vom UpcomingReviewsComposer befüllt ($upcomingReviewsCount).
    Solange kein Composer registriert ist oder keine Daten vorliegen,
    bleibt die Card leer (kein Fehler, kein Rendering).
--}}
@if(($upcomingReviewsCount ?? 0) > 0)
<div class="bg-white rounded-xl border border-purple-200 p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-lg">
            💬
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-purple-800 text-sm">Anstehende Gespräche</h4>
            <p class="text-xs text-purple-600 mt-0.5">
                {{ $upcomingReviewsCount }} Gespräch(e) in den nächsten 30 Tagen
            </p>
        </div>
    </div>
</div>
@endif

