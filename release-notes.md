## [3.3.0] – 2026-10-14

- Element-Hintergründe können im gemeinsamen Editor ausdrücklich transparent gesetzt werden. Lauftexte bleiben sichtbar, darunterliegende Bilder, Videos und andere Inhalte scheinen durch; die Einstellung bleibt in Vorschau, Player, Vorlagen und Exporten erhalten.
- WordPress nutzt seine native Mediathek: vorhandene Anhänge stehen in Kiosky bereit, Uploads und Bildbearbeitungen werden als Anhänge mit CMS-Metadaten gespeichert. Medienordner werden als hierarchische Attachment-Taxonomie geführt.
- TYPO3 nutzt die File Abstraction Layer (FAL), öffentliche Dateispeicher und native Ordner. Kiosky-Metadaten einschließlich Videozuschnitt und Papierkorbstatus liegen am CMS-Dateidatensatz.
- Bestehende Kiosky-Medien werden beim ersten Zugriff mit Bearbeitungsberechtigung in die CMS-Medienverwaltung übernommen. Medien-IDs und URLs in Slides, Vorlagen und Einstellungen werden angepasst; der separate Medienkatalog wird danach aus dem Kiosky-State entfernt. Fehlgeschlagene Übernahmen behalten die Quelldaten für einen erneuten Versuch.
- CMS-Dateiberechtigungen werden berücksichtigt; bestehende Medienverweise werden beim Laden anhand ihrer CMS-ID aktualisiert. Uploads werden anhand des tatsächlichen Dateiinhalts geprüft, SVGs auf passive Inhalte beschränkt.
- Neue Migrationstests sowie Medien-Smoke-Tests für echte WordPress- und TYPO3-Installationen prüfen Upload, Bearbeitung, Papierkorb, Wiederherstellung und endgültiges Löschen.
