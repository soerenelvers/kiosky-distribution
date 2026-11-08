# Aus einem Preset ein weiteres Preset planen (ab 3.32.0)

Unter **Presets & Warnhinweise → Preset anlegen/bearbeiten** den Bereich
**Weiteres Preset zeitgesteuert starten → ＋ Preset-Timer** verwenden.
Folge-Preset, Uhrzeit beziehungsweise Datum und Zeitzone auswählen und das
übergeordnete Preset speichern. Mehrere Folge-Timer sind möglich.

Beispiel für ein Preset „Vorstellung“:

1. Wie bisher einen Slide oder Kanal direkt zuweisen beziehungsweise eine
   Zeitzuweisung von 19:00 bis 21:20 vorbereiten.
2. Zusätzlich einen Preset-Timer auf „Zweiter Teil“ um 21:20 einstellen.
3. „Vorstellung“ aufrufen. Die bisherigen Aktionen werden ausgeführt und der
   einmalige Timer für „Zweiter Teil“ wird angelegt. Um 21:20 wird dessen
   vollständige gespeicherte Konfiguration ausgeführt.

**Preset speichern** speichert die Definition und startet noch keinen Timer.
Erst ein Aufruf des übergeordneten Presets legt seine Folge-Timer an. Das gilt
für den Aufruf in der Oberfläche, über die API, über Companion und über einen
[geplanten Preset-Termin](preset-triggers.md). Ein Preset kann ausschließlich
Folge-Timer enthalten oder sie mit Kanalzuständen, direkten Ausspielungen,
Zeitzuweisungen oder einem Warnhinweis kombinieren.

## Zeitbezug und Kontrolle

- **Uhrzeit am Auslösetag:** Die Uhrzeit gilt am tatsächlichen Aufruftag in der
  gewählten IANA-Zeitzone, z. B. Europe/Berlin. So kann dieselbe Definition an
  verschiedenen Veranstaltungstagen verwendet werden.
- **Festes Datum und Uhrzeit:** Ein konkreter zukünftiger Zeitpunkt, auch an
  einem anderen Tag. Beim Bearbeiten bleibt ein unveränderter Zeitpunkt erhalten.
- Ein Folge-Zeitpunkt muss beim Aufrufen des übergeordneten Presets noch in der
  Zukunft liegen. Vergangene Zeitpunkte werden nicht auf morgen verschoben.
  Bei einem ungültigen Timer wird der gesamte Aufruf verworfen, einschließlich
  seiner übrigen Aktionen; es entstehen keine Teiländerungen.

Die angelegten Timer erscheinen unter **Zeitplanung → Preset-Termine** mit dem
Namen „übergeordnetes Preset → Folge-Preset“. Dort lassen sie sich vor Ausführung
bearbeiten, pausieren oder entfernen. Änderungen an der Definition des
übergeordneten Presets ändern bereits angelegte Timer nicht. Die Aktionen des
Folge-Presets werden dagegen zum Auslösezeitpunkt aus dessen dann gespeicherter
Definition gelesen. Jeder bewusste erneute Aufruf des übergeordneten Presets
legt eigene Timer an.

Automatisch angelegte Folge-Timer verschwinden nach ihrer einmaligen Verarbeitung
aus der aktiven Terminliste. Ihr Ergebnis bleibt im Ausführungsprotokoll stehen,
auch bei Fehlern oder verspätet übersprungenen Terminen. So wächst die Liste nicht
bei jeder Veranstaltung weiter. Die Aufbewahrung und die Fünf-Minuten-Grenze für
verspätete Ausführungen entsprechen den normalen Preset-Terminen.

Ein Folge-Preset kann selbst weitere Timer anlegen. Selbstaufrufe und indirekte
Schleifen, z. B. A → B → A, werden beim Speichern und beim Aufrufen abgewiesen.
Es gelten maximal 100 Timer pro Preset, 32 Ebenen und 500 erreichbare Presets.
Für tägliche oder wöchentliche Wiederholung das übergeordnete Preset über die
normale Zeitplanung wiederkehrend auslösen, statt eine Schleife anzulegen.

Warnungen, bestehende Prioritäten und die Ablösung durch spätere manuelle oder
zeitgesteuerte Zuweisungen bleiben erhalten. Das Beenden eines Presets entfernt
seine bereits geplanten Timer ebenso wenig wie bereits erzeugte Zeitzuweisungen;
diese bei Bedarf in der Zeitplanung pausieren oder entfernen.

## Alle Plattformen und API

Standalone, WordPress und TYPO3 verwenden die vorhandene Preset-Termin-Routine.
Die [Hintergrund-Konfiguration](preset-triggers.md#hintergrundbetrieb) bleibt
unverändert. Der Editor muss zur späteren Ausführung nicht geöffnet sein.
Zeitplanung und Presets müssen freigeschaltet sein.

Die bestehende Companion-Aktion „Preset ausführen“ und
`POST /api/v1/presets/{id}/execute` funktionieren weiterhin. Ein neues
Companion-Importpaket ist für diese Erweiterung nicht erforderlich.
`execution.result.presetTimers` enthält die angelegten Termine einschließlich
IDs. Die interne Preset-Konfiguration ergänzt `config.presetTimers`:

```json
{
  "presetTimers": [{
    "presetId": "ID-DES-FOLGE-PRESETS",
    "timing": {
      "mode": "time",
      "startTime": "21:20:00",
      "timezone": "Europe/Berlin"
    }
  }]
}
```

Für feste Zeitpunkte `mode: "datetime"` und `startsAt` als ISO-Zeitpunkt mit
Zeitzonenoffset verwenden. Optional kann eine Timer-Aktion einen eigenen `name`
erhalten. Beim Systemtransfer werden auch Vorwärtsverweise zwischen Presets neu
zugeordnet und geprüft; bloßes Importieren der Definition legt keine Timer an.
Bereits geplante Timer werden weiterhin pausiert importiert. Vor einem Rollback
auf eine Version ohne Folge-Timer alle noch geplanten Folge-Aufrufe prüfen und
bei Bedarf pausieren.
