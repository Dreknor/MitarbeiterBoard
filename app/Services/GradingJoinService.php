<?php

namespace App\Services;

use App\Models\GradingDocumentationSession;
use App\Models\GradingJoinCode;
use App\Models\GradingStudentDevice;
use App\Models\User;
use App\Services\Api\PaedAppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Selbsteinschätzung auf Schüler-iPads: Beitrittscodes und Schüler-Tokens.
 *
 * Schüler-Tokens gehören dem Modell GradingStudentDevice (nicht der Lehrkraft) und tragen die
 * Ability "student-grading:{session_id}:{schueler_id}". Sie laufen mit dem Code ab und werden
 * beim Widerruf oder Abschluss der Session ungültig.
 */
class GradingJoinService
{
    public function __construct(
        private GradingSessionService $sessions,
        private PaedAppService $app
    ) {
    }

    /**
     * Erzeugt je Teilnehmer einen Code. Noch gültige Codes werden wiederverwendet.
     *
     * @return Collection<GradingJoinCode>
     */
    public function createCodes(GradingDocumentationSession $session, User $user): Collection
    {
        $expiresAt = now()->addHours((int) config('paed_app.join_code_hours', 8));

        return DB::transaction(function () use ($session, $user, $expiresAt) {
            $existing = GradingJoinCode::where('session_id', $session->id)->active()->get()->keyBy('schueler_id');

            return $this->sessions->participants($session)->map(function ($schueler) use ($existing, $session, $user, $expiresAt) {
                $code = $existing->get($schueler->id) ?? GradingJoinCode::create([
                    'session_id' => $session->id,
                    'schueler_id' => $schueler->id,
                    'code' => GradingJoinCode::generateUniqueCode(),
                    'expires_at' => $expiresAt,
                    'created_by' => $user->id,
                ]);

                return $code->setRelation('schueler', $schueler);
            })->values();
        });
    }

    public function payload(GradingJoinCode $code): array
    {
        return [
            'schueler_id' => (int) $code->schueler_id,
            'firstname' => $code->schueler?->vorname,
            'code' => $code->display_code,
            'qr_payload' => $this->app->deepLink('join', ['server' => $this->app->serverUrl(), 'code' => $code->code]),
            'expires_at' => $code->expires_at->toIso8601String(),
        ];
    }

    /**
     * Widerruft alle Codes der Session inkl. der Schüler-Geräte und ihrer Tokens.
     *
     * @return int  Anzahl widerrufener Codes
     */
    public function revoke(GradingDocumentationSession $session): int
    {
        return DB::transaction(function () use ($session) {
            $deviceIds = GradingStudentDevice::where('session_id', $session->id)->pluck('id');

            PersonalAccessToken::where('tokenable_type', GradingStudentDevice::class)
                ->whereIn('tokenable_id', $deviceIds)
                ->delete();
            GradingStudentDevice::whereIn('id', $deviceIds)->delete();

            return GradingJoinCode::where('session_id', $session->id)->delete();
        });
    }

    /**
     * Beitritt eines Schüler-Geräts. Ein erneuter Beitritt mit demselben Code ersetzt das
     * bisherige Gerät (z.B. nach einem App-Neustart auf dem geteilten iPad).
     *
     * @return array{0: GradingStudentDevice, 1: string}|null  Gerät und Klartext-Token
     */
    public function join(string $code, string $deviceName): ?array
    {
        $joinCode = GradingJoinCode::where('code', GradingJoinCode::normalize($code))
            ->active()
            ->with(['session', 'schueler'])
            ->first();

        if (!$joinCode || !$joinCode->session || $joinCode->session->isCompleted() || !$joinCode->schueler) {
            return null;
        }

        return DB::transaction(function () use ($joinCode, $deviceName) {
            $this->revokeDevices($joinCode);

            $device = GradingStudentDevice::create([
                'join_code_id' => $joinCode->id,
                'session_id' => $joinCode->session_id,
                'schueler_id' => $joinCode->schueler_id,
                'device_name' => mb_substr($deviceName, 0, 100),
                'expires_at' => $joinCode->expires_at,
            ]);

            $token = $device->createToken($device->device_name, [$device->ability()], $joinCode->expires_at);

            return [$device->setRelation('joinCode', $joinCode), $token->plainTextToken];
        });
    }

    private function revokeDevices(GradingJoinCode $joinCode): void
    {
        $deviceIds = $joinCode->devices()->pluck('id');

        PersonalAccessToken::where('tokenable_type', GradingStudentDevice::class)
            ->whereIn('tokenable_id', $deviceIds)
            ->delete();
        GradingStudentDevice::whereIn('id', $deviceIds)->delete();
    }
}
