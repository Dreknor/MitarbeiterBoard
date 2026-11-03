<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Überarbeitete Anleitung „Benachrichtigungen“ für Mitarbeitende.
 *
 * Legt eine neue Version der bestehenden Wiki-Seite an (wie beim Bearbeiten im Wiki),
 * damit die bisherige Fassung im Versionsverlauf erhalten bleibt. Gibt es die Seite
 * noch nicht, wird sie neu angelegt.
 */
return new class extends Migration
{
    private string $title = 'Benachrichtigungen';

    public function up(): void
    {
        if (!Schema::hasTable('wiki_sites')) {
            return;
        }

        $aktuell = DB::table('wiki_sites')
            ->where('title', $this->title)
            ->whereNull('deleted_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        // Idempotent: nicht doppelt anlegen
        if ($aktuell && trim((string) $aktuell->text) === trim($this->html())) {
            return;
        }

        DB::table('wiki_sites')->insert([
            'author_id'        => 1,
            'title'            => $this->title,
            'previous_version' => $aktuell ? ($aktuell->previous_version ?: $aktuell->id) : null,
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

        DB::table('wiki_sites')
            ->where('title', $this->title)
            ->where('text', $this->html())
            ->delete();
    }

    private function html(): string
    {
        return <<<'HTML'
<h2>Benachrichtigungen einstellen – Anleitung</h2>
<p>Das MitarbeiterBoard informiert Sie über alles, was Sie betrifft: neue Aufgaben, geänderte Vertretungen, Antworten auf Ihre Tickets und vieles mehr. Sie entscheiden selbst, <strong>worüber</strong> und <strong>auf welchem Weg</strong> Sie informiert werden: in der Glocke, als Push-Nachricht auf Handy oder PC oder per E-Mail.</p>
<p><strong>Kurz gesagt:</strong> In der Glocke landet immer alles. Push und E-Mail stellen Sie pro Bereich ein. Die tägliche Übersicht „Dein Tag“ ist ein Zusatzangebot, das Sie selbst einschalten.</p>

<h3>1. So kommen Sie zu den Einstellungen</h3>
<ul>
  <li>Klicken Sie oben rechts auf die <strong>Glocke</strong> und dann auf <strong>Einstellungen</strong> (Zahnrad), <em>oder</em></li>
  <li>klicken Sie oben rechts auf Ihren Namen und wählen Sie <strong>Benachrichtigungen</strong>.</li>
</ul>
<p>Die Seite hat drei Teile: <strong>Push auf diesem Gerät</strong>, <strong>Worüber möchten Sie informiert werden?</strong> und <strong>Tagesübersicht „Dein Tag“</strong>. Änderungen an den beiden unteren Teilen werden erst mit <strong>„Einstellungen speichern“</strong> ganz unten übernommen.</p>

<h3>2. Die Glocke</h3>
<p>Die rote Zahl an der Glocke zeigt, wie viele ungelesene Benachrichtigungen Sie haben.</p>
<ul>
  <li><strong>Klick auf die Glocke</strong> zeigt die letzten 10 Benachrichtigungen.</li>
  <li><strong>Klick auf eine Benachrichtigung</strong> öffnet die passende Seite (z. B. das Thema oder das Ticket) und markiert sie als gelesen.</li>
  <li><strong>„Alle als gelesen markieren“</strong> setzt den Zähler zurück.</li>
  <li><strong>„Alle anzeigen“</strong> öffnet den vollständigen Verlauf. Dort können Sie nach Kategorie und nach „ungelesen“ filtern.</li>
  <li><strong>„Mein Tag“</strong> zeigt Ihre Tagesübersicht im Browser (siehe Abschnitt 5).</li>
</ul>
<p>In der Glocke erscheint <em>immer</em> alles, auch wenn Sie Push und E-Mail für einen Bereich ausgeschaltet haben. Gelesene Einträge werden nach 180 Tagen automatisch gelöscht.</p>

<h3>3. Worüber möchten Sie informiert werden?</h3>
<p>Für jede Kategorie gibt es zwei Einstellungen:</p>
<ul>
  <li><strong>Push</strong> (Schalter): eine kurze Nachricht auf Ihrem Handy, Tablet oder PC, sofort. Funktioniert nur auf Geräten, auf denen Sie Push aktiviert haben (Abschnitt 4).</li>
  <li><strong>Mail</strong> (drei Möglichkeiten):
    <ul>
      <li><em>sofort</em>: eine E-Mail pro Ereignis,</li>
      <li><em>Zusammenfassung</em>: eine gesammelte E-Mail am Nachmittag (werktags um 16:00 Uhr) mit allem, was Sie bis dahin noch <strong>nicht gelesen</strong> haben. Was Sie vorher schon in der Glocke angeklickt haben, ist nicht mehr dabei. Kommt nichts zusammen, gibt es auch keine Mail.</li>
      <li><em>aus</em>: keine E-Mail, nur Glocke (und ggf. Push).</li>
    </ul>
  </li>
</ul>
<p>Sie sehen nur die Kategorien, die zu Ihren Aufgaben und Rechten passen. Haben Sie noch nie etwas gespeichert, gelten die Standardwerte aus der Tabelle.</p>

<table>
  <thead><tr><th>Kategorie</th><th>Worum geht es?</th><th>Standard</th></tr></thead>
  <tbody>
    <tr><td>Vertretungen</td><td>Eine Vertretung für Sie wurde neu angelegt, geändert oder gestrichen.</td><td>Push an, Mail sofort</td></tr>
    <tr><td>Aufgaben</td><td>Ihnen wurde eine persönliche oder gemeinsame Aufgabe zugewiesen. Gilt auch für die wöchentliche Erinnerung an offene Aufgaben (montags).</td><td>Push an, Mail sofort</td></tr>
    <tr><td>Themen &amp; Protokolle</td><td>Neues Thema in einer abonnierten Gruppe, ein Thema wurde Ihnen zugewiesen, neues Protokoll zu Ihrem Thema.</td><td>Push aus, Mail sofort</td></tr>
    <tr><td>Meetings</td><td>Einladungen zu Meetings und Themen-Einladungen Ihrer Gruppen.</td><td>Push aus, Mail sofort</td></tr>
    <tr><td>Tickets</td><td>Neues Ticket, Kommentar, Zuweisung, Ticket geschlossen oder wieder geöffnet.</td><td>Push an, Mail sofort</td></tr>
    <tr><td>Prozesse</td><td>Ein Prozessschritt ist für Sie fällig oder es gibt einen neuen Kommentar.</td><td>Push aus, Mail sofort</td></tr>
    <tr><td>Terminlisten</td><td>Ein reservierter Termin wurde abgesagt.</td><td>Push aus, Mail sofort</td></tr>
    <tr><td>Abwesenheiten</td><td>Sofortmeldung, sobald eine neue Abwesenheit im Kollegium eingetragen wird. Nur wenn Sie Push oder Mail einschalten, sonst erscheint dazu auch nichts in der Glocke.</td><td>aus</td></tr>
    <tr><td>Urlaub</td><td>Urlaubsanträge, Genehmigungen und Ablehnungen.</td><td>Push an, Mail sofort</td></tr>
    <tr><td>Dienstplan</td><td>Veröffentlichte und geänderte Dienstpläne.</td><td>Push an, Mail sofort</td></tr>
    <tr><td>Zeiterfassung</td><td>Arbeitszeitnachweise und Hinweise zur Zeiterfassung.</td><td>Push an, Mail sofort</td></tr>
    <tr><td>Personalverwaltung</td><td>Ablaufende Dokumente, Qualifikationen und Wiedervorlagen (nur für die Personalverwaltung).</td><td>Push aus, Mail sofort</td></tr>
    <tr><td>Nachrichten</td><td>Neue Nachrichten im Board. Dafür gibt es eine Sammelmail am Abend (20:00 Uhr). „sofort“ und „Zusammenfassung“ bedeuten hier beide: Sammelmail am Abend. „aus“ schaltet sie ab.</td><td>Mail an</td></tr>
    <tr><td>System</td><td>Technische Hinweise, z. B. Fehler bei Synchronisation oder Mailversand (betrifft vor allem Administratoren).</td><td>Push an, Mail sofort</td></tr>
  </tbody>
</table>

<p><strong>Unsere Empfehlung:</strong> Push für Vertretungen und Aufgaben einschalten, die meisten anderen Kategorien auf „Zusammenfassung“ stellen. So bleibt Ihr Postfach übersichtlich und Sie verpassen trotzdem nichts Dringendes.</p>

<h3>4. Push auf Handy oder PC aktivieren</h3>
<p>Push muss auf <strong>jedem Gerät einmal</strong> aktiviert werden. Die Schalter in der Tabelle legen nur fest, <em>wofür</em> Push kommt.</p>
<ol>
  <li>Öffnen Sie die Benachrichtigungs-Einstellungen auf dem Gerät, auf dem Sie Push erhalten möchten.</li>
  <li>Klicken Sie im Bereich <strong>„Push-Benachrichtigungen auf diesem Gerät“</strong> auf <strong>„Push auf diesem Gerät aktivieren“</strong>.</li>
  <li>Bestätigen Sie die Nachfrage des Browsers mit <strong>„Zulassen“</strong>.</li>
  <li>Mit <strong>„Test senden“</strong> prüfen Sie, ob alles funktioniert.</li>
</ol>
<p><strong>Android (Chrome, Firefox) und PC (Chrome, Edge, Firefox):</strong> funktioniert direkt im Browser.</p>
<p><strong>iPhone / iPad (ab iOS 16.4):</strong> Push funktioniert nur, wenn das MitarbeiterBoard als App auf dem Home-Bildschirm liegt:</p>
<ol>
  <li>MitarbeiterBoard in <strong>Safari</strong> öffnen.</li>
  <li>Auf <strong>Teilen</strong> (Quadrat mit Pfeil) tippen und <strong>„Zum Home-Bildschirm“</strong> wählen.</li>
  <li>Die neue App vom Home-Bildschirm aus öffnen, anmelden und dort Push aktivieren.</li>
</ol>
<p><strong>Es kommt nichts an?</strong></p>
<ul>
  <li>Steht dort „Benachrichtigungen sind im Browser blockiert“, haben Sie die Nachfrage früher abgelehnt. Erlauben Sie Benachrichtigungen in den Website-Einstellungen des Browsers (Schloss-Symbol links neben der Adresse) und aktivieren Sie Push danach erneut.</li>
  <li>Steht dort „Dieser Browser unterstützt keine Push-Benachrichtigungen“, nutzen Sie einen anderen Browser bzw. auf dem iPhone den Weg über den Home-Bildschirm.</li>
  <li>Prüfen Sie, ob Push für die gewünschte Kategorie eingeschaltet und gespeichert ist.</li>
  <li>Prüfen Sie die Einstellungen Ihres Betriebssystems (z. B. „Nicht stören“ oder Fokus-Modus).</li>
</ul>
<p>Über <strong>„Deaktivieren“</strong> schalten Sie Push auf dem jeweiligen Gerät wieder ab. Auf Ihren anderen Geräten bleibt Push aktiv.</p>

<h3>5. Tagesübersicht „Dein Tag“</h3>
<p>Die Tagesübersicht ist <strong>standardmäßig ausgeschaltet</strong>. Wer möchte, bekommt damit an jedem Arbeitstag eine kurze Übersicht über alles, was ansteht.</p>
<p><strong>Einschalten:</strong></p>
<ol>
  <li>In den Einstellungen zum Bereich <strong>„Tagesübersicht ‚Dein Tag‘“</strong> gehen.</li>
  <li>Bei <strong>„Zustellen per“</strong> <strong>E-Mail</strong> und/oder <strong>Push (Kurzfassung)</strong> einschalten. Push enthält nur eine Kurzfassung, z. B. „2 Meine Vertretungen · 1 Meetings“; ein Tipp darauf öffnet die vollständige Übersicht.</li>
  <li>Bei <strong>„Wann?“</strong> wählen:
    <ul>
      <li><em>am Morgen</em> (zwischen 5:00 und 10:00 Uhr) für den heutigen Tag, oder</li>
      <li><em>am Vorabend</em> (zwischen 16:00 und 22:00 Uhr) für den nächsten Arbeitstag. Am Freitagabend bekommen Sie also schon die Übersicht für Montag.</li>
    </ul>
    Die Uhrzeit wählen Sie im Viertelstunden-Takt.
  </li>
  <li>Bei <strong>„Was soll enthalten sein?“</strong> die gewünschten Bereiche anhaken.</li>
  <li>Bei <strong>„Termine aus diesen Kalendern“</strong> die Kalender anhaken, deren Termine erscheinen sollen. Mit dem Schalter darunter kommen zusätzlich alle Termine dazu, zu denen Sie eingeladen sind.</li>
  <li><strong>„Einstellungen speichern“</strong> klicken.</li>
</ol>
<p><strong>Mögliche Bereiche</strong> (je nach Ihren Aufgaben sehen Sie nicht alle):</p>
<ul>
  <li>Meine Vertretungen, Dienstplan, Meetings, Termine (aus den gewählten Kalendern),</li>
  <li>Fällige Aufgaben und Prozessschritte (auch überfällige),</li>
  <li>Meine Tickets und Offene Urlaubsanträge (für Genehmigende),</li>
  <li>Abwesenheiten im Kollegium: dieser Bereich ist anfangs <em>nicht</em> angehakt und muss ausdrücklich gewählt werden.</li>
</ul>
<p><strong>Wann kommt keine Übersicht?</strong></p>
<ul>
  <li>An Wochenenden und Feiertagen.</li>
  <li>Wenn an dem Tag nichts ansteht.</li>
  <li>Wenn Sie an dem Tag selbst abwesend sind oder Urlaub haben (außer Sie haben unter <strong>Eigene Daten</strong> „E-Mail bei Abwesenheit oder Urlaub“ auf „ja“ gestellt).</li>
</ul>
<p><strong>Vorschau:</strong> Mit dem Knopf <strong>„Vorschau“</strong> oder über die Glocke → <strong>„Mein Tag“</strong> sehen Sie die Übersicht jederzeit im Browser, auch wenn E-Mail und Push ausgeschaltet sind. Mit den Pfeilen blättern Sie zu anderen Tagen.</p>
<p>Wenn Sie die Übersicht am Vorabend erhalten: Vertretungen können sich bis zum Morgen noch ändern. Änderungen erhalten Sie zusätzlich als Benachrichtigung in der Kategorie „Vertretungen“.</p>
<p><strong>Ausschalten:</strong> Bei „Zustellen per“ E-Mail und Push ausschalten und speichern.</p>

<h3>6. Abwesenheiten im Kollegium (früheres Abwesenheits-Abo)</h3>
<p>Die früheren Glocken-Schalter „täglich“ und „sofort“ auf der Abwesenheiten-Karte gibt es nicht mehr. Der Glocken-Knopf dort führt jetzt direkt zu den Benachrichtigungs-Einstellungen. Wer früher ein Abo hatte, wurde automatisch übernommen:</p>
<ul>
  <li><strong>„sofort“</strong> ist jetzt die Kategorie <strong>Abwesenheiten</strong> (Mail „sofort“). Dort können Sie zusätzlich Push einschalten.</li>
  <li><strong>„täglich“</strong> ist jetzt der Bereich <strong>Abwesenheiten im Kollegium</strong> in der Tagesübersicht. Für Sie wurde die Tagesübersicht per E-Mail mit der bisherigen Uhrzeit 7:30 Uhr eingeschaltet; Uhrzeit und Inhalt können Sie anpassen.</li>
</ul>

<h3>7. Häufige Fragen</h3>
<p><strong>Bekomme ich gar keine E-Mails mehr, wenn ich überall „aus“ wähle?</strong><br>
Fast. Persönliche Meeting-Einladungen, die von den Organisierenden bewusst verschickt werden, persönlich versandte Dienstpläne und der monatliche Arbeitszeitnachweis kommen weiterhin per E-Mail.</p>
<p><strong>Bekomme ich Benachrichtigungen, während ich krank oder im Urlaub bin?</strong><br>
Die Glocke wird weiter gefüllt, damit Sie danach nichts verpassen. E-Mails zu Aufgaben, Prozessen und Abwesenheiten werden in dieser Zeit nicht verschickt, und auch die Tagesübersicht entfällt. Wer das trotzdem möchte, stellt unter <strong>Eigene Daten</strong> „E-Mail bei Abwesenheit oder Urlaub“ auf „ja“.</p>
<p><strong>Auf wie vielen Geräten kann ich Push nutzen?</strong><br>
Auf beliebig vielen. Aktivieren Sie Push einfach auf jedem Gerät einmal.</p>
<p><strong>Ich habe Push eingeschaltet, bekomme aber nichts.</strong><br>
Push für eine Kategorie wirkt nur, wenn Push auch auf mindestens einem Gerät aktiviert ist. Prüfen Sie mit „Test senden“, ob das Gerät erreichbar ist (siehe Abschnitt 4).</p>
<p><strong>Ich bekomme die Tagesübersicht nicht.</strong><br>
Prüfen Sie, ob bei „Zustellen per“ E-Mail oder Push eingeschaltet ist und ob mindestens ein Bereich angehakt ist. Steht an dem Tag nichts an, wird bewusst keine Übersicht verschickt. Mit „Vorschau“ sehen Sie, was aktuell enthalten wäre.</p>
<p><strong>Die Zusammenfassung um 16 Uhr war leer bzw. kam nicht.</strong><br>
Sie enthält nur Benachrichtigungen, die Sie bis dahin noch nicht gelesen haben, und kommt nur, wenn mindestens eine Kategorie auf „Zusammenfassung“ steht.</p>
HTML;
    }
};
