# WordPress-Updates

Das Release-ZIP enthält ein direkt installierbares Plugin mit dem obersten Ordner
`kiosky/`. Nach Installation und Aktivierung bindet sich Kiosky in die native WordPress-
Updateprüfung ein. Erfolgreiche Abfragen von `latest.json` werden sechs Stunden
zwischengespeichert; daraus entsteht kein eigener Sechs-Stunden-Zeitplan. Netzwerkfehler, Timeouts und ungültige Antworten werden ohne
PHP-Fatal-Error ignoriert.

Bei privaten Kiosky-Repositories wird ein fein eingeschränkter GitHub-Zugangstoken unter
**Einstellungen → Kiosky Update-Zugang** gespeichert. Alternativ kann er in
`wp-config.php` gesetzt werden:

```php
define('KIOSKY_UPDATE_ACCESS_TOKEN', 'github_pat_...');
```

Der Token benötigt nur Leserechte auf das private Repository
`soerenelvers/kiosky-distribution`; er erhält keinen Zugriff auf das
Entwicklungsrepository. Das Plugin sendet ihn ausschließlich an
`api.github.com`, um `latest.json` und das ausgewählte WordPress-ZIP abzurufen.
Der native WordPress-Upgrader wird für diese privaten Paket-URLs über
`upgrader_pre_download` angebunden.

Ein Update wird nur angeboten, wenn Version, Stable-Kanal, API-Version, PHP- und
WordPress-Anforderung kompatibel sind. WordPress zeigt es im normalen
Plugin-Update-Dialog und im Informationsfenster an. Die Installation erfolgt erst
nach Zustimmung; native automatische Plugin-Updates können Administratoren
optional in WordPress aktivieren. „Erneut prüfen“ auf der WordPress-Update-Seite
verwirft den Cache über die üblichen Transient-Hooks.

WordPress lädt das Paket über HTTPS. Kiosky prüft das heruntergeladene ZIP
über `upgrader_pre_download` gegen die SHA-256-Prüfsumme des Stable-Manifests,
bevor es zur Installation an den nativen Upgrader übergeben wird.
Bei abweichender Prüfsumme wird die Installation abgebrochen.

Optionale Installationsmeldungen oder Telemetrie sind nicht implementiert und
werden nicht versendet.

## Einrichtung und manuelle Prüfung

Die [vollständige Einrichtungsanleitung](setup-guide.md) beschreibt Fine-grained
GitHub-Tokens mit `Contents: Read-only`, die Aktualisierung eines bereits
installierten Plugins, WP-Cron und die Trennung von Update-Token und API-Schlüssel.

WordPress plant Prüfungen zweimal täglich. Ohne Seitenaufrufe kann WP-Cron später
ausgeführt werden. Unter **Kiosky → Updates → Nach Updates suchen** lässt sich
der Kiosky-Cache sofort umgehen. Alternativ **Dashboard → Aktualisierungen →
Erneut prüfen** wählen. Ein frei einstellbares Prüfintervall gibt es in der
normalen Oberfläche derzeit nicht. Automatische Installation ist separat pro
Plugin aktivierbar.

Für den einmaligen Wechsel alter Pluginordner und Updater siehe [Paketnamen und Migration](package-naming.md).

## Kiosky → Updates öffnet nur die Zugangseinstellungen

Bis einschließlich 3.11.0 verwenden die Kiosky-Updateübersicht und die native WordPress-Seite für den Update-Zugang denselben Seitenschlüssel `kiosky-updates`. Dadurch können die WordPress-Menüzuordnung und die Seiten-Callbacks kollidieren: „Kiosky → Updates“ zeigt dann das Formular für den Zugangstoken statt der Updateübersicht.

Ab 3.11.1 besitzt die Kiosky-Updateübersicht den eigenen Schlüssel `kiosky-update-center`. Die bisherige URL der Zugangseinstellungen und die gespeicherten Tokens bleiben erhalten. Die Seite heißt nun „Kiosky Update-Zugang“ und verlinkt für berechtigte Administratoren auf „Updates suchen und installieren“.

Bis zur Installation der Korrektur kann ein angemeldeter Administrator die Kiosky-Updateübersicht direkt über `<WordPress-Adresse>/wp-admin/admin-post.php?action=kiosky_app&view=updates` öffnen. Alternativ stehen die normale WordPress-Prüfung unter „Dashboard → Aktualisierungen → Erneut prüfen“ und die anschließende Installation unter „Plugins → Installierte Plugins“ zur Verfügung. Das Plugin muss dafür nicht deinstalliert werden. Eine erfolgreiche manuelle Prüfung erfordert weiterhin eine erreichbare, gültige und kompatible Updatequelle; eine Modullizenz oder ein Zugangstoken wird durch den Direktaufruf nicht ersetzt.
