## [3.3.8] – 2026-10-18

- Standalone-Git-Installationen erhalten ohne eigene Konfiguration das stabile GitHub-Update-Manifest; explizite Einstellungen und Paket-Metadaten behalten Vorrang.
- Neue Befehle `deploy:standalone` und `verify:standalone` bauen nach Git-Pull reproduzierbar und erkennen alte Versionen, Commits, Frontend-Dateien sowie fehlende Server-Builds. Dokumentation verwendet main und verlangt Build plus Prozessneustart.
- Manueller Eigentümer-Release mit bestehender Distribution ergänzt: Die exakte main-SHA muss zunächst vollständige CI einschließlich Sicherheitsprüfung bestehen; Pakete und reale CMS-Upgrades werden vor Veröffentlichung geprüft.
- Sicherheitsabhängigkeiten des optionalen Update-Dienstes aktualisiert; dessen Build und Tests werden zusätzlich in der CI geprüft.
