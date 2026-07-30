## [3.0.0] - 2026-07-31

### Added

- Ein integrierter Update-Manager prüft neue Versionen und unterstützt
  Standalone-, TYPO3- und WordPress-Installationen mit Release Notes,
  Benachrichtigungen und abgesicherten Aktualisierungsabläufen.
- Der neue portable Import und Export überträgt wählbare Einstellungen und
  Inhalte kreuzweise zwischen Standalone, TYPO3 und WordPress.
- Transferpakete umfassen Veranstaltungen und Abläufe, Slides samt
  Versionshistorie, Vorlagen und Medien, Kanäle, Displays, Standorte, Gruppen,
  Matrizen, Zeitpläne, Zuweisungen, Presets, Warnungen, Benutzerprofile und
  Schnittstellendefinitionen.
- Exportauswahl und Importvorschau erklären Abhängigkeiten und
  Sicherheitsfolgen vor der Ausführung.

### Changed

- Standalone-Importe werden atomar ausgeführt und bei einem Fehler vollständig
  zurückgerollt.
- Technische IDs und sichere Zugangsschlüssel werden auf der Zielinstanz neu
  erzeugt, damit Transferpakete zwischen unabhängigen Installationen
  eingesetzt werden können.

### Security

- Passwörter, Sitzungen, API-Schlüssel, Player-Schlüssel und
  Schnittstellen-Zugangsdaten werden nicht in Transferpakete aufgenommen.
- Importierte Benutzerprofile bleiben bis zur sicheren Einrichtung
  deaktiviert; Integrationen werden ohne Zugangsdaten übernommen.
