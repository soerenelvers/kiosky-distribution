## [3.2.1] - 2026-08-11

### Added

- WordPress-Administratoren können Stable-Updates zusätzlich zur nativen
  Pluginverwaltung direkt unter „Kiosky > Updates“ prüfen und installieren.
- Stable-Releases durchlaufen echte Upgrade-Tests von der vorherigen Version in
  WordPress sowie TYPO3 Classic und Composer für TYPO3 13 und 14.

### Changed

- TYPO3 prüft beim Öffnen des Kiosky-Backends automatisch auf neue Releases,
  sobald die letzte Prüfung älter als 24 Stunden ist.
- Die Release-Pipeline veröffentlicht Composer-Tag, Stable-Manifest und
  GitHub-Release erst nach erfolgreichen Paket- und Upgrade-Prüfungen.
- TYPO3 bietet One-Click-Updates nur an, wenn die exakte Release-Version im
  privaten Composer-Repository erreichbar ist, und stellt bei Fehlern auch den
  Vendor-Stand wieder her.

### Security

- WordPress verifiziert jedes Update-ZIP gegen die SHA-256-Prüfsumme des
  unveränderlichen Stable-Manifests.
- TYPO3 übergibt den privaten Lesetoken ausschließlich als `COMPOSER_AUTH` an
  die Composer-Kindprozesse; Zugangsdaten erscheinen nicht in der Kommandozeile.
