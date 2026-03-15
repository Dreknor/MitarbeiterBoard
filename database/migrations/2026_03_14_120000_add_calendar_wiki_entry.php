<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $title = 'Kalender';

    public function up(): void
    {
        if (!Schema::hasTable('wiki_sites')) {
            return;
        }

        // Idempotent: nicht doppelt anlegen
        if (DB::table('wiki_sites')->where('title', $this->title)->exists()) {
            return;
        }

        DB::table('wiki_sites')->insert([
            'author_id'        => 1,
            'title'            => $this->title,
            'previous_version' => null,
            'text'             => $this->html(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        if (!Schema::hasTable('wiki_sites')) {
            return;
        }

        DB::table('wiki_sites')->where('title', $this->title)->delete();
    }

    private function html(): string
    {
        return <<<'HTML'
<h2>Kalender-Modul</h2>
<p>Das Kalender-Modul zeigt Termine aus Open-Xchange (OX) im MitarbeiterBoard an. Termine werden automatisch alle 15 Minuten synchronisiert.</p>

<h3>Kalender anzeigen</h3>
<ol>
  <li>Klicken Sie auf <strong>Kalender</strong> in der Navigation.</li>
  <li>Wählen Sie die gewünschte Ansicht: <strong>Monat</strong>, <strong>Woche</strong> oder <strong>Tag</strong>.</li>
  <li>Nutzen Sie die Filter-Sidebar links, um einzelne Kalender ein- oder auszublenden.</li>
</ol>

<h3>Termin erstellen</h3>
<ol>
  <li>Klicken Sie auf <strong>+ Neuer Termin</strong>.</li>
  <li>Wählen Sie den Ziel-Kalender.</li>
  <li>Geben Sie Titel, Datum/Uhrzeit und optional Ort und Beschreibung ein.</li>
  <li>Bei wiederkehrenden Terminen: Wählen Sie ein Wiederholungsmuster.</li>
  <li>Klicken Sie auf <strong>Speichern</strong>.</li>
</ol>
<p><strong>Hinweis:</strong> Der Termin wird sofort in OX erstellt. Beim nächsten Sync erscheint er auch für andere Nutzer.</p>

<h3>Termin bearbeiten / löschen</h3>
<ol>
  <li>Klicken Sie auf einen Termin im Kalender.</li>
  <li>Im Detail-Fenster klicken Sie auf <strong>Bearbeiten</strong> oder <strong>Löschen</strong>.</li>
  <li>Beim Bearbeiten werden Ihre Änderungen nach OX zurückgeschrieben.</li>
</ol>
<p><strong>Achtung:</strong> Wenn der Termin zwischenzeitlich in OX geändert wurde, erhalten Sie eine Warnung.</p>

<h3>iCal-Feed (Kalender-Abo)</h3>
<p>Sie können Ihre sichtbaren Termine als iCal-Feed in Outlook, Google Calendar o.&nbsp;ä. abonnieren:</p>
<ol>
  <li>Gehen Sie zu <strong>Kalender</strong> → <strong>Einstellungen</strong> (⚙️).</li>
  <li>Klicken Sie auf <strong>Feed-Token generieren</strong>.</li>
  <li>Kopieren Sie die angezeigte URL.</li>
  <li>Fügen Sie diese URL als Kalender-Abo in Ihrem E-Mail-Programm hinzu.</li>
</ol>

<h3>Für Administratoren</h3>

<h4>Kalender verwalten</h4>
<ul>
  <li><strong>Kalender → Verwaltung</strong> öffnet die Admin-Seite.</li>
  <li>Hier können Sie neue OX-Kalender hinzufügen, Farben ändern und Gruppen-Zuordnungen pflegen.</li>
</ul>

<h4>Gruppen-Berechtigungen</h4>
<ul>
  <li>Jeder Kalender kann Gruppen zugeordnet werden.</li>
  <li>Pro Gruppe kann festgelegt werden, ob nur Lesen oder auch Schreiben erlaubt ist.</li>
  <li>Kalender ohne Gruppen-Zuordnung sind für alle Nutzer mit <code>view calendar</code>-Berechtigung sichtbar.</li>
</ul>

<h4>Sync-Status &amp; Fehler</h4>
<ul>
  <li>Unter <strong>Kalender → Admin → Sync-Logs</strong> sehen Sie den Synchronisationsverlauf.</li>
  <li>Bei 3+ aufeinanderfolgenden Fehlern werden Admins automatisch per E-Mail benachrichtigt.</li>
  <li>Der manuelle Sync kann über den Button <strong>„Jetzt synchronisieren"</strong> ausgelöst werden.</li>
  <li>Einstellungen (Sync-Intervall, Aufbewahrungsfrist etc.) sind über <strong>Einstellungen → Kalender</strong> konfigurierbar.</li>
</ul>
HTML;
    }
};

