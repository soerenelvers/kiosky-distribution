# Zeitzuweisungen in Presets (ab 3.30.0)

Unter **Presets & Warnhinweise → Preset anlegen** oder beim Bearbeiten eines
Zustands-/Reset-Presets **＋ Zeitzuweisung** wählen. Pro Zeitfenster lassen sich
mehrere Displays und Gruppen sowie ein Slide oder Kanal auswählen. Weitere
Zeitfenster können andere Inhalte, Ziele oder Zeiträume haben.

- **Uhrzeit am Auslösetag:** Datum wird erst beim Tastendruck in der gewählten
  Zeitzone bestimmt. „Bis“ vor „Von“ bedeutet Ende am Folgetag. Ein bereits
  vergangenes Zeitfenster bleibt am Auslösetag; es wird nicht auf morgen verschoben.
- **Festes Datum und Uhrzeit:** Start und Ende mit Datum auswählen, auch über
  mehrere Tage. Das Ende muss nach dem Start liegen.
- Zeitzone (Standard Europe/Berlin), Priorität, Wiederholung und Intervall lassen
  sich pro Zeitfenster einstellen. Wöchentlich sind mehrere Wochentage wählbar;
  ohne Auswahl gilt der Wochentag des Starts. Wiederkehrende Zeitfenster müssen
  kürzer als 24 Stunden sein. Sommer-/Winterzeit folgt der angegebenen Zeitzone;
  nicht existierende Uhrzeiten werden abgewiesen.

**Preset speichern** speichert nur die Definition. Erst **Preset aufrufen**
veröffentlicht reguläre Einträge in der Zeitplanung. Jeder Aufruf erzeugt neue,
unabhängige Zeitplaneinträge und genau einen Ausführungsnachweis. Bei einem
ungültigen Ziel, Inhalt oder Zeitraum wird der gesamte Aufruf verworfen, auch
wenn das Preset zusätzlich Kanalzustände oder direkte Zuweisungen enthält.

Ein Preset kann ausschließlich Zeitzuweisungen enthalten oder diese mit seinen
bestehenden Aktionen kombinieren. Eine reine Zeitzuweisung erzeugt keine
unbegrenzte direkte Content-Zuweisung. Nach dem Zeitfenster wird der sonst gültige
Zeitplan oder der Standardkanal/-slide verwendet. Warnungen und die bestehende
Ablösung ausgelöster Presets durch spätere Zuweisungen bleiben erhalten.

Die erzeugten Einträge können im normalen Zeitplan bearbeitet oder gelöscht
werden. Spätere Änderungen oder das Löschen der Preset-Definition verändern
bereits erzeugte Einträge nicht. `presets/stop` beendet direkte Preset-Zuweisungen,
aber löscht keine Zeitplanung; dafür den Zeitplan oder `/schedules/{id}` verwenden.
Das Zeitplanmodul muss für die Auslösung freigeschaltet sein.

## API und Companion

Die vorhandene Companion-Aktion zum Aufrufen eines gespeicherten Presets sowie
`POST /api/v1/presets/{id}/execute` mit `{}` führen dieselben Aktionen aus wie die
Oberfläche. Die Rückgabe `execution.result.schedules` enthält die erzeugten
Zeitplaneinträge mit ihren IDs. Ein Modulwechsel oder neues Companion-Importpaket
ist hierfür nicht erforderlich.

Interne Preset-Erstellung/-Bearbeitung verwendet `config.schedules`, eine Liste
von höchstens 500 Aufträgen entsprechend dem bestehenden
[Zeitplan-API-Vertrag](external-api.md#zeitplanung-über-api-und-companion-ab-3220).
Pro Auftrag ein Ziel; die Mehrfachauswahl der Oberfläche wird darauf aufgeteilt:

```json
{
  "name": "Pause Foyer",
  "type": "channel",
  "status": "active",
  "config": {
    "schedules": [{
      "target": {"type": "group", "id": "GRUPPEN-ID"},
      "content": {"type": "slide", "id": "SLIDE-ID"},
      "timing": {
        "mode": "time",
        "startTime": "21:00",
        "endTime": "21:20",
        "timezone": "Europe/Berlin",
        "recurrence": "once"
      },
      "priority": "normal"
    }]
  }
}
```

Speicherung, Auslösung, Systemexport/-import und Oberfläche verwenden denselben
Vertrag in Standalone, WordPress und TYPO3. Es gibt keine neue Datenbankmigration
und keine zusätzliche Abfrage im Player: Er verwendet die normale Zeitplanung.
