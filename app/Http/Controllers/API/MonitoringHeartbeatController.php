<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Monitoring\MonitoringDienst;
use App\Services\Monitoring\MonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /api/monitoring/heartbeat – Statusmeldung des Heartbeat-Absenders (z. B. Home Assistant).
 *
 * Authorization: Bearer <Token aus Einstellungen → Monitoring>
 * Body: {"dienste": {"vpn": "ok", "rdp": {"status": "fehler", "meldung": "…", "latenz_ms": 23}}}
 */
class MonitoringHeartbeatController extends Controller
{
    public function __construct(private MonitoringService $monitoring)
    {
    }

    public function store(Request $request): JsonResponse
    {
        if (! $this->monitoring->tokenGueltig($request->bearerToken())) {
            return response()->json(['message' => 'Ungültiges oder fehlendes Token.'], 401);
        }

        $ergebnis = $this->monitoring->heartbeatVerarbeiten($this->dienste($request));

        return response()->json(['status' => 'ok'] + $ergebnis);
    }

    /**
     * @return array<string, array{status: string, meldung: ?string, latenz_ms: ?int}>
     */
    private function dienste(Request $request): array
    {
        $roh = $request->input('dienste', []);

        if (! is_array($roh) || count($roh) > MonitoringService::MAX_HEARTBEAT_DIENSTE) {
            throw ValidationException::withMessages(['dienste' => '„dienste“ muss ein Objekt mit höchstens '.MonitoringService::MAX_HEARTBEAT_DIENSTE.' Einträgen sein.']);
        }

        $dienste = [];

        foreach ($roh as $schluessel => $wert) {
            $schluessel = (string) $schluessel;

            if (! preg_match(MonitoringService::SCHLUESSEL_REGEX, $schluessel) || $schluessel === MonitoringDienst::QUELLE) {
                throw ValidationException::withMessages(['dienste' => "Ungültiger Dienst-Schlüssel „{$schluessel}“ (erlaubt: a–z, 0–9, _ und -, max. 40 Zeichen)."]);
            }

            $eintrag = is_array($wert) ? $wert : ['status' => $wert];
            $status = $eintrag['status'] ?? null;

            if (! in_array($status, ['ok', 'fehler'], true)) {
                throw ValidationException::withMessages(['dienste' => "Status von „{$schluessel}“ muss „ok“ oder „fehler“ sein."]);
            }

            $latenz = $eintrag['latenz_ms'] ?? null;
            $meldung = $eintrag['meldung'] ?? null;

            $dienste[$schluessel] = [
                'status' => $status,
                'latenz_ms' => is_numeric($latenz) && $latenz >= 0 && $latenz <= 600000 ? (int) round((float) $latenz) : null,
                'meldung' => is_string($meldung) && trim($meldung) !== '' ? mb_substr(trim($meldung), 0, 200) : null,
            ];
        }

        return $dienste;
    }
}
