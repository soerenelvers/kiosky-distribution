# WordPress-Updates

Das Release-ZIP enthält ein direkt installierbares Plugin mit dem obersten Ordner
`kioski/`. Nach Installation und Aktivierung prüft es höchstens alle sechs Stunden
`latest.json`. Netzwerkfehler, Timeouts und ungültige Antworten werden ohne
PHP-Fatal-Error ignoriert.

Bei privaten Kiosky-Repositories wird ein fein eingeschränkter GitHub-Zugangstoken unter
**Einstellungen → Kiosky Updates** gespeichert. Alternativ kann er in
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

WordPress lädt das Paket über HTTPS. Der SHA-256-Wert wird im Manifest
veröffentlicht. Die native WordPress-Upgrader-API bietet für externe Plugins
jedoch keinen stabilen Hook, der in allen unterstützten Versionen vor der
Installation eine eigene Hash-Prüfung garantiert; daher dient die Prüfsumme
aktuell der externen Verifikation und späteren Signaturerweiterung.

Optionale Installationsmeldungen oder Telemetrie sind nicht implementiert und
werden nicht versendet.
