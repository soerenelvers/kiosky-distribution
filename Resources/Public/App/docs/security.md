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

## Vorgeschlagene CI/CD-Sicherheitsgrenzen

Der [CI/CD-Entwurf](ci-cd.md) trennt lesende Tests und Builds von einer geprüften Kontrolllogik mit kurzlebigen App-Tokens. Auditfehler blockieren die Freigabe. Die einmalige Aktivierung und verbleibende Sicherheitsbefunde sind dort dokumentiert.

## Update-Service-Werkzeuge (3.3.8)

Der zusätzliche CI-Audit verwendet weiterhin die bestehende Schwelle `high`.
Nach der Aktualisierung sind im optionalen Update-Service keine hohen oder
kritischen Befunde mehr gemeldet. Acht mittlere Befunde bleiben in den Ketten
Drizzle/Esbuild und Vinext/Satori/fflate offen; sie werden nicht als behoben
bezeichnet. Der Dienst wird durch dieses Release nicht auf einem Server deployt.

Zwei explizite, durch Build und Lint geprüfte Overrides vermeiden den derzeit
ungepatchten `braces`-Pfad über `fast-glob`: Der CommonJS-Vite-Transform nutzt
`@chialab/vite-plugin-commonjs`, und das Next-Lint-Plugin ist auf 14.2.35 mit
Glob 10.5.0 festgelegt. Der aktuelle Next-/React-/TypeScript-Lint-Basissatz bleibt
erhalten; `@eslint/compat` bindet die klassischen Plugin-Regeln in ESLint 9 ein.
Die Worker-API bleibt unverändert. CI prüft zusätzlich den Produktionsbuild,
Lint und Verhalten der geschützten HTTP-Endpunkte. Diese Overrides bei einem
späteren Upstream-Fix erneut prüfen und möglichst entfernen.
