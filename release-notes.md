## [3.12.2] – 2026-10-30

- WordPress-Schreibabfragen verkleinern: Den serialisierten Datenbestand nur einmal über einen ASCII-Transport übertragen und den vorherigen Zustand atomar mit SHA-256 vergleichen. Dies behebt den reproduzierten Ausfall bei 700 KB Bestand und 1 MB Datenbank-Paketlimit; Speicherformat und spaltenweise Zeichensatzprüfung bleiben erhalten.
- Datenbank-Paketgrenzen vor dem Schreiben prüfen und sichere Fehlerkennungen für Paketlimit, Rechte, Verbindungsfehler, Zeichensatz/Feldgröße, Speicherplatz sowie beschädigte Daten anzeigen. Fehlgeschlagene Lesezugriffe und unlesbare Bestände dürfen nicht als leere Datenbasis überschrieben werden. SQL-Inhalte und Zugangsdaten erscheinen nicht in den öffentlichen Fehlermeldungen.
- Native WordPress-Regressionen um große Bestände, Paketgrenzen, ungültige Textdaten, tatsächliche konkurrierende Schreibzugriffe mit zwei Verbindungen und Schutz unlesbarer Daten erweitern; vertrauliche Inhalte in Fehlermeldungen separat prüfen.
