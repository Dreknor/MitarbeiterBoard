{{--
    Stufenzuordnung einer Graduierungsfrage (Checkboxen). Keine Auswahl = Frage gilt für alle Stufen.
    Erwartet: $stages, $selected (Stufen-IDs), $idPrefix (eindeutige IDs je Formular)
--}}
<div class="col-12 mb-2">
    <label class="small font-weight-bold d-block mb-1">Gilt für Stufen</label>
    @if($stages->isEmpty())
        <small class="text-muted">Das System hat noch keine Stufen – die Frage gilt für alle.</small>
    @else
        @foreach($stages as $stage)
            <div class="custom-control custom-checkbox custom-control-inline">
                <input type="checkbox" class="custom-control-input" name="stage_ids[]" value="{{ $stage->id }}"
                       id="stage-{{ $idPrefix }}-{{ $stage->id }}" @checked(in_array($stage->id, $selected))>
                <label class="custom-control-label small" for="stage-{{ $idPrefix }}-{{ $stage->id }}">{{ $stage->name }}</label>
            </div>
        @endforeach
        <small class="form-text text-muted">Keine Auswahl = die Frage gilt für alle Stufen.</small>
    @endif
</div>
