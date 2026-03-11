<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $title = 'Anleitung Wochenplansystem';

    public function up(): void
    {
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
        DB::table('wiki_sites')->where('title', $this->title)->delete();
    }

    private function html(): string
    {
        return <<<'HTML'
<h2>Überblick – Was ist neu?</h2>
<p>Das Wochenplansystem wurde vollständig überarbeitet. Hier wird ein Überblick über die Prozesse und einzelnen Funktionen gegeben.

</p>


<h2>Der Ablauf im Überblick</h2>

<p>Das typische Vorgehen sieht so aus:</p>
<ol>
  <li><strong>Klassenplan erstellen</strong> – Klasse, Zeitraum und Name eingeben, Fächer anpassen, Aufgaben pro Fach eingeben.</li>
  <li><strong>Optional: Kinderpläne erstellen</strong> – Aus dem Klassenplan heraus ein Kind auswählen und Aufgaben individuell anpassen.</li>
  <li><strong>Exportieren</strong> – PDF anzeigen und drucken oder Word-Datei herunterladen.</li>
  <li><strong>Nächste Woche: Vorlage nutzen</strong> – Plan duplizieren oder als Vorlage speichern, Zeitraum anpassen, Aufgaben aktualisieren.</li>
</ol>

<h3>Übersicht der Plantypen</h3>
<table border="1" style="border-collapse:collapse;width:100%;">
  <thead>
    <tr><th>Plantyp</th><th>Beschreibung</th></tr>
  </thead>
  <tbody>
    <tr><td><strong>Klassenplan</strong></td><td>Ein Plan für die gesamte Klasse. Ausgangspunkt für alle weiteren Pläne.</td></tr>
    <tr><td><strong>Individueller Kinderplan</strong></td><td>Ein auf ein bestimmtes Kind zugeschnittener Plan, der auf dem Klassenplan basiert.</td></tr>
    <tr><td><strong>Vorlage</strong></td><td>Eine wiederverwendbare Kopie eines Plans ohne Klassen- oder Zeitraum-Zuordnung.</td></tr>
  </tbody>
</table>

<hr>

<h2>Schritt-für-Schritt-Anleitungen</h2>

<h3>Klassenplan erstellen</h3>
<ol>
  <li>Öffnen Sie den Bereich <strong>Wochenpläne</strong> im Menü.</li>
  <li>Klicken Sie auf <strong>„+ Neuer Plan"</strong>.</li>
  <li>Füllen Sie das Formular aus:
    <ul>
      <li><strong>Bezeichnung</strong> – z.&nbsp;B. „11. WP SJ 2025/2026"</li>
      <li><strong>Klasse</strong> – Ihre Klasse aus der Dropdown-Liste wählen</li>
      <li><strong>Gültig von / bis</strong> – den Zeitraum des Wochenplans eingeben</li>
      <li><strong>Selbsteinschätzung</strong> – optional: Smiley, Skala oder keine</li>
      <li><strong>Aus Vorlage erstellen</strong> – optional: eine gespeicherte Vorlage als Ausgangsbasis wählen</li>
    </ul>
  </li>
  <li>Klicken Sie auf <strong>„Erstellen"</strong>.</li>
</ol>
<p>Das System legt automatisch die <strong>Standard-Fächer</strong> Deutsch, Mathe und Sachunterricht an. Sie landen direkt in der Bearbeitungsansicht.</p>

<h3>Aufgaben eingeben und bearbeiten</h3>
<p><strong>Aufgabe hinzufügen:</strong></p>
<ol>
  <li>Klicken Sie auf <strong>„+ Aufgabe hinzufügen"</strong> unterhalb eines Fachs.</li>
  <li>Geben Sie den Aufgabentext ein.</li>
  <li>Optional: Zeitdauer eingeben (z.&nbsp;B. „30 min").</li>
  <li>Mit <strong>Enter</strong> oder dem Speichern-Button bestätigen.</li>
</ol>
<p><strong>Aufgabe bearbeiten:</strong> Klicken Sie auf das Stift-Symbol ✏️ neben der Aufgabe, ändern Sie den Text und bestätigen Sie.</p>
<p><strong>Aufgabe löschen:</strong> Klicken Sie auf das Papierkorb-Symbol 🗑️ neben der Aufgabe.</p>
<p><strong>Reihenfolge ändern:</strong> Halten Sie die Greif-Punkte (⠿) links neben einer Aufgabe gedrückt und ziehen Sie die Aufgabe an die gewünschte Stelle (Drag &amp; Drop).</p>

<h3>Fächer verwalten</h3>
<p><strong>Fach hinzufügen:</strong> Klicken Sie auf <strong>„+ Fach hinzufügen"</strong> am Ende der Fächerliste und wählen Sie ein Fach aus dem Katalog oder geben Sie einen eigenen Namen ein.</p>
<p><strong>Fach entfernen:</strong> Klicken Sie auf das Papierkorb-Symbol 🗑️ im Fach-Header. Achtung: Alle Aufgaben des Fachs werden dabei ebenfalls gelöscht.</p>
<p><strong>Reihenfolge ändern:</strong> Über die Pfeil-Symbole ↕ im Fach-Header per Drag &amp; Drop verschieben.</p>
<p><strong>Fachname anpassen:</strong> Im Fach-Header auf den Namen klicken, um ihn für diesen Plan umzubenennen (ändert nur den Namen in diesem Plan, nicht im allgemeinen Fächerkatalog).</p>

<h3>Tägliche Übungen hinzufügen</h3>
<p>Tägliche Übungen erscheinen im PDF als Abhak-Tabelle – eine Spalte pro Wochentag im Planungszeitraum.</p>
<p><strong>Beispiel:</strong> Die Tabelle enthält Zeilen wie „10 min lesen" und „5 min Kopfrechnen" mit je einem leeren Kästchen pro Wochentag zum Abhaken.</p>
<p><strong>So aktivieren Sie tägliche Übungen:</strong></p>
<ol>
  <li>Klicken Sie in der Bearbeitungsansicht auf den Schalter <strong>„Tägliche Übungen"</strong>.</li>
  <li>Der Bereich klappt auf.</li>
  <li>Klicken Sie auf <strong>„+ Übung hinzufügen"</strong> und geben Sie die Aufgabe ein (z.&nbsp;B. „10 min lesen").</li>
  <li>Übungen können bearbeitet, gelöscht oder sortiert werden.</li>
</ol>
<p>Wenn keine täglichen Übungen aktiviert sind, erscheint dieser Bereich auch nicht im PDF.</p>

<h3>Individuellen Kinderplan erstellen</h3>
<p>Individuelle Kinderpläne werden immer <strong>aus einem Klassenplan heraus</strong> erstellt. Sie sind eine Kopie des Klassenplans, die Sie dann für das jeweilige Kind anpassen können.</p>
<ol>
  <li>Öffnen Sie den gewünschten Klassenplan in der Bearbeitungsansicht.</li>
  <li>Klicken Sie auf <strong>„👤 Kinderplan erstellen"</strong>.</li>
  <li>Wählen Sie das <strong>Kind</strong> aus der Liste der Klassen-Schülerinnen und -Schüler.</li>
  <li>Optional: Wählen Sie eine <strong>Formatvorlage</strong> (z.&nbsp;B. „Große Schrift" oder „Legasthenie-Unterstützung").</li>
  <li>Klicken Sie auf <strong>„Plan erstellen"</strong>.</li>
</ol>
<p>Das System erstellt automatisch eine vollständige Kopie aller Fächer und Aufgaben. Sie landen direkt im Kinderplan-Editor.</p>
<p><strong>Im Kinderplan-Editor können Sie:</strong></p>
<ul>
  <li>Aufgaben <strong>anpassen, löschen oder neue hinzufügen</strong></li>
  <li>Fächer <strong>hinzufügen oder entfernen</strong></li>
  <li>Aufgaben <strong>mit dem Klassenplan synchronisieren</strong></li>
  <li>Eine <strong>eigene Formatvorlage</strong> für diesen Plan wählen</li>
</ul>
<p>💡 <strong>Hinweis:</strong> Der Link zum Klassenplan bleibt erhalten – Sie können jederzeit über <strong>„← Zurück zum Klassenplan"</strong> navigieren.</p>

<h3>Aufgaben vom Klassenplan synchronisieren</h3>
<p>Wenn Sie den Klassenplan nachträglich geändert haben und diese Änderungen in einen Kinderplan übernehmen möchten, nutzen Sie die <strong>Synchronisation</strong>.</p>
<p><strong>So synchronisieren Sie ein einzelnes Fach:</strong></p>
<ol>
  <li>Öffnen Sie den Kinderplan in der Bearbeitungsansicht.</li>
  <li>Klicken Sie im gewünschten Fach auf <strong>„🔄 Vom Klassenplan synchronisieren"</strong>.</li>
  <li>Bestätigen Sie die Meldung.</li>
</ol>
<p>⚠️ <strong>Achtung:</strong> Beim Sync werden die bisherigen Aufgaben dieses Fachs im Kinderplan durch die aktuellen Aufgaben des Klassenplans <strong>ersetzt</strong>. Individuelle Anpassungen gehen verloren. Danach sind die Aufgaben wieder frei bearbeitbar.</p>
<p>Synchronisierte Aufgaben werden mit einem kleinen 🔗-Symbol markiert.</p>

<h3>Vorlagen nutzen</h3>
<p><strong>Plan als Vorlage speichern:</strong></p>
<ol>
  <li>Öffnen Sie den gewünschten Plan.</li>
  <li>Klicken Sie auf <strong>„Als Vorlage speichern"</strong>.</li>
  <li>Geben Sie einen <strong>Namen für die Vorlage</strong> ein (z.&nbsp;B. „Standardstruktur Klasse 4").</li>
  <li>Die Vorlage ist beim nächsten neuen Plan zur Auswahl verfügbar.</li>
</ol>
<p><strong>Von einer Vorlage aus einen neuen Plan erstellen:</strong></p>
<ol>
  <li>Klicken Sie auf <strong>„+ Neuer Plan"</strong>.</li>
  <li>Wählen Sie unter <strong>„Aus Vorlage erstellen"</strong> Ihre gespeicherte Vorlage aus.</li>
  <li>Tragen Sie Klasse und Zeitraum ein.</li>
  <li>Alle Fächer und Aufgaben der Vorlage werden übernommen – Sie müssen nur noch die Aufgaben aktualisieren.</li>
</ol>
<p><strong>Plan duplizieren:</strong> Klicken Sie in der Bearbeitungsansicht auf <strong>„📋 Duplizieren"</strong>. Es wird eine 1:1-Kopie des Plans angelegt, die Sie umbenennen und anpassen können.</p>
<p><strong>Vorlagen verwalten:</strong> In der Wochenplan-Übersicht gibt es einen Tab <strong>„Vorlagen"</strong> mit allen gespeicherten Vorlagen. Vorlagen können dort bearbeitet und gelöscht werden.</p>

<h3>Arbeitsblätter anhängen</h3>
<p>An jeden Plan können Dateien angehängt werden (z.&nbsp;B. PDFs oder Bilder von Arbeitsblättern).</p>
<ol>
  <li>Scrollen Sie in der Bearbeitungsansicht nach unten zum Bereich <strong>„Arbeitsblätter"</strong>.</li>
  <li>Klicken Sie auf <strong>„+ Datei hochladen"</strong>.</li>
  <li>Wählen Sie die Datei von Ihrem Computer aus.</li>
  <li>Die Datei erscheint in der Liste und kann mit dem 🗑️-Symbol wieder entfernt werden.</li>
</ol>
<p>💡 Die angehängten Arbeitsblätter sind im System gespeichert und dienen der Übersicht. Sie werden <strong>nicht automatisch</strong> in die PDF-Ausgabe eingebunden.</p>

<h3>Plan als PDF oder Word exportieren</h3>
<p><strong>PDF im Browser anzeigen und drucken:</strong></p>
<ol>
  <li>Klicken Sie in der Bearbeitungsansicht auf <strong>„📄 PDF"</strong>.</li>
  <li>Der Plan öffnet sich als PDF direkt im Browser.</li>
  <li>Über die Druckfunktion des Browsers können Sie ihn ausdrucken.</li>
</ol>
<p><strong>Word-Datei herunterladen:</strong></p>
<ol>
  <li>Klicken Sie auf <strong>„📝 Word"</strong>.</li>
  <li>Eine <code>.docx</code>-Datei wird heruntergeladen.</li>
  <li>Diese können Sie mit Microsoft Word oder LibreOffice weiter bearbeiten.</li>
</ol>
<p><strong>Druckvorschau:</strong> Über <strong>„Vorschau"</strong> können Sie eine druckoptimierte HTML-Ansicht des Plans aufrufen, ohne dass eine Datei erzeugt wird.</p>

<hr>

<h2>Formatvorlagen – Pläne für besondere Bedürfnisse</h2>
<p>Formatvorlagen steuern das <strong>Aussehen des gedruckten Plans</strong> (PDF). Das ist besonders wichtig für Kinder mit besonderen Bedürfnissen.</p>

<h3>Verfügbare Formatvorlagen</h3>
<table border="1" style="border-collapse:collapse;width:100%;">
  <thead>
    <tr><th>Name</th><th>Beschreibung</th><th>Geeignet für</th></tr>
  </thead>
  <tbody>
    <tr><td><strong>Standard</strong></td><td>Normale Schriftgröße, übersichtliches Layout</td><td>Alle Kinder</td></tr>
    <tr><td><strong>Große Schrift</strong></td><td>Größere Schrift, mehr Zeilenabstand</td><td>Sehbehinderte Kinder</td></tr>
    <tr><td><strong>Sehr große Schrift</strong></td><td>Sehr große Schrift</td><td>Stark sehbehinderte Kinder</td></tr>
    <tr><td><strong>Legasthenie-Unterstützung</strong></td><td>OpenDyslexic-Schrift, angepasstes Layout</td><td>Kinder mit Legasthenie</td></tr>
  </tbody>
</table>

<h3>Formatvorlage zuweisen</h3>
<p><strong>Für einen Klassenplan:</strong> In der Bearbeitungsansicht unter <strong>„Formatvorlage"</strong> eine Option aus dem Dropdown wählen.</p>
<p><strong>Für einen Kinderplan:</strong> Beim Erstellen des Kinderplans eine Formatvorlage auswählen oder im Kinderplan-Editor die Formatvorlage ändern.</p>
<p>💡 <strong>Hinweis:</strong> Die Formatvorlage gilt nur für den <strong>PDF-Export</strong>. Die Bearbeitungsansicht im Browser sieht immer gleich aus.</p>

<h3>Weitere Einstellungen einer Formatvorlage</h3>
<ul>
  <li><strong>Namenszeile</strong> – Höhe der Zeile, in der das Kind seinen Namen einträgt (nützlich für Kinder mit motorischen Einschränkungen)</li>
  <li><strong>Dauer-Spalte</strong> – optionale Spalte mit der vorgesehenen Bearbeitungszeit pro Aufgabe</li>
  <li><strong>Unterschriften</strong> – Unterschriftszeilen für Eltern und Lehrkraft</li>
  <li><strong>Selbsteinschätzung</strong> – Smiley oder Skala am Ende des Plans</li>
</ul>

<hr>

<h2>Selbsteinschätzung für Schülerinnen und Schüler</h2>
<p>Die Selbsteinschätzung erscheint als extra Bereich <strong>am Ende des gedruckten Plans</strong>. Das Kind kann dort ankreuzen oder anmalen, wie es gearbeitet hat.</p>

<h3>Varianten</h3>
<p><strong>Smiley (Variante 1):</strong> Drei Felder mit den Smileys 😟&nbsp;schwierig, 😐&nbsp;okay und 😊&nbsp;gut.</p>
<p><strong>Skala (Variante 2):</strong> Ein Zahlenstrahl von 1 bis 10.</p>

<h3>Aktivieren</h3>
<p>Beim Erstellen oder Bearbeiten eines Plans unter <strong>„Selbsteinschätzung"</strong> eine der Optionen wählen:</p>
<ul>
  <li><strong>Keine</strong> – kein Bereich im PDF</li>
  <li><strong>Smiley</strong> – drei Smileys (glücklich, neutral, traurig)</li>
  <li><strong>Skala</strong> – Zahlenstrahl von 1 bis 10</li>
</ul>

<hr>

<h2>Häufige Fragen (FAQ)</h2>

<p><strong>Kann ich einen Plan bearbeiten, nachdem er schon gedruckt wurde?</strong><br>
Ja. Änderungen wirken sich beim nächsten Export aus. Bereits gedruckte Exemplare bleiben natürlich unverändert.</p>

<p><strong>Was passiert, wenn ich einen Kinderplan vom Klassenplan synchronisiere?</strong><br>
Die Aufgaben des jeweiligen Fachs im Kinderplan werden durch die aktuellen Aufgaben des Klassenplans ersetzt. Individuelle Anpassungen gehen verloren. Danach kann der Kinderplan wieder frei bearbeitet werden.</p>

<p><strong>Können mehrere Personen gleichzeitig an einem Plan arbeiten?</strong><br>
Grundsätzlich ist das möglich, aber es gibt kein Echtzeit-Kollaborationssystem. Wenn zwei Personen denselben Plan gleichzeitig bearbeiten, kann es zu Überschreibungen kommen. Es empfiehlt sich, Pläne nacheinander zu bearbeiten.</p>

<p><strong>Wie lösche ich einen Plan?</strong><br>
Über das 🗑️-Symbol in der Übersicht oder über <strong>„Löschen"</strong> in der Bearbeitungsansicht. Gelöschte Pläne werden zunächst nur als gelöscht markiert und können ggf. von einem Administrator wiederhergestellt werden.</p>

<p><strong>Kann ich Fächer umbenennen?</strong><br>
Ja – für jeden einzelnen Plan kann der Fachname angepasst werden. Der allgemeine Fächerkatalog kann nur von Administratoren verwaltet werden.</p>

<p><strong>Was ist der Unterschied zwischen „Duplizieren" und „Als Vorlage speichern"?</strong><br>
<em>Duplizieren</em> erstellt sofort eine Kopie des Plans mit allen Metadaten (Klasse, Zeitraum). Die Kopie kann dann umbenannt und angepasst werden.<br>
<em>Als Vorlage speichern</em> erzeugt eine Blaupause ohne Klassen- oder Zeitraumzuordnung. Diese Vorlage erscheint beim nächsten neuen Plan zur Auswahl.</p>

<p><strong>Welche Dateiformate kann ich als Arbeitsblatt hochladen?</strong><br>
Das System unterstützt gängige Formate wie PDF, JPG und PNG. Für die maximale Dateigröße gelten die allgemeinen Servereinstellungen.</p>

<p><strong>Kann ich die Formatvorlage eines Plans nachträglich ändern?</strong><br>
Ja. In der Bearbeitungsansicht kann die Formatvorlage jederzeit über das Dropdown geändert werden. Beim nächsten PDF-Export wird das neue Layout verwendet.</p>

<p><strong>Ich sehe die Formatvorlage „Legasthenie-Unterstützung" nicht. Was tun?</strong><br>
Diese Vorlage sollte standardmäßig vorhanden sein. Wenden Sie sich bitte an die Administration, falls sie fehlt.</p>

<hr>
<p><em>Bei weiteren Fragen oder technischen Problemen wenden Sie sich bitte an die Systemadministration.</em></p>
HTML;
    }
};

