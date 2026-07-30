## [2.4.8] - 2026-07-30

### Added

- WordPress kann Aktualisierungen nach der Umstellung des Hauptrepositories
  über eine private, token-geschützte GitHub-Distribution beziehen.
- TYPO3 wird als eigenständig installierbares Composer-Paket im privaten
  Distributionsrepository veröffentlicht.
- Ein optional selbst hostbarer Update-Dienst verwaltet Lizenzschlüssel,
  Release-Metadaten und signierte Downloads.
- Urheberrechts- und Lizenzhinweise kennzeichnen Sören Elvers als Urheber und
  trennen den proprietären Kiosky-Kern von den GPL-kompatiblen CMS-Adaptern.

### Changed

- Der Release-Workflow veröffentlicht unveränderliche WordPress- und
  TYPO3-Pakete samt Prüfsummen in das private Distributionsrepository.
- Der easyjob-Verbindungstest prüft die ungefilterte Projektliste und meldet
  die Zahl der lesbaren Projekte; zusätzliche Antwortformen werden unterstützt.

### Fixed

- Netzwerk-, TLS- und ungültige JSON-Antworten der easyjob-Schnittstelle
  liefern präzisere Fehlermeldungen.

