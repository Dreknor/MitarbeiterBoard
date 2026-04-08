<?php

namespace App\Services\Personal;

use App\Models\personal\PersonalAccessLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Log;

/**
 * Verwaltet Audit-Trail-Zugriffe und Lesezugriff-Protokollierung.
 *
 * Implementierung folgt in Phase 1 (P1-02).
 */
class PersonalAuditService
{
    /**
     * Bereinigt Logs älter als 2 Jahre.
     * AUSNAHME: Logs mit action='deletion' bleiben 10 Jahre.
     */
    public function cleanupOldLogs(): int
    {
        $deleted = PersonalAccessLog::where('created_at', '<', now()->subYears(2))
            ->where('action', '!=', 'deletion')
            ->delete();

        Log::info("PersonalAuditService: {$deleted} alte Log-Einträge bereinigt.");
        return $deleted;
    }

    /**
     * Gibt paginierten Zugriffsverlauf für eine bestimmte Ressource zurück.
     */
    public function getAccessLogs(string $resourceType, int $resourceId): LengthAwarePaginator
    {
        return PersonalAccessLog::where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * Artisan-Command-Logik: Re-Encryption bei APP_KEY-Rotation.
     * Betrifft: EmployeeReview.notes, BemCase.meeting_notes, BemCase.measures
     *
     * WICHTIG: Nutzt OLD_APP_KEY zum Entschlüsseln und den aktuellen APP_KEY zum Re-Encryptieren.
     * In .env muss OLD_APP_KEY gesetzt sein (der alte Schlüssel VOR der Rotation).
     *
     * @throws \RuntimeException wenn OLD_APP_KEY nicht gesetzt
     */
    public function reEncryptSensitiveFields(): void
    {
        $oldKey = config('app.old_key');
        if (!$oldKey) {
            throw new \RuntimeException('OLD_APP_KEY ist nicht in .env gesetzt. Abbruch.');
        }

        // Temporären Encrypter mit dem ALTEN Key erstellen
        $rawKey = base64_decode(str_replace('base64:', '', $oldKey));
        $oldEncrypter = new Encrypter($rawKey, config('app.cipher'));

        $this->reEncryptModel(\App\Models\personal\EmployeeReview::class, ['notes'], $oldEncrypter);
        $this->reEncryptModel(\App\Models\personal\BemCase::class, ['meeting_notes', 'measures'], $oldEncrypter);
    }

    private function reEncryptModel(string $modelClass, array $fields, Encrypter $oldEncrypter): void
    {
        if (!class_exists($modelClass)) {
            Log::warning("PersonalAuditService: Modell {$modelClass} nicht gefunden, übersprungen.");
            return;
        }

        $modelClass::chunkById(100, function ($records) use ($fields, $oldEncrypter, $modelClass) {
            foreach ($records as $record) {
                $changed = false;
                foreach ($fields as $field) {
                    $raw = $record->getRawOriginal($field);
                    if (!$raw) continue;
                    try {
                        $decrypted = $oldEncrypter->decrypt($raw);
                        $record->$field = $decrypted; // Nutzt aktuellen APP_KEY via Accessor/Mutator
                        $changed = true;
                    } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                        Log::error("Re-Encryption fehlgeschlagen für {$modelClass} #{$record->id} ({$field}): {$e->getMessage()}");
                    }
                }
                if ($changed) {
                    $record->saveQuietly();
                }
            }
        });
    }
}
