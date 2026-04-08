@if(($upcomingTrainingsCount ?? 0) > 0)
<div class="bg-white rounded-xl border border-blue-200 p-4 shadow-sm">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 text-lg">
            📚
        </div>
        <div class="flex-1 min-w-0">
            <h4 class="font-semibold text-blue-800 text-sm">Anstehende Fortbildungen</h4>
            <p class="text-xs text-blue-600 mt-0.5">{{ $upcomingTrainingsCount }} Fortbildung(en) in den nächsten 30 Tagen</p>
            <a href="{{ route('personal.trainings.index') }}" class="text-xs text-blue-500 underline mt-1 inline-block">Alle anzeigen</a>
        </div>
    </div>
</div>
@endif

