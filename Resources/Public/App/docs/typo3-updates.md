# TYPO3-Updates

Das TYPO3-ZIP `kioski_VERSION.zip` ist eine direkt im Classic-Mode-Extension-Manager
installierbare Extension `kioski` und unterstützt TYPO3 13.4 oder 14 mit
PHP 8.2. Das Composer-Paket heißt `casesound/kiosky`.

Nach Konfiguration des privaten Kiosky-Distributionsrepositories:

```sh
composer config repositories.kiosky vcs https://github.com/soerenelvers/kiosky-distribution.git
composer config --global --auth github-oauth.github.com GITHUB_TOKEN
composer require casesound/kiosky
composer update casesound/kiosky --with-dependencies
```

Der fein eingeschränkte Token benötigt nur Leserechte auf
`soerenelvers/kiosky-distribution`. Er wird in Composer `auth.json`
beziehungsweise `COMPOSER_AUTH`, nicht im Projekt-Repository gespeichert.
Jeder unveränderliche Release-Tag enthält ein vollständiges installierbares
TYPO3-Paket; Composer erkennt die Tags als Paketversionen.

Im Kiosky-Modul unter **Updates** wird eine neue Version erst installierbar, wenn
Composer genau den im Stable-Manifest genannten Tag auflösen kann. Nach dem Update
prüft Kiosky die tatsächlich installierte Version. Bei einem Fehler werden
`composer.json`, `composer.lock` und der Vendor-Stand automatisch zurückgesetzt.
Der in der Extension-Konfiguration gesetzte `updateAccessToken` wird dem
Composer-Kindprozess als `COMPOSER_AUTH` bereitgestellt. Dadurch funktioniert der
Backend-Updateweg auch dann, wenn der Webserver-Benutzer nicht das globale
Composer-Home des Administrators verwendet.

Für verwaltete Installationen steht `scripts/deploy-typo3-update.sh` bereit. Es
verlangt `KIOSKI_TYPO3_PROJECT_DIR`, protokolliert die Version, unterstützt
optionale Backup- und Wartungsbefehle, führt Composer und TYPO3 `extension:setup`
sowie `cache:flush` aus, prüft die erwartete Version und optional einen Health
Check. Automatische Ausführung muss je Server ausdrücklich eingerichtet werden.

Plesk Scheduled Task:

```sh
KIOSKI_TYPO3_PROJECT_DIR=/absoluter/projektpfad \
PHP_BIN=/opt/plesk/php/8.2/bin/php \
COMPOSER_BIN=/usr/lib/plesk-9.0/composer.phar \
/absoluter/kioski-pfad/scripts/deploy-typo3-update.sh
```

Pfad, Benutzer und Dateirechte müssen zur konkreten Subscription passen.
Zugangsdaten gehören ausschließlich in geschützte Umgebungsvariablen.
