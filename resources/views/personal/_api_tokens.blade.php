{{-- Mein Profil / Profil bearbeiten → Anwendungstokens (API) erstellen und widerrufen (Daten: PaedAppProfileComposer) --}}
<div class="personal-card" id="api-tokens">
    <h3 class="font-semibold text-gray-900 mb-1">Anwendungstokens (API)</h3>
    <p class="text-sm text-gray-500 mb-4">
        Mit einem Anwendungstoken können eigene Skripte oder Programme über die REST-API
        (<span class="font-mono text-gray-800">{{ url('/api/v1') }}</span>) in Ihrem Namen und mit Ihren Rechten
        auf das MitarbeiterBoard zugreifen. Übergeben Sie es im Header
        <span class="font-mono text-gray-800">Authorization: Bearer &lt;Token&gt;</span>.
        Geben Sie Tokens nicht weiter und widerrufen Sie nicht mehr benötigte Tokens.
    </p>

    @if(session('apiTokenPlain'))
        {{-- Ohne Alpine, da nicht jede Profilseite Alpine lädt --}}
        <div class="rounded-lg p-3 mb-4 bg-green-50 border border-green-200">
            <div class="text-sm font-medium text-green-800 mb-2">
                Neues Token „{{ session('apiTokenName') }}“ – jetzt kopieren, es wird nur einmal angezeigt:
            </div>
            <div class="flex items-center gap-2">
                <input type="text" readonly class="personal-input font-mono text-xs" id="api_token_plain"
                       value="{{ session('apiTokenPlain') }}" onfocus="this.select()" aria-label="Neues Anwendungstoken">
                <button type="button" class="btn-personal-secondary text-sm shrink-0"
                        onclick="const b = this; navigator.clipboard?.writeText(document.getElementById('api_token_plain').value).then(() => b.textContent = 'Kopiert')">
                    Kopieren
                </button>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('self-service.api-tokens.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end mb-4">
        @csrf
        <div class="sm:col-span-2">
            <label class="personal-label" for="api_token_name">Bezeichnung</label>
            <input id="api_token_name" type="text" name="name" maxlength="100" required class="personal-input"
                   placeholder="z.B. Export-Skript" value="{{ old('name') }}">
            @error('name', 'apiToken')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="personal-label" for="api_token_laufzeit">Gültig für</label>
            <select id="api_token_laufzeit" name="laufzeit" class="personal-input">
                @foreach($apiTokenLaufzeiten as $tage => $bezeichnung)
                    <option value="{{ $tage }}" @selected((int) old('laufzeit', 90) === $tage)>{{ $bezeichnung }}</option>
                @endforeach
            </select>
            @error('laufzeit', 'apiToken')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-3">
            <button type="submit" class="btn-personal-primary text-sm">Token erstellen</button>
        </div>
    </form>

    @forelse($apiTokens as $apiToken)
        <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
            <div class="min-w-0">
                <div class="font-medium text-gray-900 truncate">{{ $apiToken->name }}</div>
                <div class="text-xs text-gray-500">
                    Zuletzt genutzt: {{ $apiToken->last_used_at ? $apiToken->last_used_at->format('d.m.Y H:i') : 'nie' }}
                    · erstellt am {{ $apiToken->created_at?->format('d.m.Y') }}
                    @if($apiToken->expires_at)
                        · {{ $apiToken->expires_at->isPast() ? 'abgelaufen am' : 'gültig bis' }} {{ $apiToken->expires_at->format('d.m.Y') }}
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('self-service.app-devices.destroy', $apiToken->id) }}" class="shrink-0"
                  onsubmit="return confirm({{ \Illuminate\Support\Js::from('Token „' . $apiToken->name . '“ wirklich widerrufen? Programme, die es nutzen, verlieren sofort den Zugriff.') }})">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-personal-secondary text-sm">Widerrufen</button>
            </form>
        </div>
    @empty
        <p class="text-sm text-gray-400">Keine Anwendungstokens vorhanden.</p>
    @endforelse
</div>
