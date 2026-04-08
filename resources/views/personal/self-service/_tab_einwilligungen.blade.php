{{-- Tab: Einwilligungen im Self-Service --}}
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="font-semibold text-gray-900">Meine Datenschutz-Einwilligungen</h3>
    </div>
    <p class="text-sm text-gray-500">Sie können Ihre Einwilligungen jederzeit widerrufen. Ein Widerruf wirkt sich unmittelbar auf die betroffenen Funktionen aus.</p>

    @if(isset($consentTypes))
    @foreach($consentTypes as $type)
    @php $consent = isset($myConsents) ? ($myConsents[$type->id] ?? null) : null; @endphp
    <div class="personal-card">
        <div class="flex items-start justify-between gap-4">
            <div class="flex-1">
                <h4 class="font-semibold text-gray-900">{{ $type->name }}</h4>
                <p class="text-sm text-gray-500 mt-1">{{ $type->description }}</p>
                <p class="text-xs text-gray-400 mt-1">Rechtsgrundlage: {{ $type->legal_basis }}</p>
                <div class="mt-2">
                    @if($consent && $consent->isActive())
                    <span class="badge-green">✓ Einwilligung erteilt am {{ $consent->granted_at->format('d.m.Y') }}</span>
                    @else
                    <span class="badge-gray">Keine Einwilligung</span>
                    @endif
                </div>
            </div>
            <div class="shrink-0">
                @if($consent && $consent->isActive())
                <form method="POST" action="{{ route('self-service.consents.revoke', $type) }}">
                    @csrf
                    <button type="submit"
                            onclick="return confirm('Einwilligung wirklich widerrufen?')"
                            class="btn-personal-secondary text-sm text-red-600 hover:text-red-700">
                        Widerrufen
                    </button>
                </form>
                @else
                <form method="POST" action="{{ route('self-service.consents.grant', $type) }}">
                    @csrf
                    <button type="submit" class="btn-personal-primary text-sm">
                        Einwilligung erteilen
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>
    @endforeach
    @else
    <div class="personal-card text-center text-gray-400 py-8">
        Keine Einwilligungstypen konfiguriert
    </div>
    @endif
</div>

