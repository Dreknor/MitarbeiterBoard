{{-- Profil bearbeiten → Pädagogen-App: QR-Code „App verbinden“ und Meine App-Geräte (Daten: PaedAppProfileComposer) --}}
<div class="card" id="paed-app">
    <div class="card-header">
        <h4 class="title">Pädagogen-App</h4>
    </div>
    <div class="card-body">
        <h6>App verbinden</h6>
        <p class="small text-muted">
            Scannen Sie den QR-Code in der Pädagogen-App, um diesen Server auszuwählen.
        </p>
        <div class="text-center mb-2" aria-label="QR-Code zum Verbinden der App">
            {!! $paedAppQrCode !!}
        </div>
        <p class="small text-muted text-center" style="word-break: break-all;">
            Serveradresse: <span class="text-monospace text-dark">{{ $paedAppServerUrl }}</span>
        </p>

        <h6 class="mt-4">Meine App-Geräte</h6>
        @forelse($paedAppDevices as $device)
            <div class="d-flex justify-content-between align-items-center py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                <div class="small" style="min-width: 0;">
                    <strong>{{ $device->name }}</strong><br>
                    <span class="text-muted">
                        Zuletzt genutzt: {{ $device->last_used_at ? $device->last_used_at->format('d.m.Y H:i') : 'nie' }}
                        @if($device->expires_at)
                            · gültig bis {{ $device->expires_at->format('d.m.Y') }}
                        @endif
                    </span>
                </div>
                <form method="POST" action="{{ route('self-service.app-devices.destroy', $device->id) }}" class="ml-2">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Gerät „{{ addslashes($device->name) }}“ wirklich abmelden?')">
                        Abmelden
                    </button>
                </form>
            </div>
        @empty
            <p class="small text-muted mb-0">Keine Geräte angemeldet.</p>
        @endforelse
    </div>
</div>
