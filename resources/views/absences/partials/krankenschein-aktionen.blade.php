{{-- Krankenschein erfassen/entfernen und Pflicht für kurze Krankheiten (/sick_notes) – nur POST/DELETE --}}
<div class="inline-flex flex-wrap items-center justify-end gap-1.5">
    @if($absence->sick_note_date === null)
        <form action="{{ route('sick_notes.schein', $absence) }}" method="post" class="inline-flex items-center gap-1.5" data-tour="krank-schein">
            @csrf
            <input aria-label="Eingangsdatum des Krankenscheins" type="date" name="datum" value="{{ $heute->toDateString() }}" max="{{ $heute->toDateString() }}"
                   class="abw-input abw-input-sm" title="Eingangsdatum des Krankenscheins">
            <button type="submit" class="abw-btn abw-btn-primary abw-btn-sm" title="Krankenschein erfassen">
                <i class="fas fa-check"></i><span>Schein erfasst</span>
            </button>
        </form>
        @if(!$service->erreichtSchwelle($absence))
            <form action="{{ route('sick_notes.pflicht', $absence) }}" method="post" class="inline">
                @csrf
                <input type="hidden" name="pflicht" value="{{ $absence->sick_note_required ? 0 : 1 }}">
                <button type="submit" class="abw-btn abw-btn-ghost abw-btn-sm"
                        title="{{ $absence->sick_note_required ? 'Schein für diese kurze Krankheit nicht mehr verlangen' : 'Schein schon ab dem ersten Tag verlangen' }}">
                    <i class="fas {{ $absence->sick_note_required ? 'fa-times' : 'fa-exclamation' }}"></i>
                    <span>{{ $absence->sick_note_required ? 'nicht verlangen' : 'verlangen' }}</span>
                </button>
            </form>
        @endif
    @else
        <form action="{{ route('sick_notes.schein.entfernen', $absence) }}" method="post" class="inline"
              onsubmit="return confirm(@js('Krankenschein für '.$absence->user->name.' ('.$zeitraum($absence).') entfernen?'))">
            @csrf
            @method('DELETE')
            <button type="submit" class="abw-btn-icon" title="Krankenschein entfernen" aria-label="Krankenschein entfernen">
                <i class="fas fa-undo"></i>
            </button>
        </form>
    @endif
</div>
