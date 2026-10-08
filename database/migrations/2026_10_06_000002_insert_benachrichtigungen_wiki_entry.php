<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $title = 'Benachrichtigungen';

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
<h2>Benachrichtigungen im MitarbeiterBoard</h2>
<p>Das MitarbeiterBoard informiert Sie über alles, was Sie betrifft, zum Beispiel neue Aufgaben, geänderte Vertretungen oder Antworten auf Ihre Tickets. Sie entscheiden selbst, <strong>worüber</strong> und <strong>wie</strong> Sie informiert werden: in der Glocke, als Push-Nachricht auf dem Handy oder per E-Mail.</p>

<h3>1. Die Glocke</h3>
<p>Oben rechts neben Ihrem Namen sehen Sie eine <strong>Glocke</strong>. Die rote Zahl zeigt, wie viele ungelesene Benachrichtigungen Sie haben.</p>
<ul>
  <li><strong>Klick auf die Glocke</strong> zeigt die letzten 10 Benachrichtigungen.</li>
  <li><strong>Klick auf eine Benachrichtigung</strong> öffnet die passende Seite (z. B. das Thema oder das Ticket) und markiert sie als gelesen.</li>
  <li><strong>„Alle als gelesen markieren“</strong> setzt den Zähler zurück.</li>
  <li><strong>„Alle anzeigen“</strong> öffnet den vollständigen Verlauf. Dort können Sie nach Kategorie und nach „ungelesen“ filtern.</li>
</ul>
<p>In der Glocke erscheint <em>immer</em> alles, auch wenn Sie Push und E-Mail für eine Kategorie ausgeschaltet haben. Gelesene Einträge werden nach 180 Tagen automatisch gelöscht.</p>

<h3>2. Kategorien</h3>
<table>
  <thead><tr><th>Kategorie</th><th>Wann bekommen Sie eine Benachrichtigung?</th></tr></thead>
  <tbody>
    <tr><td>Vertretungen</td><td>Eine Vertretung für Sie wurde neu angelegt, geändert oder gestrichen (nur Termine ab heute).</td></tr>
    <tr><td>Aufgaben</td><td>Ihnen wurde eine persönliche oder gemeinsame Aufgabe zugewiesen.</td></tr>
    <tr><td>Themen &amp; Protokolle</td><td>Neues Thema in einer abonnierten Gruppe, ein Thema wurde Ihnen zugewiesen, neues Protokoll zu Ihrem Thema.</td></tr>
    <tr><td>Meetings</td><td>Sie wurden zu einem Meeting eingeladen.</td></tr>
    <tr><td>Tickets</td><td>Neues Ticket, Kommentar, Zuweisung, Ticket geschlossen oder wieder geöffnet.</td></tr>
    <tr><td>Prozesse</td><td>Ein Prozessschritt ist für Sie fällig oder es gibt einen neuen Kommentar.</td></tr>
    <tr><td>Terminlisten</td><td>Ein reservierter Termin wurde abgesagt.</td></tr>
    <tr><td>Abwesenheiten</td><td>Eine neue Abwesenheit wurde eingetragen (nur wenn Sie Push oder Mail für diese Kategorie einschalten; standardmäßig aus).</td></tr>
    <tr><td>Urlaub, Dienstplan, Zeiterfassung</td><td>Urlaubsanträge und Entscheidungen, veröffentlichte oder geänderte Dienstpläne, Hinweise zum Arbeitszeitnachweis.</td></tr>
  </tbody>
</table>
<p>Sie sehen nur die Kategorien, die für Ihre Aufgaben relevant sind.</p>

<h3>3. Einstellungen</h3>
<p>Sie erreichen die Einstellungen über die Glocke (<strong>Einstellungen</strong>) oder über Ihr Profilmenü oben rechts (<strong>Benachrichtigungen</strong>). Für jede Kategorie gibt es zwei Schalter:</p>
<ul>
  <li><strong>Push</strong>: eine kurze Nachricht auf Ihrem Handy, Tablet oder PC, sofort.</li>
  <li><strong>Mail</strong>:
    <ul>
      <li><em>sofort</em>: eine E-Mail pro Ereignis,</li>
      <li><em>Zusammenfassung</em>: alles Ungelesene gesammelt in <strong>einer</strong> E-Mail am Nachmittag (werktags 16:00 Uhr),</li>
      <li><em>aus</em>: keine E-Mail, nur Glocke (und ggf. Push).</li>
    </ul>
  </li>
</ul>
<p><strong>Unsere Empfehlung:</strong> Push für Vertretungen und Aufgaben einschalten, alles andere auf „Zusammenfassung“ stellen. So bleibt Ihr Postfach übersichtlich und Sie verpassen trotzdem nichts Dringendes.</p>
<p>Einladungen zu Meetings kommen immer per E-Mail, weil sie von den Organisierenden bewusst verschickt werden.</p>

<h3>4. Push auf Handy oder PC aktivieren</h3>
<p>Push muss auf <strong>jedem Gerät einmal</strong> aktiviert werden.</p>
<ol>
  <li>Öffnen Sie <strong>Benachrichtigungen → Einstellungen</strong> auf dem Gerät, auf dem Sie Push erhalten möchten.</li>
  <li>Klicken Sie auf <strong>„Push auf diesem Gerät aktivieren“</strong>.</li>
  <li>Bestätigen Sie die Nachfrage des Browsers mit <strong>„Zulassen“</strong>.</li>
  <li>Mit <strong>„Test senden“</strong> prüfen Sie, ob alles funktioniert.</li>
</ol>
<p><strong>Android (Chrome, Firefox) und PC (Chrome, Edge, Firefox):</strong> funktioniert direkt im Browser.</p>
<p><strong>iPhone / iPad (ab iOS 16.4):</strong> Push funktioniert nur, wenn das MitarbeiterBoard als App auf dem Home-Bildschirm liegt:</p>
<ol>
  <li>MitarbeiterBoard in <strong>Safari</strong> öffnen.</li>
  <li>Auf <strong>Teilen</strong> (Quadrat mit Pfeil) tippen → <strong>„Zum Home-Bildschirm“</strong>.</li>
  <li>Die neue App vom Home-Bildschirm aus öffnen, anmelden und dort Push aktivieren.</li>
</ol>
<p><strong>Es kommt nichts an?</strong></p>
<ul>
  <li>Steht in den Einstellungen „Benachrichtigungen sind im Browser blockiert“, haben Sie die Nachfrage früher abgelehnt. Erlauben Sie Benachrichtigungen in den Website-Einstellungen des Browsers (Schloss-Symbol links neben der Adresse) und aktivieren Sie Push danach erneut.</li>
  <li>Prüfen Sie, ob Push für die gewünschte Kategorie eingeschaltet ist.</li>
  <li>Prüfen Sie die Benachrichtigungs-Einstellungen Ihres Betriebssystems (z. B. „Nicht stören“).</li>
</ul>
<p>Über <strong>„Deaktivieren“</strong> schalten Sie Push auf dem jeweiligen Gerät wieder ab.</p>

<h3>5. Tagesübersicht „Dein Tag“</h3>
<p>Auf Wunsch erhalten Sie an jedem Arbeitstag eine kurze Übersicht über alles, was ansteht:</p>
<ul>
  <li>Ihre Vertretungen und Ihr Dienstplan,</li>
  <li>Meetings und <strong>Termine aus den Kalendern, die Sie auswählen</strong>,</li>
  <li>fällige und überfällige Aufgaben und Prozessschritte,</li>
  <li>je nach Aufgabe: zugewiesene Tickets, offene Urlaubsanträge zur Genehmigung,</li>
  <li>auf Wunsch: <strong>Abwesenheiten im Kollegium</strong> (muss in den Einstellungen ausdrücklich angehakt werden).</li>
</ul>
<p>In den Einstellungen unter <strong>„Tagesübersicht“</strong> legen Sie fest:</p>
<ul>
  <li><strong>per E-Mail und/oder Push</strong> (Push enthält eine Kurzfassung, z. B. „2 Vertretungen · 1 Meeting“),</li>
  <li><strong>Zeitpunkt</strong>: <em>am Morgen</em> (z. B. 6:30 Uhr) für den heutigen Tag oder <em>am Vorabend</em> (z. B. 19:00 Uhr) für den nächsten Arbeitstag. Am Freitagabend bekommen Sie also die Übersicht für Montag,</li>
  <li><strong>Uhrzeit</strong> im Viertelstunden-Takt,</li>
  <li><strong>welche Bereiche</strong> enthalten sein sollen,</li>
  <li><strong>welche Kalender</strong>: Haken Sie die Kalender an, deren Termine erscheinen sollen. Zusätzlich können Sie alle Termine einschließen, zu denen Sie eingeladen sind.</li>
</ul>
<p>Die Übersicht kommt nur, wenn auch etwas ansteht. An Wochenenden, Feiertagen und an Tagen, an denen Sie selbst abwesend sind oder Urlaub haben, wird keine Übersicht verschickt. Über die Glocke → <strong>„Mein Tag“</strong> können Sie die Übersicht jederzeit im Browser ansehen und mit den Pfeilen zu anderen Tagen blättern.</p>
<p>Wenn Sie die Übersicht am Vorabend erhalten: Vertretungen können sich bis zum Morgen noch ändern. Änderungen an Ihren Vertretungen erhalten Sie zusätzlich als Benachrichtigung (Kategorie „Vertretungen“).</p>
<p><strong>Abbestellen:</strong> In den Einstellungen bei „Tagesübersicht“ E-Mail und Push ausschalten.</p>

<h3>6. Abwesenheiten (früheres Abwesenheits-Abo)</h3>
<p>Die bisherigen Glocken-Schalter „täglich“ und „sofort“ auf der Abwesenheiten-Karte gibt es nicht mehr. Sie wurden durch die Benachrichtigungen ersetzt. Wer bisher ein Abo hatte, wurde automatisch übernommen:</p>
<ul>
  <li><strong>„sofort“</strong> ist jetzt die Kategorie <strong>Abwesenheiten</strong>. Schalten Sie Push und/oder Mail ein, um bei jeder neu eingetragenen Abwesenheit informiert zu werden.</li>
  <li><strong>„täglich“</strong> ist jetzt der Bereich <strong>Abwesenheiten im Kollegium</strong> in der Tagesübersicht. Sie sehen dort jeden Morgen (oder am Vorabend), wer fehlt, zusammen mit Ihren übrigen Terminen. Die bisherige Uhrzeit 7:30 Uhr wurde übernommen und kann angepasst werden.</li>
</ul>
<p>Der Glocken-Knopf auf der Abwesenheiten-Karte führt direkt zu diesen Einstellungen.</p>

<h3>7. Häufige Fragen</h3>
<p><strong>Werden E-Mails komplett abgeschaltet, wenn ich überall „aus“ wähle?</strong><br>
Fast. Meeting-Einladungen, persönlich versandte Dienstpläne und der monatliche Arbeitszeitnachweis kommen weiterhin per E-Mail.</p>
<p><strong>Bekomme ich Benachrichtigungen, während ich krank oder im Urlaub bin?</strong><br>
Die Glocke wird weiter gefüllt, damit Sie danach nichts verpassen. E-Mails zu Aufgaben, Prozessen und Abwesenheiten werden in dieser Zeit nicht verschickt, außer Sie haben in Ihren eigenen Daten „Mails auch bei Abwesenheit“ aktiviert.</p>
<p><strong>Auf wie vielen Geräten kann ich Push nutzen?</strong><br>
Auf beliebig vielen. Aktivieren Sie Push einfach auf jedem Gerät einmal.</p>
<p><strong>Ich bekomme die Tagesübersicht nicht.</strong><br>
Prüfen Sie, ob E-Mail oder Push bei „Tagesübersicht“ eingeschaltet ist und ob mindestens ein Bereich ausgewählt ist. Steht an dem Tag nichts an, wird bewusst keine Übersicht verschickt. Mit „Vorschau“ sehen Sie, was aktuell enthalten wäre.</p>
HTML;
    }
};
