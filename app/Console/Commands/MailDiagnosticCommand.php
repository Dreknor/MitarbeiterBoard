<?php

namespace App\Console\Commands;

use App\Mail\DailyAbsenceReport;
use App\Mail\InvitationMail;
use App\Mail\MeetingInvitationMail;
use App\Mail\NewAbsenceMail;
use App\Mail\NewThemeMail;
use App\Mail\ReminderMail;
use App\Mail\StepErinnerungMail;
use App\Mail\newPostsMail;
use App\Mail\newStepMail;
use App\Mail\newTaskMail;
use App\Mail\remindTaskMail;
use App\Models\Group;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email as SymfonyEmail;

class MailDiagnosticCommand extends Command
{
    protected $signature = 'mail:diagnose
                            {email?          : E-Mail-Adresse des Empfängers}
                            {--type=alle     : Welche Mail testen (alle, einfach, meeting, aufgabe, schritt, abwesenheit, einladung, mitteilung, thema, prozess)}
                            {--list          : Alle verfügbaren Mail-Typen auflisten}
                            {--dry-run       : Mail nur rendern, nicht versenden}
                            {--dump-headers  : MIME-Header der generierten E-Mail ausgeben}
                            {--dump-ical     : ICS-Inhalt der Meeting-Einladung ausgeben}';

    protected $description = 'Diagnose-Tool: Testet den Versand und die Struktur aller E-Mail-Typen';

    /**
     * Alle verfügbaren Test-Mails mit ihren Metadaten.
     */
    private function mailDefinitions(): array
    {
        return [
            'einfach' => [
                'label'       => 'Einfache Erinnerung (ReminderMail)',
                'description' => 'Keine Parameter, kein Attachment – die einfachste Mail im System.',
                'class'       => ReminderMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new ReminderMail(),
            ],
            'aufgabe' => [
                'label'       => 'Aufgaben-Erinnerung (remindTaskMail)',
                'description' => 'HTML-Mail mit Liste ausstehender Aufgaben (überfällig, heute, zukünftig).',
                'class'       => remindTaskMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new remindTaskMail('Test-Benutzer', $this->buildTestTasks()),
            ],
            'schritt' => [
                'label'       => 'Prozessschritt-Erinnerung (StepErinnerungMail)',
                'description' => 'Erinnerung an offene Prozessschritte.',
                'class'       => StepErinnerungMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new StepErinnerungMail('Test-Benutzer', $this->buildTestSteps()),
            ],
            'meeting' => [
                'label'       => 'Meeting-Einladung (MeetingInvitationMail)',
                'description' => 'HTML-Mail mit ICS-Kalender-Anhang (text/calendar + application/ics).',
                'class'       => MeetingInvitationMail::class,
                'hasAttachment' => true,
                'factory'     => fn (string $email) => $this->buildMeetingMail($email),
            ],
            'abwesenheit' => [
                'label'       => 'Neue Abwesenheit (NewAbsenceMail)',
                'description' => 'Benachrichtigung über eine neue Abwesenheitsmeldung.',
                'class'       => NewAbsenceMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new NewAbsenceMail(
                    'Max Muster', now()->format('d.m.Y'), now()->addDays(3)->format('d.m.Y'), 'Krankheit'
                ),
            ],
            'abwesenheit-report' => [
                'label'       => 'Täglicher Abwesenheitsbericht (DailyAbsenceReport)',
                'description' => 'Tagesübersicht aller gemeldeten Abwesenheiten.',
                'class'       => DailyAbsenceReport::class,
                'hasAttachment' => false,
                'factory'     => fn () => new DailyAbsenceReport($this->buildTestAbsences()),
            ],
            'einladung' => [
                'label'       => 'Gruppen-Themen-Einladung (InvitationMail)',
                'description' => 'Übersicht der besprochenen Themen für ein Gruppentreffen.',
                'class'       => InvitationMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new InvitationMail(
                    'Test-Gruppe', now()->addDays(2)->format('d.m.Y'), $this->buildTestThemes()
                ),
            ],
            'neue-aufgabe' => [
                'label'       => 'Neue Aufgabe (newTaskMail)',
                'description' => 'Benachrichtigung über eine neu zugewiesene Aufgabe.',
                'class'       => newTaskMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new newTaskMail(
                    'Test-Benutzer', now()->addDays(5)->format('d.m.Y'),
                    'Quartalsbericht fertigstellen', 'Berichtswesen',
                    'test-gruppe', 'Test-Gruppe'
                ),
            ],
            'neues-thema' => [
                'label'       => 'Neues Thema (NewThemeMail)',
                'description' => 'Benachrichtigung über ein neu angelegtes Thema.',
                'class'       => NewThemeMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new NewThemeMail(
                    'Digitalisierung der Verwaltung', 999, 'Test-Gruppe'
                ),
            ],
            'prozess' => [
                'label'       => 'Neuer Prozessschritt (newStepMail)',
                'description' => 'Benachrichtigung über einen Fortschritt in einem Prozess.',
                'class'       => newStepMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new newStepMail(
                    'Test-Benutzer', now()->format('d.m.Y'),
                    'Genehmigung durch Vorgesetzten', 'Urlaubsantrag', 1
                ),
            ],
            'mitteilung' => [
                'label'       => 'Neue Mitteilungen (newPostsMail)',
                'description' => 'Zusammenfassung neuer Mitteilungen/Posts.',
                'class'       => newPostsMail::class,
                'hasAttachment' => false,
                'factory'     => fn () => new newPostsMail($this->buildTestPosts()),
            ],
        ];
    }

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listMailTypes();
        }

        $email = $this->argument('email')
            ?: $this->ask('Bitte die E-Mail-Adresse des Empfängers eingeben');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('❌ Ungültige E-Mail-Adresse: ' . $email);
            return Command::FAILURE;
        }

        $type = $this->option('type');
        $dryRun = $this->option('dry-run');
        $dumpHeaders = $this->option('dump-headers');
        $dumpIcal = $this->option('dump-ical');
        $definitions = $this->mailDefinitions();

        // Einzelnen Typ oder alle?
        if ($type !== 'alle') {
            if (! isset($definitions[$type])) {
                $this->error("❌ Unbekannter Mail-Typ: {$type}");
                $this->line('Verfügbare Typen: ' . implode(', ', array_keys($definitions)));
                return Command::FAILURE;
            }
            $definitions = [$type => $definitions[$type]];
        }

        $this->printMailConfig();
        $this->newLine();

        $gesamtErfolg = 0;
        $gesamtFehler = 0;

        foreach ($definitions as $key => $def) {
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📧 [{$key}] {$def['label']}");
            $this->line("   {$def['description']}");
            $this->newLine();

            try {
                $mailable = $this->createMailable($def, $email);
                $this->line('   <fg=green>✓</> Mail-Objekt erstellt');

                // MIME-Struktur analysieren
                $this->analyzeMailStructure($mailable, $key, $dumpHeaders, $dumpIcal);

                if ($dryRun) {
                    $this->warn('   ⏭  --dry-run: Mail wird NICHT versendet.');
                    $gesamtErfolg++;
                    continue;
                }

                // Tatsächlicher Versand
                $this->sendAndVerify($mailable, $email, $key);
                $gesamtErfolg++;

            } catch (\Throwable $e) {
                $gesamtFehler++;
                $this->error("   ❌ FEHLER bei [{$key}]: " . $e->getMessage());
                $this->line('   📁 ' . $e->getFile() . ':' . $e->getLine());

                if ($this->getOutput()->isVerbose()) {
                    $this->line($e->getTraceAsString());
                }

                Log::error("mail:diagnose Fehler bei [{$key}]", [
                    'type'  => $key,
                    'error' => $e->getMessage(),
                    'file'  => $e->getFile() . ':' . $e->getLine(),
                ]);
            }

            $this->newLine();
        }

        // Zusammenfassung
        $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->info('📊 Zusammenfassung');
        $this->line("   Empfänger:  {$email}");
        $this->line("   Erfolgreich: <fg=green>{$gesamtErfolg}</>");
        $this->line("   Fehlerhaft:  " . ($gesamtFehler > 0 ? "<fg=red>{$gesamtFehler}</>" : '0'));
        $this->line("   Modus:       " . ($dryRun ? 'Dry-Run (kein Versand)' : 'Live-Versand'));

        if ($gesamtFehler > 0) {
            $this->newLine();
            $this->error('⚠  Es gab Fehler – Details in den Logs (storage/logs/laravel.log).');
        }

        return $gesamtFehler > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * Erstellt das Mailable-Objekt über die Factory-Funktion.
     */
    private function createMailable(array $def, string $email): \Illuminate\Mail\Mailable
    {
        $factory = $def['factory'];
        $reflection = new \ReflectionFunction($factory);
        $params = $reflection->getNumberOfParameters();

        return $params > 0 ? $factory($email) : $factory();
    }

    /**
     * Analysiert die MIME-Struktur einer Mail und gibt Warnungen bei Problemen aus.
     */
    private function analyzeMailStructure(\Illuminate\Mail\Mailable $mailable, string $key, bool $dumpHeaders, bool $dumpIcal): void
    {
        // Mail rendern – gibt den fertigen HTML-String zurück
        $renderedHtml = $mailable->render();

        // Symfony-Email-Objekt für Header-Analyse zusammenbauen
        $msg = new SymfonyEmail();
        $msg->to('analyse@example.com');
        $msg->from(config('mail.from.address', 'test@example.com'));
        $msg->subject($mailable->subject ?? 'Analyse');
        $msg->html($renderedHtml);

        // Callbacks anwenden (z.B. withSymfonyMessage für ICS-Part)
        $callbacks = $this->extractCallbacks($mailable);
        foreach ($callbacks as $cb) {
            $cb($msg);
        }

        $this->line('   <fg=green>✓</> Mail gerendert (View)');

        // Attachments prüfen
        $attachments = $mailable->rawAttachments ?? [];
        $diskAttachments = $mailable->attachments ?? [];
        $totalAttachments = count($attachments) + count($diskAttachments);

        if ($totalAttachments > 0) {
            $this->line("   📎 Anhänge: {$totalAttachments}");
            foreach ($attachments as $att) {
                $name = $att['name'] ?? 'unbenannt';
                $mime = $att['options']['mime'] ?? 'nicht angegeben';
                $size = isset($att['data']) ? strlen($att['data']) : 0;
                $this->line("      - {$name} ({$mime}, {$this->formatBytes($size)})");
            }
        }

        // Spezielle Checks für Meeting-Einladungen
        if ($key === 'meeting') {
            $this->validateMeetingIcal($mailable, $dumpIcal);
        }

        // Header-Dump wenn gewünscht
        if ($dumpHeaders) {
            $this->newLine();
            $this->line('   <fg=yellow>── MIME-Headers ──</>');
            $toString = $msg->toString();
            // Nur Header (bis zur ersten Leerzeile)
            $headerSection = substr($toString, 0, strpos($toString, "\r\n\r\n") ?: 2000);
            foreach (explode("\r\n", $headerSection) as $line) {
                $this->line("   " . $line);
            }
        }
    }

    /**
     * Spezial-Validierung: Meeting-Einladung ICS-Struktur prüfen.
     */
    private function validateMeetingIcal(\Illuminate\Mail\Mailable $mailable, bool $dumpIcal): void
    {
        // buildIcal() ist private – via Reflection aufrufen
        try {
            $ref = new \ReflectionMethod($mailable, 'buildIcal');
            $ref->setAccessible(true);
            $ical = $ref->invoke($mailable);
        } catch (\ReflectionException $e) {
            $this->warn('   ⚠ ICS konnte nicht extrahiert werden (buildIcal nicht gefunden).');
            return;
        }

        $this->newLine();
        $this->line('   <fg=cyan>── ICS-Validierung ──</>');

        // RFC 5545 Line Unfolding: Zeilenumbruch + Leerzeichen entfernen
        $icalUnfolded = preg_replace('/\r?\n[ \t]/', '', $ical);

        // Pflichtfelder prüfen
        $checks = [
            ['METHOD:REQUEST',  'VCALENDAR METHOD:REQUEST vorhanden',  'Fehlt! Mailserver erkennen die ICS nicht als Einladung.'],
            ['BEGIN:VEVENT',    'VEVENT-Block vorhanden',              'Fehlt! Kein Kalender-Event definiert.'],
            ['ORGANIZER',       'ORGANIZER-Property vorhanden',        'Fehlt! Kein Organisator definiert.'],
            ['ATTENDEE',        'ATTENDEE-Property vorhanden',         'Fehlt! Kein Teilnehmer definiert.'],
            ['PARTSTAT=',       'ATTENDEE hat PARTSTAT-Parameter',     'Fehlt! Ohne PARTSTAT ignorieren manche Clients den Teilnehmer.'],
            ['RSVP=',           'ATTENDEE hat RSVP-Parameter',         'Fehlt! Empfänger wird nicht zur Antwort aufgefordert.'],
            ['ROLE=REQ-PARTICIPANT', 'ATTENDEE hat ROLE-Parameter',    'Fehlt! Rolle des Teilnehmers nicht definiert.'],
            ['UID:',            'UID vorhanden',                       'Fehlt! Event-Identifikator fehlt.'],
            ['DTSTAMP:',        'DTSTAMP vorhanden',                   'Fehlt! Zeitstempel fehlt.'],
            ['DTSTART',         'DTSTART vorhanden',                   'Fehlt! Startzeit fehlt.'],
            ['DTEND',           'DTEND vorhanden',                     'Fehlt! Endzeit fehlt.'],
            ['SUMMARY',         'SUMMARY vorhanden',                   'Fehlt! Betreff/Titel fehlt.'],
        ];

        $icalErrors = 0;
        foreach ($checks as [$needle, $okMsg, $failMsg]) {
            if (str_contains($icalUnfolded, $needle)) {
                $this->line("   <fg=green>✓</> {$okMsg}");
            } else {
                $this->line("   <fg=red>✗</> {$okMsg} – {$failMsg}");
                $icalErrors++;
            }
        }

        // UID-Domain prüfen (kein .local)
        if (preg_match('/UID:.*@(.+)/', $icalUnfolded, $m)) {
            $domain = trim($m[1]);
            if (str_ends_with($domain, '.local')) {
                $this->line("   <fg=red>✗</> UID-Domain '{$domain}' endet auf .local – kann Spamfilter auslösen!");
                $icalErrors++;
            } else {
                $this->line("   <fg=green>✓</> UID-Domain: {$domain}");
            }
        }

        if ($icalErrors === 0) {
            $this->line("   <fg=green>✓ Alle ICS-Checks bestanden!</>");
        } else {
            $this->warn("   ⚠ {$icalErrors} ICS-Problem(e) gefunden – kann Mail-Zustellung verhindern!");
        }

        // ICS-Inhalt ausgeben wenn gewünscht
        if ($dumpIcal) {
            $this->newLine();
            $this->line('   <fg=yellow>── ICS-Inhalt ──</>');
            foreach (explode("\n", $ical) as $line) {
                $this->line("   " . rtrim($line));
            }
        }
    }

    /**
     * Sendet die Mail und prüft über Events, ob sie rausgegangen ist.
     */
    private function sendAndVerify(\Illuminate\Mail\Mailable $mailable, string $email, string $key): void
    {
        $sending = false;
        $sent    = false;
        $error   = null;

        // Events mithören
        Event::listen(MessageSending::class, function () use (&$sending) {
            $sending = true;
        });
        Event::listen(MessageSent::class, function () use (&$sent) {
            $sent = true;
        });

        $startTime = microtime(true);

        try {
            Mail::to($email)->send($mailable);
        } catch (\Throwable $e) {
            $error = $e;
        }

        $duration = round((microtime(true) - $startTime) * 1000);

        if ($error) {
            $this->error("   ❌ SMTP-Fehler: " . $error->getMessage());
            Log::error("mail:diagnose SMTP-Fehler [{$key}]", [
                'email' => $email,
                'error' => $error->getMessage(),
            ]);
            throw $error;
        }

        $statusParts = [];
        $statusParts[] = $sending ? '<fg=green>MessageSending ✓</>' : '<fg=red>MessageSending ✗</>';
        $statusParts[] = $sent ? '<fg=green>MessageSent ✓</>' : '<fg=yellow>MessageSent ?</>';
        $statusParts[] = "{$duration}ms";

        $this->line("   ✉  Versand: " . implode(' → ', $statusParts));

        if (! $sending) {
            $this->warn('   ⚠ MessageSending-Event wurde nicht ausgelöst – eventuell blockiert ein Listener den Versand.');
        }

        if ($sending && ! $sent) {
            $this->warn('   ⚠ MessageSent-Event wurde nicht ausgelöst – Mail wurde möglicherweise nicht zugestellt.');
            $this->warn('     Prüfe: SMTP-Verbindung, Firewall, Queue-Konfiguration.');
        }

        if ($sent) {
            $this->line("   <fg=green>✓ Mail erfolgreich versendet an {$email}</>");
        }
    }

    /**
     * Gibt die aktuelle Mail-Konfiguration aus.
     */
    private function printMailConfig(): void
    {
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🔧 Mail-Konfiguration');
        $this->line('   Mailer:     ' . config('mail.default'));
        $this->line('   Host:       ' . config('mail.mailers.smtp.host', '-'));
        $this->line('   Port:       ' . config('mail.mailers.smtp.port', '-'));
        $this->line('   Encryption: ' . (config('mail.mailers.smtp.encryption') ?: 'keine'));
        $this->line('   Username:   ' . (config('mail.mailers.smtp.username') ? '***' . substr(config('mail.mailers.smtp.username'), -4) : '-'));
        $this->line('   From:       ' . config('mail.from.address') . ' (' . config('mail.from.name') . ')');
        $this->line('   App-URL:    ' . config('app.url'));
        $this->line('   Queue:      ' . config('queue.default'));
    }

    /**
     * Listet alle verfügbaren Mail-Typen auf.
     */
    private function listMailTypes(): int
    {
        $this->info('Verfügbare Mail-Typen für mail:diagnose');
        $this->newLine();

        $rows = [];
        foreach ($this->mailDefinitions() as $key => $def) {
            $rows[] = [
                $key,
                $def['label'],
                $def['hasAttachment'] ? '📎 Ja' : 'Nein',
                $def['description'],
            ];
        }

        $this->table(['Typ', 'Mail-Klasse', 'Anhang', 'Beschreibung'], $rows);

        $this->newLine();
        $this->line('Beispiele:');
        $this->line('  <fg=cyan>php artisan mail:diagnose user@example.com</>                    Alle Mails senden');
        $this->line('  <fg=cyan>php artisan mail:diagnose user@example.com --type=meeting</>     Nur Meeting-Einladung');
        $this->line('  <fg=cyan>php artisan mail:diagnose user@example.com --dry-run</>          Nur rendern, nicht senden');
        $this->line('  <fg=cyan>php artisan mail:diagnose user@example.com --type=meeting --dump-ical</>  ICS-Inhalt anzeigen');
        $this->line('  <fg=cyan>php artisan mail:diagnose user@example.com --dump-headers</>     MIME-Header anzeigen');

        return Command::SUCCESS;
    }

    // ─── Test-Daten Factories ────────────────────────────────────────────

    private function buildTestTasks(): \Illuminate\Support\Collection
    {
        return collect([
            (object) [
                'id' => 1,
                'task' => 'Dringende Aufgabe: Quartalsbericht fertigstellen',
                'date' => Carbon::now()->subDays(2),
                'theme' => (object) ['name' => 'Berichtswesen'],
                'completed' => false,
            ],
            (object) [
                'id' => 2,
                'task' => 'Heute: Team-Meeting vorbereiten',
                'date' => Carbon::now(),
                'theme' => (object) ['name' => 'Meetings'],
                'completed' => false,
            ],
            (object) [
                'id' => 3,
                'task' => 'Projekt-Dokumentation aktualisieren',
                'date' => Carbon::now()->addDays(2),
                'theme' => (object) ['name' => 'Dokumentation'],
                'completed' => false,
            ],
        ]);
    }

    private function buildTestSteps(): array
    {
        return [
            [
                'procedureName' => 'Urlaubsantrag Mustermann',
                'procedureId'   => 1,
                'stepName'      => 'Genehmigung durch Vorgesetzten',
                'stepId'        => 101,
                'endDate'       => now()->format('d.m.Y'),
            ],
            [
                'procedureName' => 'Wartung Server-Raum',
                'procedureId'   => 2,
                'stepName'      => 'Dokumentation erstellen',
                'stepId'        => 102,
                'endDate'       => now()->addDays(2)->format('d.m.Y'),
            ],
            [
                'procedureName' => 'Bestellung IT-Equipment',
                'procedureId'   => 3,
                'stepName'      => 'Budget-Freigabe einholen',
                'stepId'        => 103,
                'endDate'       => now()->subDays(1)->format('d.m.Y'),
            ],
        ];
    }

    private function buildTestAbsences(): \Illuminate\Support\Collection
    {
        return collect([
            (object) [
                'user'   => (object) ['name' => 'Max Muster'],
                'start'  => now(),
                'end'    => now()->addDays(2),
                'reason' => 'Krankheit',
            ],
            (object) [
                'user'   => (object) ['name' => 'Erika Beispiel'],
                'start'  => now(),
                'end'    => now(),
                'reason' => 'Fortbildung',
            ],
        ]);
    }

    private function buildTestThemes(): \Illuminate\Support\Collection
    {
        return collect([
            (object) ['theme' => 'Digitalisierung der Verwaltung', 'priority' => 3],
            (object) ['theme' => 'Elternarbeit verbessern', 'priority' => 2],
            (object) ['theme' => 'Evaluation Schuljahresplanung', 'priority' => 1],
        ]);
    }

    private function buildTestPosts(): \Illuminate\Support\Collection
    {
        return collect([
            (object) [
                'header'     => 'Neue Pausenregelung ab Mai',
                'content'    => 'Ab dem 01.05. gelten neue Pausenzeiten...',
                'created_at' => now()->subHours(2),
                'user'       => (object) ['name' => 'Schulleitung'],
            ],
            (object) [
                'header'     => 'Fortbildung Erste Hilfe',
                'content'    => 'Am 20.05. findet eine verpflichtende Erste-Hilfe-Fortbildung statt.',
                'created_at' => now()->subHours(5),
                'user'       => (object) ['name' => 'Verwaltung'],
            ],
        ]);
    }

    /**
     * Erstellt eine Meeting-Einladungsmail mit echtem Meeting aus der DB
     * oder einem Fake-Objekt, wenn kein Meeting existiert.
     */
    private function buildMeetingMail(string $email): MeetingInvitationMail
    {
        // Versuche echtes Meeting zu laden
        $meeting = Meeting::with('themes')->latest('id')->first();

        if ($meeting) {
            $group = $meeting->group;
            $this->line("   ℹ  Verwende echtes Meeting: \"{$meeting->title}\" (ID {$meeting->id})");
        } else {
            // Fake-Objekte erstellen (ohne DB-Speicherung)
            $this->line("   ℹ  Kein Meeting in DB – verwende Testdaten.");
            $group = new Group();
            $group->name = 'Test-Gruppe';
            $group->meeting_url = 'https://meet.example.com/test';

            $meeting = new Meeting();
            $meeting->id = 0;
            $meeting->title = 'Test-Meeting (Diagnose)';
            $meeting->date = now()->addDays(7);
            $meeting->start_time = '14:00';
            $meeting->end_time = '15:30';
            $meeting->setRelation('themes', collect());
            $meeting->setRelation('group', $group);
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = new User();
            $user->name = 'Test-Empfänger';
            $user->email = $email;
        }

        return new MeetingInvitationMail($meeting, $group, $user, 'Dies ist eine Test-Nachricht vom Diagnose-Tool.', 'Diagnose-Tool');
    }

    /**
     * Extrahiert registrierte Symfony-Message-Callbacks aus dem Mailable.
     */
    private function extractCallbacks(\Illuminate\Mail\Mailable $mailable): array
    {
        try {
            $ref = new \ReflectionProperty($mailable, 'callbacks');
            $ref->setAccessible(true);
            return $ref->getValue($mailable) ?: [];
        } catch (\ReflectionException) {
            return [];
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}




