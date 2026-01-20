# Krankmeldungen - Implementierte Features

## Übersicht der Änderungen

### 1. Filter- und Sortierfunktionen

#### Abwesenheitsliste (oberer Bereich)
- **Filter nach Abwesenheitsgrund**: Dropdown mit allen vorhandenen Gründen
- **Filter nach Mitarbeiter**: Dropdown mit allen Mitarbeitern, die Krankmeldungen haben
- **Filter nach Krankenschein-Status**:
  - Mit Schein (Krankenschein vorhanden)
  - Ohne Schein (nicht benötigt)
  - Schein fehlt (benötigt, aber nicht vorhanden)

#### Sortierung der Abwesenheitsliste
- **Nach Grund** (auf-/absteigend)
- **Nach Von-Datum** (auf-/absteigend)
- **Nach Bis-Datum** (auf-/absteigend)
- **Nach Dauer** (auf-/absteigend)

#### Mitarbeiter-Übersicht (unterer Bereich)
- **Filter nach Mindestanzahl Tage**:
  - Min. Tage mit Schein
  - Min. Tage ohne Schein
  - Min. Tage fehlt Schein

#### Sortierung der Mitarbeiter-Übersicht
- **Nach Mitarbeitername** (auf-/absteigend)
- **Nach Tage mit Schein** (auf-/absteigend)
- **Nach Tage ohne Schein** (auf-/absteigend)
- **Nach Tage fehlt Schein** (auf-/absteigend)

### 2. Excel-Export-Funktionen

#### Gesamt-Export
- **URL**: `/sick_notes/export`
- **Button**: "Excel Export (Gesamt)" in der Kopfzeile
- **Inhalt**: 
  - Sheet 1: Alle gefilterten Krankmeldungen
  - Sheet 2: Mitarbeiter-Übersicht mit Zusammenfassung

#### Mitarbeiter-Export
- **URL**: `/sick_notes/export/user/{user_id}`
- **Button**: Excel-Icon in jeder Zeile der Mitarbeiter-Übersicht
- **Inhalt**: Alle Krankmeldungen des jeweiligen Mitarbeiters

### 3. Settings-Funktionalität

Die Anwendung verwendet nun eine hybride Konfiguration:
1. **Zuerst** wird in den Settings (Datenbank) geschaut
2. **Falls nicht vorhanden**, wird auf die config-Datei zurückgegriffen

#### Neue Settings
- `absence_reason_default`: Standard-Abwesenheitsgrund (Default: "krank")
- `absence_sick_note_days`: Tage bis Krankenschein benötigt (Default: 3)
- `absence_sick_note_reasons`: Gründe für Krankenschein, getrennt mit | (Default: "krank|Kind krank")

Diese können in der Admin-Oberfläche unter "Einstellungen" → "Abwesenheiten" angepasst werden.

### 4. Behobene Probleme

#### "Ohne Schein" zeigt 0 an
**Problem**: Die Spalte "ohne Schein" zeigte bei allen Mitarbeitern 0 an, obwohl Einträge vorhanden waren.

**Ursache**: Die Berechnung verwendete `settings('absence_sick_note_days', 'absences')`, was bei fehlender Datenbank-Einstellung `null` zurückgab.

**Lösung**: Fallback auf Config-Wert implementiert:
```php
$sickNoteDaysThreshold = settings('absence_sick_note_days', 'absences') ?? config('absences.absence_sick_note_days');
```

#### Undefined variable $allReasons
**Problem**: Fehler beim Laden der View wegen fehlender Variable.

**Lösung**: Die Variable wird jetzt korrekt im Controller geladen und an die View übergeben.

#### Call to undefined method User::absences()
**Problem**: Das Relationship `absences()` war im User-Model nicht korrekt implementiert.

**Lösung**: Das Relationship war bereits vorhanden, wird jetzt aber korrekt verwendet:
```php
public function absences(){
    return $this->hasMany(Absence::class, 'users_id');
}
```

#### SQL-Fehler in der Query
**Problem**: Die ursprüngliche SQL-Query hatte fehlende Anführungszeichen:
```sql
where (`reason` in (krank, Kind krank) or `sick_note_required` = 1)
```

**Lösung**: Laravel's Query Builder verwendet automatisch Prepared Statements:
```php
$query->whereIn('reason', config('absences.absence_sick_note'))
    ->orWhere('sick_note_required', 1);
```

### 5. Geänderte Dateien

1. **config/absences.php**: Korrektur des ENV-Variable-Namens
2. **database/migrations/2026_01_20_100000_add_absence_settings.php**: Neue Migration für Settings
3. **app/Http/Controllers/AbsenceController.php**: 
   - Filter- und Sortierlogik erweitert
   - Export-Methoden hinzugefügt
   - Fallback auf Config implementiert
4. **resources/views/absences/sicknotes.blade.php**: 
   - Filter-Formular hinzugefügt
   - Sortier-Links in Tabellenkopf
   - Export-Buttons
5. **app/Exports/SickNotesExport.php**: Fallback auf Config
6. **app/Exports/SickNotesByUserExport.php**: Fallback auf Config
7. **routes/web.php**: Export-Routen (bereits vorhanden)

### 6. Verwendung

#### Filter setzen
1. Wählen Sie die gewünschten Filter aus den Dropdowns
2. Klicken Sie auf "Filtern"
3. Mit "Zurücksetzen" werden alle Filter entfernt

#### Sortieren
Klicken Sie auf die Spaltenüberschriften in der Tabelle. Ein erneuter Klick kehrt die Sortierrichtung um.

#### Excel exportieren
- **Gesamt-Export**: Klicken Sie auf "Excel Export (Gesamt)" in der Kopfzeile
- **Mitarbeiter-Export**: Klicken Sie auf das Excel-Icon in der Zeile des jeweiligen Mitarbeiters

Die Exports berücksichtigen automatisch alle aktiven Filter.

### 7. Berechtigungen

Alle Funktionen erfordern die Berechtigung `manage sick_notes`.

## Technische Details

### Query-Optimierung
- Die Abwesenheitsliste wird einmalig aus der Datenbank geladen
- Filter und Sortierung werden auf die Collection angewendet
- Bei vielen Datensätzen sollte die Filterung in die Query verschoben werden

### Cache
Die Settings werden für 60 Sekunden gecacht. Nach Änderungen in den Einstellungen sollte der Cache geleert werden:
```bash
php artisan cache:clear
```
