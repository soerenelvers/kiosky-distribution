# Private Update-Distribution

Das Entwicklungsrepository `soerenelvers/kiosky` bleibt privat. Veröffentlichte
Pakete werden durch GitHub Actions in das separate private Repository
`soerenelvers/kiosky-distribution` übertragen. Dieses enthält ausschließlich
freigegebene Release-Stände:

- `latest.json` für die Update-Suche aller Plattformen;
- WordPress-, Standalone- und TYPO3-ZIPs unter `dist/`;
- den installierbaren TYPO3-Paketinhalt an der Repository-Wurzel;
- einen unveränderlichen Git-Tag pro Version.

Die Repository-Variable `KIOSKI_DISTRIBUTION_REPOSITORY` enthält
`soerenelvers/kiosky-distribution`. Das Secret
`KIOSKI_DISTRIBUTION_SSH_KEY` ist ein schreibberechtigter Deploy-Key, der nur
dieses Distributionsrepository erreichen kann.

`release.json` und `latest.json` enthalten Schema-Version, Kanal, Kompatibilität,
Anforderungen, HTTPS-Download-URLs und SHA-256-Werte. Aktuell ist `stable`
produktiv; `beta` ist für manuelle Vorab-Builds vorbereitet. Clients lehnen
unbekannte Schemata, falsche Produkte, inkompatible API-Versionen, Downgrades,
unsichere URLs und ungültige Hash-Formate ab.

WordPress und Composer verwenden je Kunde einen fein eingeschränkten GitHub-Token
mit ausschließlich lesendem Zugriff auf das Distributionsrepository. Ein
Kundentoken darf niemals Zugriff auf `soerenelvers/kiosky` erhalten.

## Optionaler selbst gehosteter Dienst

Unter `update-service/` liegt zusätzlich ein selbst hostbarer Update- und
Composer-Dienst mit Datenbank, Objektspeicher, gehashten Lizenzschlüsseln und
signierten Downloads. Er ist nicht Teil des produktiven GitHub-Updatepfads,
solange keine öffentlich erreichbare Hosting-Umgebung dafür konfiguriert ist.

Bei einem späteren Betrieb dieses Dienstes werden Kundenlizenzen lokal mit
geschützten Umgebungsvariablen erzeugt:

```sh
KIOSKI_UPDATE_BASE_URL=https://updates.example.test \
KIOSKI_UPDATE_ADMIN_TOKEN=... \
pnpm license:create -- "Kundenname"
```

Der ausgegebene Lizenzschlüssel wird nur einmal angezeigt. Der Dienst speichert
nur seinen SHA-256-Hash.

TYPO3 verwendet denselben Dienst als geschütztes Composer-Repository:

```sh
composer config repositories.kiosky composer https://updates.example.test/composer
composer config --auth http-basic.updates.example.test kiosky LIZENZSCHLUESSEL
composer require casesound/kiosky
```

Zugangsdaten gehören in Composer `auth.json` oder `COMPOSER_AUTH`, niemals in
`composer.json`. Der Composer-Endpunkt und die ZIP-Downloads prüfen den
Lizenzstatus serverseitig.

Die plattformspezifischen Manifest-Endpunkte sind:

- `/api/v1/standalone/latest`
- `/api/v1/wordpress/latest`
- `/api/v1/typo3/latest`

Eine Lizenz kann `standalone`, `wordpress`, `typo3` oder `all` freischalten.

Eine spätere Installationsregistrierung muss opt-in, anonymisiert und transparent
sein. Diese Version sendet keinerlei Installationsdaten.
