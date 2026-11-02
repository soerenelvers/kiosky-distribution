## [3.13.1] – 2026-11-02

- Zeitplaneinträge in allen drei Ausgaben erst beim Klick auf „Schließen“ speichern. Änderungen im Dialog bleiben bis dahin lokal; Fehler oder ungültige Zeiträume lassen den Dialog mit den Eingaben geöffnet. Mehrfachzuweisungen behalten vorhandene IDs und werden ohne parallele CMS-Schreibabfragen gespeichert.

- WordPress-Speicherfehler „Die Kiosky-Daten wurden parallel geändert“ bei gleichzeitigem Player-Betrieb beheben: Reine Heartbeats, Befehlszustellung und API-Nutzungszeitpunkte mit Inhalts-/Einstellungsänderungen zusammenführen, ohne die eigentliche API-Aktion erneut auszuführen. Aktuelle Statuswerte und gespeicherte Inhalte bleiben erhalten.
- Atomaren Datenbankvergleich, echte Konfigurationskonflikte, Änderungen/Widerrufe von API-Zugängen und die erneute Player-Autorisierung nach Schlüsselwechsel oder Löschen weiterhin erzwingen. Keine Datenmigration oder Änderung am WordPress-Speicherformat.
- Reproduktion mit echter zweiter MariaDB-Verbindung für Slide-Speichern/-Löschen, Einstellungen und ein Rennen direkt vor dem SQL-UPDATE ergänzen; zusätzlich große Bestände, Paketgrenzen und Zeichensätze prüfen.
