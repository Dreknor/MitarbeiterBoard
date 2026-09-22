{{--
    Dashboard-Card: Offene Änderungsanträge
    Berechtigung: edit personal_data:all

    Wird von einem PendingChangeRequestsComposer befüllt ($pendingChangeRequestsCount).
    Solange kein Composer registriert ist oder keine offenen Anträge vorliegen,
    bleibt die Card leer (kein Fehler, kein Rendering).
--}}
@if(($pendingChangeRequestsCount ?? 0) > 0)
<div class="bg-white rounded-xl border border-amber-200 p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 text-xl shrink-0">
            📋
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-amber-800 text-sm">Offene Änderungsanträge</h4>
            <p class="text-xs text-amber-700 mt-0.5">
                {{ $pendingChangeRequestsCount }} Antrag/Anträge warten auf Bearbeitung
            </p>
        </div>
        @if(isset($changeRequestsUrl))
        <a href="{{ $changeRequestsUrl }}" class="text-xs text-amber-600 hover:text-amber-800 font-medium shrink-0">
            Ansehen →
        </a>
        @endif
    </div>
</div>
@endif

