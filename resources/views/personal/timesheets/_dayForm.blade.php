{{-- Formularfelder einer Zeitbuchung (Anlegen/Bearbeiten) --}}
<div class="grid grid-cols-2 gap-3" x-data="{ start: @js(old('start', $werte['start'] ?? '')), ende: @js(old('end', $werte['end'] ?? '')), pause: @js((string) old('pause', $werte['pause'] ?? '')) }">
    <div>
        <label for="start" class="zw-label">Beginn</label>
        <input type="time" name="start" id="start" x-model="start" @if($maxZeit ?? null) max="{{ $maxZeit }}" @endif class="zw-input @error('start') is-invalid @enderror" required>
        @error('start') <p class="zw-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="end" class="zw-label">Ende</label>
        <input type="time" name="end" id="end" x-model="ende" @if($maxZeit ?? null) max="{{ $maxZeit }}" @endif class="zw-input @error('end') is-invalid @enderror" required>
        @error('end') <p class="zw-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="pause" class="zw-label">Pause (Minuten)</label>
        <input type="number" name="pause" id="pause" min="0" max="600" step="5" x-model="pause" inputmode="numeric" class="zw-input @error('pause') is-invalid @enderror">
        @error('pause') <p class="zw-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="comment" class="zw-label">Bemerkung</label>
        <input type="text" name="comment" id="comment" maxlength="60" class="zw-input" value="{{ old('comment', $werte['comment'] ?? '') }}">
    </div>
    @if($maxZeit ?? null)
        <p class="col-span-2 zw-hint -mt-1"><i class="fas fa-info-circle mr-1"></i>Für heute nur bis {{ $maxZeit }} Uhr – spätere Zeiten bitte nachträglich erfassen.</p>
    @endif
    <template x-if="start && ende && ende > start">
        <p class="col-span-2 text-sm text-gray-600" x-data="{ get minuten() { const [a,b] = start.split(':').map(Number); const [c,d] = ende.split(':').map(Number); return (c*60+d) - (a*60+b); } }">
            Anwesenheit <strong x-text="Math.floor(minuten/60) + ':' + String(minuten%60).padStart(2,'0')"></strong> h
            <span x-show="minuten > 540 && (Number(pause) || 0) < 45" class="text-amber-700"> · gesetzlich mindestens 45 Minuten Pause (über 9 Stunden)</span>
            <span x-show="minuten > 360 && minuten <= 540 && (Number(pause) || 0) < 30" class="text-amber-700"> · gesetzlich mindestens 30 Minuten Pause (über 6 Stunden)</span>
            <span x-show="minuten - (Number(pause) || 0) > 600" class="text-red-600"> · mehr als 10 Stunden Arbeitszeit</span>
        </p>
    </template>
</div>
