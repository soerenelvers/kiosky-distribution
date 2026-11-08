## [3.32.0] – 2026-11-08

- In Presets zusätzliche Folge-Preset-Timer mit Uhrzeit am Auslösetag oder festem Datum und eigener Zeitzone vorbereiten; erst beim Aufrufen anlegen und mit bisherigen Kanal-, Ausspiel- und Zeitzuweisungen kombinieren.
- Dieselben Folgeaktionen für Oberfläche, API, Companion, geplante Presets und Warn-Presets ausführen; reine Timer-Presets unterstützen und Aktionen samt Timer-Erzeugung atomar speichern.
- Selbstaufrufe, indirekte Schleifen, fehlende/archivierte Ziele und überlange Ketten beim Speichern und Auslösen abweisen; automatisch erzeugte Einmal-Timer nach Verarbeitung aus der aktiven Liste entfernen und das Protokoll erhalten.
- Vorwärtsverweise beim Systemtransfer zwischen allen drei Varianten neu zuordnen und validieren; Hintergrundbetrieb und bisherige Preset-/Warnungsprioritäten erhalten.
- Tests für mehrstufige Ausführung, parallele Prozesse, Fehler-Rollback, API/Companion, native CMS-Persistenz und responsive Bearbeitung ergänzen.
