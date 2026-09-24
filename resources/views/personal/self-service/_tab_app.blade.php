{{-- Tab: Pädagogen-App im Self-Service (Daten: PaedAppProfileComposer) --}}
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    {{-- Kachel: App verbinden --}}
    <div class="personal-card">
        <h3 class="font-semibold text-gray-900 mb-1">App verbinden</h3>
        <p class="text-sm text-gray-500 mb-4">
            Scannen Sie den QR-Code in der Pädagogen-App, um diesen Server auszuwählen.
            Die Anmeldung erfolgt anschließend in der App.
        </p>
        <div class="flex flex-col items-center gap-3">
            <div class="bg-white p-2 rounded-lg border border-gray-200" aria-label="QR-Code zum Verbinden der App">
                {!! $paedAppQrCode !!}
            </div>
            <div class="text-xs text-gray-500 text-center break-all">
                Oder Serveradresse manuell eingeben:<br>
                <span class="font-mono text-gray-800">{{ $paedAppServerUrl }}</span>
            </div>
        </div>
    </div>

    {{-- Meine App-Geräte --}}
    <div class="personal-card">
        <h3 class="font-semibold text-gray-900 mb-1">Meine App-Geräte</h3>
        <p class="text-sm text-gray-500 mb-4">
            Geräte, auf denen Sie in der Pädagogen-App angemeldet sind. Melden Sie verlorene oder
            nicht mehr genutzte Geräte hier ab.
        </p>

        @forelse($paedAppDevices as $device)
        <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
            <div class="min-w-0">
                <div class="font-medium text-gray-900 truncate">{{ $device->name }}</div>
                <div class="text-xs text-gray-500">
                    Zuletzt genutzt: {{ $device->last_used_at ? $device->last_used_at->format('d.m.Y H:i') : 'nie' }}
                    · angemeldet seit {{ $device->created_at?->format('d.m.Y') }}
                    @if($device->expires_at)
                        · gültig bis {{ $device->expires_at->format('d.m.Y') }}
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('self-service.app-devices.destroy', $device->id) }}" class="shrink-0">
                @csrf
                @method('DELETE')
                <button type="submit"
                        onclick="return confirm('Gerät „{{ addslashes($device->name) }}“ wirklich abmelden?')"
                        class="btn-personal-secondary text-sm">
                    Abmelden
                </button>
            </form>
        </div>
        @empty
        <p class="text-sm text-gray-400">Keine Geräte angemeldet.</p>
        @endforelse
    </div>
</div>
