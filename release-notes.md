## [3.12.1] – 2026-10-30

- WordPress-Speicherung bei gemischten Zeichensätzen der Optionstabelle korrigieren: Die native spaltenweise Validierung erlaubt gültige Unicode-Inhalte im Datenfeld, auch wenn Optionsnamen noch den älteren Zeichensatz verwenden. Dies behebt blockierte Änderungen an Slides und Einstellungen ohne Datenbankmigration oder Änderung des Speicherformats.
- Native WordPress-/MariaDB-Regressionen für Anlegen, Bearbeiten und Löschen von Slides, Einstellungen, Optionscache, konkurrierende Änderungen und echte Schreibfehler ergänzen; sowohl gemischte als auch durchgängig utf8mb4 kodierte Tabellen prüfen.
