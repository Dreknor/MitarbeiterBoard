<?php

namespace App\Services\Personal;

use App\Enums\DocumentStatus;
use App\Enums\SyncStatus;
use App\Jobs\Personal\UploadDocumentToNextcloud;
use App\Models\Group;
use App\Models\personal\DocumentTemplate;
use App\Models\personal\DocumentType;
use App\Models\personal\PersonalDocument;
use App\Models\User;
use App\Notifications\Personal\DocumentExpiringNotification;
use App\Services\Personal\Contracts\NextcloudFileServiceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PersonalDocumentService
{
    public function __construct(
        private readonly NextcloudFileServiceInterface $nc
    ) {}

    /**
     * Dokument aus PHPWord-Vorlage generieren.
     * Gibt lokalen Temp-Pfad zurück.
     */
    public function generateFromTemplate(DocumentTemplate $template, User $employe, array $data = []): string
    {
        $templatePath = storage_path('app/templates/personal/' . basename($template->template_path));

        if (! file_exists($templatePath)) {
            throw new \RuntimeException("Vorlagen-Datei nicht gefunden: {$templatePath}");
        }

        $processor = new \PhpOffice\PhpWord\TemplateProcessor($templatePath);

        $defaults = $this->buildDefaultPlaceholders($employe);
        foreach (array_merge($defaults, $data) as $key => $value) {
            $processor->setValue($key, (string) $value);
        }

        $tmpDir = storage_path('app/tmp');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $tmpPath = $tmpDir . '/personal_' . uniqid() . '.docx';
        $processor->saveAs($tmpPath);

        return $tmpPath;
    }

    /**
     * Dokument hochladen (dispatcht Queue-Job, gibt sofort zurück).
     */
    public function uploadToNextcloud(User $employe, string $localPath, DocumentType $type, array $meta = []): PersonalDocument
    {
        $employePath = $this->getEmployeePath($employe);
        $filename    = $type->nextcloud_subfolder . '/' . basename($localPath);
        $remotePath  = $employePath . '/' . $filename;

        $document = PersonalDocument::create(array_merge([
            'employe_id'       => $employe->id,
            'document_type_id' => $type->id,
            'title'            => $meta['title'] ?? basename($localPath),
            'nextcloud_path'   => $remotePath,
            'sync_status'      => SyncStatus::Pending,
            'status'           => DocumentStatus::Aktuell,
            'uploaded_by'      => auth()->id() ?? $employe->id,
            'reminder_days'    => $type->default_reminder_days,
        ], $meta));

        UploadDocumentToNextcloud::dispatch($document, $localPath);

        return $document;
    }

    /**
     * Datei von Nextcloud streamen (kein direkter NC-Link in der UI).
     */
    public function downloadFromNextcloud(PersonalDocument $document): StreamedResponse
    {
        $content = $this->nc->downloadFile($document->nextcloud_path);

        if ($content === false) {
            abort(404, 'Dokument nicht in Nextcloud gefunden.');
        }

        $filename = basename($document->nextcloud_path);

        return response()->streamDownload(
            fn () => print($content),
            $filename
        );
    }

    /**
     * Nextcloud-Pfad für einen Mitarbeiter ermitteln.
     * Format: /Personal/{PrimäreGruppe}/{Status}/{Nachname}_{Vorname}_{ID}
     */
    public function getEmployeePath(User $employe): string
    {
        $group  = $this->getPrimaryGroup($employe);
        $status = $employe->employments()->where('status', 'aktiv')->exists() ? 'Angestellt' : 'Ausgeschieden';
        $name   = $this->sanitizeFolderName(
            ($employe->familienname ?: '') . '_' . ($employe->vorname ?: '') . '_' . $employe->id
        );
        $base   = config('nextcloud.personal.base_path', '/Personal');

        $groupName = $group?->name ?? 'Allgemein';

        return "{$base}/{$groupName}/{$status}/{$name}";
    }

    /**
     * Primäre Gruppe ermitteln (manuelle Überschreibung oder Anstellung mit den meisten Stunden).
     */
    private function getPrimaryGroup(User $employe): ?Group
    {
        if ($employe->employe_data?->primary_department_id) {
            return Group::find($employe->employe_data->primary_department_id);
        }

        $employment = $employe->employments()
            ->where('status', 'aktiv')
            ->orderByDesc('hours')
            ->orderBy('start')
            ->first();

        if ($employment?->department) return $employment->department;

        return $employe->groups_rel()->first();
    }

    private function sanitizeFolderName(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9äöüÄÖÜß_\-]/', '_', $name);
    }

    private function buildDefaultPlaceholders(User $employe): array
    {
        $employment = $employe->employments()->where('status', 'aktiv')->first();

        return [
            'VORNAME'           => $employe->vorname ?? '',
            'NACHNAME'          => $employe->familienname ?? '',
            'GEBURTSDATUM'      => $employe->employe_data?->geburtstag?->format('d.m.Y') ?? '',
            'ADRESSE_STRASSE'   => $employe->address?->strasse ?? '',
            'ADRESSE_PLZ'       => $employe->address?->plz ?? '',
            'ADRESSE_ORT'       => $employe->address?->ort ?? '',
            'ANSTELLUNGSBEGINN' => $employment?->start?->format('d.m.Y') ?? '',
            'WOCHENSTUNDEN'     => (string) ($employment?->hours ?? ''),
            'ABTEILUNG'         => $employment?->department?->name ?? '',
            'TARIFGRUPPE'       => $employment?->salary_group ?? '',
            'STUFE'             => $employment?->salary_level ?? '',
            'PROBEZEIT_ENDE'    => $employment?->probation_end?->format('d.m.Y') ?? '',
            'DATUM_HEUTE'       => now()->format('d.m.Y'),
        ];
    }

    /**
     * Ablaufende Dokumente prüfen und Erinnerungen senden (Scheduler-Job).
     */
    public function checkExpiringDocuments(): void
    {
        $expiring = PersonalDocument::whereNotNull('expiry_date')
            ->whereNotNull('reminder_days')
            ->where('status', DocumentStatus::Aktuell->value)
            ->whereNull('reminder_sent_at')
            ->whereRaw('DATE_SUB(expiry_date, INTERVAL reminder_days DAY) <= CURDATE()')
            ->get();

        foreach ($expiring as $document) {
            Notification::send(
                User::permission('manage personal_documents')->get(),
                new DocumentExpiringNotification($document)
            );
            $document->update(['reminder_sent_at' => now()]);
        }

        Log::info("Personal: {$expiring->count()} ablaufende Dokument-Erinnerungen versendet.");
    }
}

