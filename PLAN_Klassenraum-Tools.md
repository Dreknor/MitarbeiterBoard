# Klassenraum-Tools – Plan für weitere Werkzeuge (Grundschule)

Stand: November 2026 · Bereich `/klassenraum`, Recht `use classroom tools`

## Ausgangslage

Im MitarbeiterBoard gibt es den Bereich **Klassenraum-Tools** mit vier Werkzeugen:

| Tool | Kategorie | Kurzbeschreibung |
|---|---|---|
| Aquarium | Lautstärke | Fische verstecken sich bei Lärm, das Wasser trübt ein |
| Sternenhimmel | Lautstärke | In der Stille gehen Sterne auf, bei Lärm ziehen Wolken auf |
| Blumenwiese | Lautstärke | Blüten schließen sich bei Lärm, es beginnt zu regnen |
| Timer | Zeit | Große Zeitscheibe mit Schnellwahl, Aufgabentext und Gong |

Technisch ist der Bereich so gebaut, dass neue Tools wenig Aufwand machen:

- **Verzeichnis** `app/Support/KlassenraumTools.php`: Ein Eintrag (Titel, Kategorie, Symbol, Farbe, Vite-Entry) reicht, damit das Tool in Übersicht, Route und Tests auftaucht.
- **Lautstärke-Motor** `resources/js/klassenraum/shared/lautstaerke.js`: Mikrofon, Grenze, Reaktionszeit, Anzeige, Einstellungen, Vollbild und Uhr. Ein neues Lautstärke-Thema ist nur noch eine Szene (`szenen/*.js`, rund 300 Zeilen Canvas-Code).
- **Gemeinsame Bedienung** `shared/oberflaeche.js`: Vollbild, automatisches Ausblenden, Uhr, Einstellungsfenster, synthetische Töne (keine Audiodateien).

Die Vorschläge unten sind nach **Nutzen im Grundschulalltag** und **Aufwand** sortiert. Grundsatz bleibt: Alles läuft im Browser, ohne externe Dienste, ohne Tracking; personenbezogene Daten nur dort, wo sie wirklich gebraucht werden.

---

## Phase 1 – Schnelle Gewinne (ohne Schülerdaten)

Diese Tools brauchen keine Daten vom Server und können wie die bestehenden als reine Frontend-Tools umgesetzt werden.

### 1. Stimmlautstärke-Skala („Wie laut darf ich gerade sein?“)
- **Zweck:** Die erlaubte Lautstärke für die aktuelle Phase sichtbar machen – 0 Stille, 1 Flüstern, 2 Partnerstimme, 3 Gruppenstimme, 4 Vortragsstimme.
- **Umsetzung:** Große Stufenkarte mit Symbol und Farbe, per Klick oder Taste 0–4 umschalten. Optional mit dem Lautstärke-Motor kombinieren: Die Grenze folgt automatisch der gewählten Stufe.
- **Aufwand:** gering (1–2 Tage). **Nutzen:** sehr hoch – ergänzt die drei Lautstärke-Szenen um eine klare Regel.

### 2. Lärmampel (Thema für Klasse 1/2)
- **Zweck:** Für die Jüngsten ist eine Ampel eindeutiger als eine Szene.
- **Umsetzung:** Vierte Szene für den bestehenden Motor (grün/gelb/rot, freundliches Gesicht). Nur `szenen/ampel.js` und ein Eintrag im Verzeichnis.
- **Aufwand:** gering (½–1 Tag).

### 3. Arbeitsphasen-Timer (Ablauf mit mehreren Schritten)
- **Zweck:** Unterrichtsphasen hintereinander takten, z. B. „8 min Lesen → 2 min Aufräumen → 5 min Sitzkreis“.
- **Umsetzung:** Erweiterung des Timers um eine Schrittliste mit Symbolen; automatischer Wechsel mit Gong, Vorschau auf den nächsten Schritt. Abläufe zunächst im Browser speichern, später optional am Benutzerkonto.
- **Aufwand:** mittel (2–3 Tage). **Nutzen:** hoch, besonders für Kinder, die Struktur brauchen.

### 4. Atem- und Ruheübung
- **Zweck:** Ankommen nach der Pause, Beruhigung vor Arbeitsphasen.
- **Umsetzung:** Sanft wachsender und schrumpfender Kreis („Einatmen … Ausatmen …“), wählbarer Rhythmus (4-4, 4-6, Box-Atmung), Dauer 1–5 Minuten, optional leiser Klang.
- **Aufwand:** gering (1 Tag).

### 5. Würfel, Münze und Zufallszahl
- **Zweck:** Spiele, Mathematik (Mengenbilder), faire Entscheidungen.
- **Umsetzung:** 1–3 große Würfel mit Augen oder Zahlen (auch W10/W20 für höhere Klassen), Münzwurf, Zufallszahl im Bereich. Animiert, mit Tastatur bedienbar.
- **Aufwand:** gering (1 Tag).

### 6. Visuelle Timer-Themen
- **Zweck:** Abwechslung und kindgerechte Bilder statt Zeitscheibe.
- **Umsetzung:** Wählbare Darstellung im Timer: Sanduhr, Kerze, Raupe frisst Blätter, Ballon verliert Luft. Die Zeitlogik bleibt dieselbe.
- **Aufwand:** gering bis mittel (je Thema ½ Tag).

---

## Phase 2 – Tools mit Daten aus dem MitarbeiterBoard

Hier liegt der eigentliche Mehrwert gegenüber frei verfügbaren Webseiten: Das MitarbeiterBoard kennt bereits **Klassen** (`Klasse`), **Schüler:innen** (`Schueler`: Vorname, Nachname, Geburtsdatum, Klasse), das **Zeitraster** (`Zeitraster`) und den **Vertretungsplan** (`Vertretung`). Für diese Tools gilt:

- Daten nur für Klassen, auf die die Lehrkraft Zugriff hat (vorhandene `StudentPolicy`).
- Auf dem Beamer nur **Vornamen** (bei Gleichheit plus Initiale), niemals Nachname oder Geburtsdatum.
- Datenschutzhinweis in der Übersicht ergänzen; bei Bedarf mit der Schulleitung bzw. dem Datenschutzbeauftragten abstimmen.

### 7. Namen ziehen (Losglück)
- **Zweck:** Wer ist dran? Fair und ohne „immer dieselben“.
- **Umsetzung:** Klasse wählen → Vornamen aus der Klassenliste; Ziehen mit Animation (Glücksrad oder Lostrommel); Option „Gezogene nicht wiederholen, bis alle dran waren“; Abwesende abwählen (später aus `PaedDiarySchuelerAbsence` vorbelegen).
- **Aufwand:** mittel (2 Tage). **Nutzen:** sehr hoch.

### 8. Gruppen bilden
- **Zweck:** Zufällige Partner- oder Gruppenarbeit.
- **Umsetzung:** Gleiche Datenquelle wie „Namen ziehen“; Gruppengröße oder Gruppenanzahl wählen; Ergebnis als große Karten mit Gruppenfarben; „Nicht zusammen“-Paare als Option (nur im Browser der Lehrkraft gespeichert).
- **Aufwand:** mittel (1–2 Tage, baut auf Nr. 7 auf).

### 9. Morgenkreis / Tagesanzeige
- **Zweck:** Datum, Wochentag, Jahreszeit, Wetter (manuell wählbar) und Geburtstagskinder des Tages – Ritual für den Tagesbeginn.
- **Umsetzung:** Kalenderkreis mit großen Symbolen; Geburtstage aus `Schueler.geburtsdatum` (nur Vorname und „hat heute Geburtstag“, kein Alter ohne Freigabe); Ferien und Feiertage aus `App\Support\Feiertage`.
- **Aufwand:** mittel (2 Tage).

### 10. Tagesplan mit Bildkarten
- **Zweck:** Visueller Tagesablauf (Deutsch, Mathe, Hofpause, Sport …) mit Hervorhebung der aktuellen Stunde; hilfreich für Kinder mit Förderbedarf und für den Ganztag/Hort.
- **Umsetzung:** Stunden aus dem `Zeitraster`, Fächer per Symbolkarte zuordnen; Vorlage pro Klasse und Wochentag (neue Tabelle). Später Vertretungen aus dem Vertretungsplan einblenden.
- **Aufwand:** hoch (4–5 Tage inkl. Verwaltung).

### 11. Klassendienste
- **Zweck:** Wer hat diese Woche Tafel-, Blumen-, Ordnungsdienst?
- **Umsetzung:** Dienste pro Klasse definieren, automatische wöchentliche Rotation über die Klassenliste, Anzeige als Plakat. Neue Tabelle `klassen_dienste`.
- **Aufwand:** mittel (2–3 Tage).

---

## Phase 3 – Lernwerkzeuge für den Unterricht

Fachbezogene Werkzeuge für Tafel und Smartboard; ohne Schülerdaten, aber inhaltlich aufwendiger.

| Nr. | Tool | Idee | Aufwand |
|---|---|---|---|
| 12 | **Hundertertafel & Zahlenstrahl** | Zahlen markieren, einfärben, verdecken; Zahlenstrahl bis 20/100/1000 mit Sprüngen | mittel |
| 13 | **Uhr lernen** | Analoge Uhr mit ziehbaren Zeigern, Anzeige digital/in Worten („Viertel nach drei“), Übungsmodus | mittel |
| 14 | **Blitzrechnen** | Kopfrechenaufgaben (Einmaleins, Plus/Minus bis 20/100) groß und getaktet, Schwierigkeitsstufen | gering |
| 15 | **Lineatur-Tafel** | Leere Tafel mit Lineatur Klasse 1–4, Karo, Schreiben mit Stift/Finger, Bild speichern | mittel |
| 16 | **Silben & Buchstaben** | Wörter in Silbenbögen darstellen, Buchstabenkarten, eigene Bildwörter (keine fremden Anlauttabellen – urheberrechtlich geschützt, eigene Bilder nutzen) | mittel |
| 17 | **Stellenwerttafel** | H/Z/E mit Plättchen zum Legen und Bündeln | mittel |

---

## Übergreifende Verbesserungen

- **Raum-Profile:** Grenze und Empfindlichkeit hängen vom Raum und Mikrofon ab. Statt nur im Browser könnten sie pro Raum (vorhandene Raumverwaltung) gespeichert werden.
- **Tafel-PC ohne persönliche Anmeldung:** Für fest installierte Rechner ein gerätegebundener Zugang nur zu `/klassenraum` (vergleichbar mit dem Terminal-Token der Zeiterfassung), damit sich Lehrkräfte nicht an jedem Beamer-PC anmelden müssen.
- **Einzelrechte bei Bedarf:** Aktuell gilt ein Recht für alle Tools. Tools mit Schülerdaten (Phase 2) sollten zusätzlich die vorhandene `StudentPolicy` prüfen; ein eigenes Recht wie `use classroom tools with students` ist denkbar.
- **Mehrsprachigkeit:** Für DaZ-Klassen einfache Symbolsprache und optional zweite Sprache bei Texten.
- **Barrierefreiheit:** Hohe Kontraste, große Schrift, Tastaturbedienung und `prefers-reduced-motion` wie bei den bestehenden Tools beibehalten.

## Vorgeschlagene Reihenfolge

1. Stimmlautstärke-Skala und Lärmampel – ergänzen die Lautstärke-Tools direkt
2. Würfel/Zufall und Atemübung – schnell umgesetzt, täglich nutzbar
3. Namen ziehen und Gruppen bilden – größter Mehrwert durch vorhandene Klassenlisten
4. Arbeitsphasen-Timer
5. Morgenkreis, danach Tagesplan und Klassendienste
6. Lernwerkzeuge nach Rückmeldung aus dem Kollegium priorisieren

Vor Phase 2 lohnt eine kurze Rückmeldung aus dem Kollegium: Welche Tools werden schon genutzt (z. B. aus anderen Webseiten) und was fehlt im Alltag am meisten?
