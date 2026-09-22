@php
    $prefix = $prefix ?? 'meeting';
    $bookRoomEnabled = (bool) ($bookRoomEnabled ?? false);
    $selectedRoomId = $selectedRoomId ?? null;
@endphp

@if($canBookRooms)
    <div class="border rounded p-3 mt-3">
        <h6 class="mb-2">Raumbuchung (optional)</h6>

        <input type="hidden" name="book_room" value="0">

        <div class="custom-control custom-switch mb-3">
            <input type="checkbox"
                   class="custom-control-input"
                   id="{{ $prefix }}_book_room"
                   name="book_room"
                   value="1"
                   {{ $bookRoomEnabled ? 'checked' : '' }}>
            <label class="custom-control-label" for="{{ $prefix }}_book_room">Raum direkt buchen</label>
        </div>

        <div id="{{ $prefix }}_room_fields" class="{{ $bookRoomEnabled ? '' : 'd-none' }}">
            <div class="form-group">
                <label for="{{ $prefix }}_room_id">Raum</label>
                <select class="form-control @error('room_id') is-invalid @enderror"
                        id="{{ $prefix }}_room_id"
                        name="room_id">
                    <option value="">Bitte auswählen</option>
                    @foreach($bookableRooms as $room)
                        <option value="{{ $room->id }}" @selected((string) old('room_id', $selectedRoomId) === (string) $room->id)>
                            {{ $room->name }}@if($room->room_number) (Nr. {{ $room->room_number }})@endif
                        </option>
                    @endforeach
                </select>
                @error('room_id')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div id="{{ $prefix }}_availability" class="small text-muted"></div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('{{ $prefix }}_book_room');
            const fields = document.getElementById('{{ $prefix }}_room_fields');
            const roomSelect = document.getElementById('{{ $prefix }}_room_id');
            const info = document.getElementById('{{ $prefix }}_availability');

            if (!toggle || !fields || !roomSelect || !info) {
                return;
            }

            const form = toggle.closest('form');
            const dateInput = form ? form.querySelector('input[name="date"]') : null;
            const startInput = form ? form.querySelector('input[name="start_time"]') : null;
            const endInput = form ? form.querySelector('input[name="end_time"]') : null;
            const endpointBase = '{{ url('rooms/availability') }}';

            function setState(text, cls) {
                info.className = 'small ' + cls;
                info.textContent = text;
            }

            function refreshVisibility() {
                fields.classList.toggle('d-none', !toggle.checked);
            }

            async function checkAvailability() {
                if (!toggle.checked) {
                    setState('', 'text-muted');
                    return;
                }

                const roomId = roomSelect.value;
                const date = dateInput ? dateInput.value : '';
                const start = startInput ? startInput.value : '';
                const end = endInput ? endInput.value : '';

                if (!roomId || !date || !start || !end) {
                    setState('Bitte Raum, Datum und Zeit ausfuellen.', 'text-muted');
                    return;
                }

                try {
                    const url = `${endpointBase}/${roomId}?date=${encodeURIComponent(date)}&start_time=${encodeURIComponent(start)}&end_time=${encodeURIComponent(end)}`;
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        setState('Verfuegbarkeit konnte nicht geprueft werden.', 'text-danger');
                        return;
                    }

                    const data = await response.json();

                    if (data.available) {
                        setState('Raum ist verfuegbar.', 'text-success');
                        return;
                    }

                    const alternatives = (data.alternatives || [])
                        .map(item => item.room_number ? `${item.name} (Nr. ${item.room_number})` : item.name)
                        .join(', ');

                    const suggestion = alternatives ? ` Freie Alternativen: ${alternatives}.` : '';
                    setState((data.message || 'Raum ist nicht verfuegbar.') + suggestion, 'text-warning');
                } catch (error) {
                    setState('Verfuegbarkeit konnte nicht geprueft werden.', 'text-danger');
                }
            }

            toggle.addEventListener('change', function () {
                refreshVisibility();
                checkAvailability();
            });

            [roomSelect, dateInput, startInput, endInput].forEach(function (element) {
                if (element) {
                    element.addEventListener('change', checkAvailability);
                }
            });

            refreshVisibility();
            checkAvailability();
        });
    </script>
@else
    <div class="alert alert-info mt-3 mb-0">
        Fuer direkte Raumbuchungen fehlt die Berechtigung.
    </div>
@endif


