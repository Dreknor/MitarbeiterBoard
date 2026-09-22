@if(($missingQualificationsCount ?? 0) > 0)
<div class="bg-white rounded-xl border border-orange-200 p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 text-lg">
            🎓
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-orange-800 text-sm">Qualifikationen</h4>
            <p class="text-xs text-orange-600 mt-0.5">{{ $missingQualificationsCount }} Qualifikation(en) ablaufend oder abgelaufen</p>
            <a href="{{ route('personal.qualifications.matrix') }}" class="text-xs text-orange-500 underline mt-1 inline-block">Matrix anzeigen</a>
        </div>
    </div>
</div>
@endif

