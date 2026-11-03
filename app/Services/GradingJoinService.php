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
    public const CODE_BEREITS_VERWENDET = 'Dieser Code wurde bereits auf einem Gerät verwendet. Bitte die Lehrkraft, das Gerät zurückzusetzen.';

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
     * Beitritt eines Schüler-Geräts. Ein Code gilt nur für EIN Gerät: Ist bereits ein Gerät
     * mit dem Code angemeldet, wird der Beitritt abgelehnt (sonst könnte ein Mitschüler mit
     * einem abgeschriebenen Code das Gerät übernehmen). Die Lehrkraft setzt das Gerät zurück
     * (geraetZuruecksetzen() bzw. Widerruf aller Codes) und erzeugt danach einen neuen Code.
     *
     * @return array{0: GradingStudentDevice, 1: string}|null  Gerät und Klartext-Token, null bei ungültigem Code
     * @throws \DomainException wenn der Code bereits von einem Gerät verwendet wird
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
            // Sperre gegen gleichzeitige Beitritte mit demselben Code
            GradingJoinCode::whereKey($joinCode->id)->lockForUpdate()->first();

            if ($joinCode->devices()->exists()) {
                throw new \DomainException(self::CODE_BEREITS_VERWENDET);
            }

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

    /**
     * Setzt das Gerät eines Schülers zurück: Gerät, Token und Code werden gelöscht.
     * Ein neuer Code entsteht beim nächsten createCodes().
     *
     * @return bool  true, wenn ein Code vorhanden war
     */
    public function geraetZuruecksetzen(GradingDocumentationSession $session, int $schuelerId): bool
    {
        return DB::transaction(function () use ($session, $schuelerId) {
            $codes = GradingJoinCode::where('session_id', $session->id)->where('schueler_id', $schuelerId)->get();
            $codes->each(fn (GradingJoinCode $code) => $this->revokeDevices($code));

            return GradingJoinCode::whereIn('id', $codes->pluck('id'))->delete() > 0;
        });
    }

    /**
     * Räumt abgelaufene Codes sowie abgelaufene oder verwaiste Schüler-Geräte (Session
     * abgeschlossen/gelöscht) samt Sanctum-Tokens auf.
     *
     * @return array{codes: int, devices: int, tokens: int}
     */
    public function aufraeumen(): array
    {
        return DB::transaction(function () {
            $expiredCodeIds = GradingJoinCode::where('expires_at', '<', now())->pluck('id');

            $deviceIds = GradingStudentDevice::query()
                ->where(fn ($q) => $q->where('expires_at', '<', now())
                    ->orWhereIn('join_code_id', $expiredCodeIds)
                    ->orWhereDoesntHave('session')
                    ->orWhereHas('session', fn ($s) => $s->whereNotNull('completed_at')))
                ->pluck('id');

            // Tokens gelöschter Geräte (tokenable fehlt) ebenfalls entfernen
            $tokens = PersonalAccessToken::where('tokenable_type', GradingStudentDevice::class)
                ->where(fn ($q) => $q->whereIn('tokenable_id', $deviceIds)
                    ->orWhereNotIn('tokenable_id', GradingStudentDevice::query()->select('id')))
                ->delete();
            $devices = GradingStudentDevice::whereIn('id', $deviceIds)->delete();
            $codes = GradingJoinCode::whereIn('id', $expiredCodeIds)->delete();

            return ['codes' => $codes, 'devices' => $devices, 'tokens' => $tokens];
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
