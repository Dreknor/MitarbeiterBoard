<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Carbon;

class UpdatePaedDiaryWikiToHtml extends Migration
{
    /**
     * Run the migrations.
     * Aktualisiert den bestehenden Wiki-Eintrag auf HTML-Auszeichnung.
     *
     * @return void
     */
    public function up()
    {
        $title = 'Pädagogisches Tagebuch — Handbuch';

        $text = <<<'HTML'
<h2>Pädagogisches Tagebuch — Kurzanleitung / Handbuch</h2>

<p>Dieses Dokument gibt einen kompakten Überblick über den Funktionsumfang des pädagogischen Tagebuchs und erklärt die wichtigsten Arbeitsabläufe.</p>

<h3>1. Übersicht</h3>
<ul>
  <li>Wochenansicht (Montag–Freitag) mit einer Zeile pro Schüler und einer Spalte pro Tag.</li>
  <li>Notizen (Einträge) für einzelne Schüler an bestimmten Tagen.</li>
  <li>Zusätzliche, frei definierbare Spalten (Columns) pro Klasse zur Erfassung von Zusatzinformationen (z. B. Anwesenheit, Verhalten).</li>
  <li>Aufgaben (Tasks) und Termine (Appointments) mit Zuweisung an Klassen, Gruppen oder einzelne Schüler.</li>
  <li>Gruppenmodus: mehrere Klassen können zu einer Gruppe gekoppelt werden (Klassenkopplung).</li>
  <li>Exportfunktionen für Excel/CSV.</li>
</ul>

<h3>2. Begriffe</h3>
<ul>
  <li><strong>Eintrag / Notiz</strong>: Ein Tagebucheintrag für einen oder mehrere Schüler an einem Datum.</li>
  <li><strong>Spalte</strong>: Zusätzliche, benutzerdefinierte Felder für Schüler/Datum (boolean, text, number).</li>
  <li><strong>Gruppe</strong>: Sammlung mehrerer Klassen zur parallelen Ansicht und Bearbeitung.</li>
  <li><strong>Aufgabe</strong>: Offene To‑Do‑Einträge, die hervorgehoben und abgeschlossen werden können.</li>
  <li><strong>Termin</strong>: Einzel- oder wiederkehrender Termin, sichtbar in Kopf- und Schülerzellen.</li>
</ul>

<h3>3. Tagebuch bedienen</h3>
<ul>
  <li><strong>Klasse auswählen</strong>: Oben links die Klasse wählen. Bei Klassenkopplung die Gruppe auswählen.</li>
  <li><strong>Woche navigieren</strong>: ← / Heute / → für Wochenwechsel.</li>
  <li><strong>Neue Notiz</strong>: Zelle anklicken oder „Neue Notiz“ öffnen, Datum wählen, Schüler markieren und Text eingeben.</li>
  <li><strong>Notiz bearbeiten / löschen</strong>: Notiz klicken → bearbeiten oder löschen.</li>
</ul>

<h4>Notizen — Verhalten (erledigt / nicht erledigt)</h4>
<ul>
  <li><strong>Erstellung</strong>: Eine Notiz wird mit Datum, Text und mindestens einem Schüler angelegt. Optional kann die Notiz sofort als "erledigt" markiert werden.</li>
  <li><strong>Nicht erledigte Notizen</strong> (offen): Solche Einträge bleiben aktiv und werden in der Liste als offene Notizen angezeigt. Offene Notizen, die vor der aktuellen Woche erstellt wurden, werden automatisch in die Anzeige der aktuellen Woche übernommen, damit sie sichtbar bleiben, bis sie abgeschlossen werden.</li>
  <li><strong>Erledigen (Abschließen)</strong>: Beim Setzen auf "Erledigt" wird das Feld completed_at gesetzt. Zusätzlich werden beim Abschließen automatisch für alle Tage zwischen dem ursprünglichen Datum der Notiz und dem Abschlussdatum Kopien des Eintrags erstellt, sofern für einen bestimmten Tag noch kein gleichartiger Eintrag für dieselben Schüler existiert. Damit wird die Historie lückenlos fortgeschrieben.</li>
  <li><strong>Re-Open (wieder öffnen)</strong>: Wird eine bereits abgeschlossene Notiz wieder geöffnet (completed entfernt), bleibt die Historie der bereits erstellten Kopien bestehen; die ursprüngliche Notiz erhält wieder den offenen Status. Bereits geklonte Einträge werden nicht automatisch entfernt.</li>
  <li><strong>Mehrere Schüler</strong>: Eine Notiz kann mehreren Schülern gleichzeitig zugewiesen werden. Beim Abschließen/ Öffnen gilt die Aktion für alle zugewiesenen Schüler derselben Notiz.</li>
  <li><strong>Löschen</strong>: Löschen entfernt die Notiz und die Pivot‑Verknüpfungen. Kopien, die bereits in anderen Wochen erzeugt wurden, bleiben unverändert (je nach Systemkonfiguration können ältere Einträge separat entfernt werden).</li>
  <li><strong>Anzeige</strong>: Offene Notizen werden in einem eigenen Panel ("Offene Aufgaben / Offene Notizen") hervorgehoben. Abgeschlossene Notizen erscheinen in der Wochenansicht an ihrem jeweiligen Datum; offene Notizen werden zusätzlich in folgenden Wochen angezeigt, bis sie abgeschlossen sind.</li>
  <li><strong>Gruppenmodus</strong>: Beim Arbeiten mit Gruppen werden Notizen separat je Klasse angelegt (durch die Gruppenoperation werden Einträge für jede beteiligte Klasse erzeugt). Die Erledigt/Offen‑Logik gilt pro Klassen‑Eintrag.</li>
  <li><strong>Rechte</strong>: Nur berechtigte Nutzer dürfen Notizen abschließen, wieder öffnen oder löschen. Autorenname und Zeitpunkt werden protokolliert.</li>
</ul>


<p>Dieser Abschnitt beschreibt das Verhalten der Notizen (Einträge) im System, insbesondere die Unterscheidung zwischen offenen und abgeschlossenen Notizen sowie die automatische Klon‑Logik beim Abschließen.</p>

<h5>Erstellung</h5>
<ul>
  <li>Notizen benötigen ein Datum, einen Text und mindestens einen zugewiesenen Schüler.</li>
  <li>Beim Anlegen kann optional die Option "Erledigt" gesetzt werden; andernfalls bleibt die Notiz zunächst offen.</li>
  <li>Im Gruppenmodus werden bei Anlage Einträge getrennt pro Klasse erzeugt (eine Aktion erzeugt pro beteiligter Klasse eigenen Eintrag).</li>
</ul>

<h5>Offene Notizen (nicht erledigt)</h5>
<ul>
  <li>Offene Notizen werden in einem separaten Panel als "Offene Notizen / Offene Aufgaben" gelistet und sind in der Wochenansicht besonders sichtbar.</li>
  <li>Wurden offene Notizen in einer früheren Woche angelegt, erscheinen sie zusätzlich in allen nachfolgenden Wochen (bis zur Erledigung), damit Verantwortliche sie nicht übersehen.</li>
  <li>Mehrere Schüler: Ein offener Eintrag, der mehreren Schülern zugewiesen ist, gilt für alle zugewiesenen Schüler; Aktionen (z. B. Abschließen) wirken auf die gesamte Notiz und damit auf alle betroffenen Schüler derselben Notiz.</li>
</ul>

<h5>Abschließen (Erledigen)</h5>
<ul>
  <li>Beim Abschließen wird das Feld <code>completed_at</code> gesetzt. Dadurch wird die Notiz nicht gelöscht, sondern als erledigt markiert.</li>
  <li>Unmittelbar nach dem Markieren als erledigt wird eine Klon‑Logik ausgeführt: Für jeden Tag zwischen dem ursprünglichen Notizdatum und dem Abschlussdatum (inklusive) werden Kopien des Eintrags erstellt, sofern an dem jeweiligen Tag noch kein Eintrag mit identischem Inhalt für dieselben Schüler existiert.</li>
  <li>Ziel dieser Logik ist es, die Historie lückenlos fortzuschreiben — z. B. damit sichtbar bleibt, an welchen Tagen der Zustand gültig war.</li>
  <li>Die Klon‑Operation ignoriert bereits vorhandene, gleichartige Einträge und erzeugt somit keine Duplikate.</li>
</ul>

<h5>Wieder öffnen (Re‑Open)</h5>
<ul>
  <li>Wird eine bereits abgeschlossene Notiz wieder geöffnet (das Attribut <code>completed_at</code> wird entfernt), erhält der Originaleintrag wieder den offenen Status.</li>
  <li>Die zuvor erzeugten Klon‑Einträge bleiben bestehen; sie werden nicht automatisch entfernt oder geändert. Dadurch bleibt die lückenlose Historie erhalten, auch wenn ein Eintrag später re‑opened wird.</li>
  <li>Sollten die geklonten Einträge nicht mehr gewünscht sein, müssen sie manuell entfernt werden (je nach Rechtevergabe).</li>
</ul>

<h5>Löschen</h5>
<ul>
  <li>Beim Löschen einer Notiz werden die Verknüpfungen (Pivot) zu Schülern entfernt und der Eintrag gelöscht.</li>
  <li>Kopien, die bereits durch die Klon‑Logik in andere Wochen geschrieben wurden, bleiben in der Regel erhalten; eine gezielte Bereinigung älterer Kopien ist nur über gesonderte Administrations‑Funktionen möglich.</li>
</ul>
<h3>4. Spaltenverwaltung</h3>
<ul>
  <li><strong>Spalten erstellen</strong>: Über „Spalten verwalten“ neue Spalten für eine Klasse hinzufügen. Typen: boolean, text, number.</li>
  <li><strong>Kategorien</strong>: Spalten können kategorisiert werden, um die Anzeige pro Zelle zu gruppieren.</li>
  <li><strong>Deaktivieren</strong>: Spalte kann ab einer bestimmten Woche deaktiviert werden (Werte dieser und späterer Wochen werden entfernt). Reaktivierung möglich.</li>
  <li><strong>Inline-Bearbeitung</strong>: Werte werden direkt in der Tabelle eingegeben; Booleans per Klick‑Button.</li>
</ul>

<h3>5. Gruppenmodus</h3>
<ul>
  <li>Mehrere Klassen werden zu einer Ansicht kombiniert; Schüler werden nach Klassen gruppiert angezeigt.</li>
  <li>Manche Funktionen (z. B. Spaltenverwaltung) stehen nur im Klassenmodus zur Verfügung.</li>
  <li>Beim Erstellen von Einträgen/Terminen kann die Aktion auf alle Klassen der Gruppe angewendet werden.</li>
</ul>

<h3>6. Termine &amp; Aufgaben</h3>
<ul>
  <li><strong>Termine</strong>: Können Klassen, Gruppen oder einzelne Schüler betreffen; wiederkehrende Termine möglich.</li>
  <li><strong>Aufgaben</strong>: Einzelne Schüler zuweisen. Offene Aufgaben erscheinen in einem separaten Panel und können als erledigt markiert werden.</li>
</ul>

<h3>7. Export</h3>
<p>Export als Excel/CSV pro Klasse oder Gruppe für die gewählte Woche.</p>

<h3>8. Rechte &amp; Sicherheit</h3>
<ul>
  <li>Zugriff auf Klassen, Gruppen, Spalten und Aktionen erfolgt anhand der Benutzerberechtigungen.</li>
  <li>Änderungen an Stufen (grading stages) und an Terminen sind nur für berechtigte Nutzer möglich.</li>
</ul>

<h3>9. Tipps</h3>
<ul>
  <li>Bei gruppenweitem Arbeiten zuerst eine Gruppe anlegen und testen.</li>
  <li>Spaltenkategorien nutzen, um die Zellen übersichtlich zu halten.</li>
  <li>Offene Notizen regelmäßig abschließen, wenn sie auf mehrere Tage gelten sollen.</li>
</ul>

<hr>
HTML;

        if (!Schema::hasTable('wiki_sites')) {
            return;
        }

        DB::table('wiki_sites')->where('title', $title)->updateOrInsert(
            [
            'title' => $title],
            [
            'text' => $text,
            'updated_at' => Carbon::now(),
            ]);
    }

    /**
     * Reverse the migrations.
     * Keine Rückgängig-Operation implementiert.
     *
     * @return void
     */
    public function down()
    {
        // intentionally empty
    }
}

