{{-- Löschen (nur per DELETE mit Rückfrage) bzw. Hinweis, warum es nicht geht --}}
@if($service->loeschbarDurch(auth()->user(), $absence))
    <form action="{{ route('absences.destroy', $absence) }}" method="post" class="inline-block"
          onsubmit="return confirm(@js('Abwesenheit von '.$absence->user->name.' ('.$zeitraum($absence).') löschen?'))">
        @csrf
        @method('DELETE')
        <button type="submit" class="abw-btn-icon" title="Löschen" aria-label="Abwesenheit löschen" data-tour="abw-loeschen">
            <i class="fas fa-trash-alt"></i>
        </button>
    </form>
@elseif($service->darfLoeschen(auth()->user(), $absence))
    <span class="inline-flex items-center justify-center w-9 h-9 text-gray-300"
          title="{{ $absence->holiday_id !== null ? 'Stammt aus einem Urlaub – bitte den Urlaub stornieren' : 'Abwesenheiten, die heute enden oder vorbei sind, bleiben als Nachweis erhalten' }}">
        <i class="fas fa-lock text-xs"></i>
    </span>
@endif
