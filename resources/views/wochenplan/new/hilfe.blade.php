@extends('layouts.app')

@push('css')
    @vite('resources/css/wochenplan.css')
@endpush

@section('content')
<div class="wp-wrapper" style="max-width: 900px">
    <a href="{{ route('wp.index') }}" class="wp-back">← Übersicht</a>
    <div class="wp-head">
        <div>
            <h1 class="wp-title">Anleitung Wochenplan</h1>
            <p class="wp-subtitle">So arbeiten Sie mit Klassenplänen, Kinderplänen, Vorlagen und dem Fortschritt je Kind.</p>
        </div>
        <a href="{{ route('wp.index', ['tour' => 'wochenplan']) }}" class="wp-btn wp-btn-secondary wp-btn-sm">Rundgang starten</a>
    </div>

    <div class="wp-card">
        <div class="wp-card-body wp-prose">
            <h2>Wer sieht welche Pläne?</h2>
            <p>Alle Pädagog:innen sehen und bearbeiten die Pläne ihrer Klassen – also der Klassen, denen sie im Pädagogischen Tagebuch zugeordnet sind. Kinderpläne gehören zur aktuellen Klasse des Kindes. Schulleitung und Verwaltung können mit dem Recht „manage all wochenplaene“ alle Pläne bearbeiten. Vorlagen stehen allen zur Verfügung.</p>

            <h2>Klassenplan anlegen</h2>
            <ul>
                <li>„Neuer Plan“ – Klasse und Zeitraum wählen, optional eine Vorlage.</li>
                <li>Aufgaben direkt im Fach eintragen. Zu jeder Aufgabe gibt es optional Dauer, Niveau (Basis, Standard, Erweitert) und einen Link – im PDF erscheint der Link als QR-Code.</li>
                <li>Fächer und Aufgaben lassen sich am Griff ⠿ verschieben, Aufgaben auch in ein anderes Fach.</li>
                <li>Alles wird sofort gespeichert. Arbeiten zwei Personen gleichzeitig am selben Plan, erscheint ein Hinweis und der aktuelle Stand wird geladen – nichts wird still überschrieben.</li>
            </ul>

            <h2>Kinderpläne</h2>
            <ul>
                <li>„Kinderplan erstellen“ übernimmt alle Aufgaben des Klassenplans für ausgewählte Kinder. Mit Niveau werden nur Aufgaben ohne Niveau und die des gewählten Niveaus übernommen.</li>
                <li>Aufgaben im Kinderplan dürfen angepasst werden. Sie sind dann als „angepasst“ markiert; eigene Aufgaben als „eigene“.</li>
                <li>„Abgleich prüfen“ zeigt vorher, was sich ändert: neue, aktualisierte und entfallene Aufgaben. Angepasste und eigene Aufgaben bleiben auf Wunsch erhalten, Häkchen bleiben bestehen.</li>
                <li>Arbeitsblätter des Klassenplans gelten automatisch auch für alle Kinderpläne.</li>
                <li>Alle Kinderpläne eines Klassenplans gibt es als ein PDF oder als ZIP mit PDF und Word je Kind.</li>
            </ul>

            <h2>Fortschritt je Kind</h2>
            <p>Unter „Fortschritt“ haken Sie pro Kind und Aufgabe ab: ein Klick schaltet offen → teilweise → erledigt → offen. Mit gedrückter Umschalt- oder Strg-Taste wählen Sie mehrere Zellen aus und setzen sie gemeinsam; „alle erledigt“ gibt es pro Aufgabe. Zu jedem Eintrag kann eine kurze Notiz gespeichert werden, sie ist nur für Pädagog:innen sichtbar.</p>

            <h2>Nächste Woche</h2>
            <p>„Nächste Woche“ legt eine Kopie für die Folgewoche an – auf Wunsch mit allen Kinderplänen und ihren individuellen Aufgaben. Arbeitsblätter und Häkchen werden nicht übernommen.</p>

            <h2>Vorlagen und Aufgaben-Bibliothek</h2>
            <ul>
                <li>„Als Vorlage“ speichert einen Plan ohne Klasse und Kind. Aus Vorlagen entstehen neue Pläne mit allen Fächern und Aufgaben.</li>
                <li>Einzelne Aufgaben übernehmen Sie mit 📚 in die Bibliothek – sortiert nach Fach und Klassenstufe. Über 📚 am Fach fügen Sie Aufgaben aus der Bibliothek ein.</li>
            </ul>

            <h2>Nextcloud</h2>
            <p>Ist die Nextcloud-Ablage eingeschaltet, liegen Arbeitsblätter und die fertigen Pläne (PDF und Word) zentral in der Nextcloud. Nach jeder Änderung wird der Plan etwa zwei Minuten später automatisch neu abgelegt. Ändert sich Name, Zeitraum oder Klasse, wird der Ordner verschoben; gelöschte Pläne landen im Ordner „_gelöscht“. Die Ordnerstruktur legt die Verwaltung in den Einstellungen fest. Bitte die abgelegten Pläne nicht direkt in der Nextcloud bearbeiten – sie werden beim nächsten Speichern überschrieben.</p>

            <h2>Pädagogisches Tagebuch</h2>
            <p>Ist die Anbindung eingeschaltet, können offene Tagebuch-Aufgaben in einen Kinderplan übernommen werden. „Erledigt“ wird in beide Richtungen abgeglichen. In der Wochenansicht des Tagebuchs steht bei jedem Kind der Stand seines Wochenplans (z. B. „5 ✓ · 2 ◐ von 8“); ein Klick öffnet die Aufgaben zum Abhaken. In der Schülerakte erscheinen die Wochenpläne des Kindes.</p>
        </div>
    </div>
</div>
@endsection
