# Release- und Update-Sicherheit

- GitHub Actions besitzt nur `contents: write`.
- Releases benötigen einen SemVer-Tag und einen Commit, der nachweislich in
  `origin/main` enthalten ist.
- Alle Update-URLs müssen HTTPS verwenden.
- Pakete werden aus festen Quellverzeichnissen gebaut; Entwicklungsdateien,
  Secrets, Tests, Caches und Git-Metadaten werden ausgeschlossen und geprüft.
- SHA-256-Werte stehen in Manifest und `SHA256SUMS.txt`.
- WordPress verwendet `wp_safe_remote_get`, kurze Timeouts, begrenzte Redirects,
  Schema- und Kompatibilitätsprüfung sowie einen zeitlich begrenzten Cache.
- Ausfälle des Update-Dienstes beeinträchtigen die laufende Installation nicht.
- TYPO3-Aktualisierungen laufen nur explizit über Composer oder das konfigurierte
  Deployment-Skript. Es gibt keinen blinden SSH-Zugriff auf Kundenserver.
- Backup, Wartungsmodus, Health Check und Sollversion sind für verwaltete
  Deployments konfigurierbar. Ein automatischer Rollback wird ohne verlässlich
  getestete Infrastruktur nicht vorgetäuscht.

Kryptografische Signaturen sind als spätere Schema-Erweiterung vorgesehen.
