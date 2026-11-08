## [3.31.0] – 2026-11-08

- Vollständige Presets in der Zeitplanung einmalig, täglich, wöchentlich oder im Intervall auslösen; mehrere Termine ermöglichen zeitgesteuerte Folgeaktionen mit den gespeicherten Preset-Zielen.
- Terminverwaltung mit Zeitzone, Wiederholungsende, Wochentagen, Aktivieren/Pausieren und Ausführungsprotokoll; responsive Dialoge und Preset-Uhr-Schaltfläche ergänzen.
- Ausführungen mit Aktionen atomar speichern und pro Termin/Zeitpunkt gegen parallele Zugriffe und Neustarts absichern; Fehler ohne Teiländerungen protokollieren und mehr als fünf Minuten verpasste Termine überspringen.
- Standalone-Taktgeber, separate minütliche WordPress-Cron-Aufgabe und TYPO3-Scheduler/CLI bereitstellen; laufende CMS-Player prüfen zusätzlich auf fällige Termine. Hintergrundaufgaben lesen keine komplette Mediathek ein.
- Terminübernahme beim Systemtransfer mit neuen Preset-Verweisen und pausiertem Start; begrenzte kompakte Protokolle, Rollen-/Lizenzprüfungen und Zeitzonen-/Sommerzeit-Parität in allen drei Varianten sichern.
- Anwendungstests, echte parallele SQLite-Prozesse, CMS-Speicherkonflikte, native CMS-Installations-/Upgradeprüfungen sowie Desktop-/Mobilbrowser-Abläufe ergänzen.
