{{-- Profil bearbeiten → Pädagogen-App: QR-Code „App verbinden“ und Meine App-Geräte (Daten: PaedAppProfileComposer) --}}
<div class="personal-card" id="paed-app">
    <h2 class="text-base font-semibold text-gray-900 mb-4">Pädagogen-App</h2>

    <h3 class="text-sm font-semibold text-gray-700 mb-1">App verbinden</h3>
    <p class="text-xs text-gray-500 mb-3">Scannen Sie den QR-Code in der Pädagogen-App, um diesen Server auszuwählen.</p>
    <div class="flex flex-col items-center gap-2 mb-4">
        <div class="bg-white p-2 rounded-lg border border-gray-200" aria-label="QR-Code zum Verbinden der App">
            {!! $paedAppQrCode !!}
        </div>
        <p class="text-xs text-gray-500 text-center break-all">
            Serveradresse: <span class="font-mono text-gray-800">{{ $paedAppServerUrl }}</span>
        </p>
    </div>

    <h3 class="text-sm font-semibold text-gray-700 mb-1">Meine App-Geräte</h3>
    @forelse($paedAppDevices as $device)
        <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-gray-100' : '' }}">
            <div class="min-w-0 text-sm">
                <div class="font-medium text-gray-900 truncate">{{ $device->name }}</div>
                <div class="text-xs text-gray-500">
                    Zuletzt genutzt: {{ $device->last_used_at ? $device->last_used_at->format('d.m.Y H:i') : 'nie' }}
                    @if($device->expires_at)
                        · gültig bis {{ $device->expires_at->format('d.m.Y') }}
                    @endif
                </div>
            </div>
            <form method="POST" action="{{ route('self-service.app-devices.destroy', $device->id) }}" class="shrink-0"
                  onsubmit="return confirm({{ \Illuminate\Support\Js::from('Gerät „' . $device->name . '“ wirklich abmelden?') }})">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-personal-secondary text-xs">Abmelden</button>
            </form>
        </div>
    @empty
        <p class="text-xs text-gray-400">Keine Geräte angemeldet.</p>
    @endforelse
</div>
