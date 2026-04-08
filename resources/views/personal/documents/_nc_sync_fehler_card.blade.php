@if(($ncSyncFehlerCount ?? 0) > 0)
<div class="bg-white rounded-xl border border-red-200 p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-600 text-lg">
            ⚠️
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-red-700 text-sm">Nextcloud Sync-Fehler</h4>
            <p class="text-xs text-red-600 mt-0.5">{{ $ncSyncFehlerCount }} Dokument(e) konnten nicht synchronisiert werden</p>
            <a href="{{ route('personal.documents.sync-errors') }}" class="text-xs text-red-500 underline mt-1 inline-block">Details anzeigen</a>
        </div>
    </div>
</div>
@endif

