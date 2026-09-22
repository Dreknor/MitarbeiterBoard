@if(($expiringDocumentsCount ?? 0) > 0)
<div class="bg-white rounded-xl border border-yellow-200 p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center text-yellow-600 text-lg">
            📄
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-yellow-800 text-sm">Ablaufende Dokumente</h4>
            <p class="text-xs text-yellow-600 mt-0.5">{{ $expiringDocumentsCount }} Dokument(e) laufen in den nächsten 30 Tagen ab</p>
        </div>
    </div>
</div>
@endif

