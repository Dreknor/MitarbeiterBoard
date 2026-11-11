{{-- Kennzeichen einer Abwesenheit (Übersicht) --}}
<div class="flex flex-wrap gap-1">
    @if($absence->showVertretungsplan)
        <span class="abw-badge abw-badge-violet" title="Wird auf dem Vertretungsplan angezeigt"><i class="fas fa-columns"></i> Vertretungsplan</span>
    @endif
    @if($absence->holiday_id !== null)
        <span class="abw-badge abw-badge-green" title="Aus einem genehmigten Urlaub übernommen"><i class="fas fa-umbrella-beach"></i> Urlaub</span>
    @endif
    @if($zeigeKrankenschein && ($absence->sick_note_required || $absence->sick_note_date))
        @if($absence->sick_note_date)
            <span class="abw-badge abw-badge-green" title="Krankenschein erfasst am {{ $absence->sick_note_date->format('d.m.Y') }}"><i class="fas fa-file-medical"></i> Schein liegt vor</span>
        @else
            <span class="abw-badge abw-badge-amber" title="Krankenschein erforderlich, noch nicht erfasst"><i class="fas fa-file-medical"></i> Schein fehlt</span>
        @endif
    @endif
</div>
