# Releases

Kiosky verwendet eine gemeinsame Semantic-Version aus `VERSION`. `package.json`,
WordPress, TYPO3, Dateinamen, Manifest und Git-Tag werden vor jedem Release
gegeneinander geprüft.

## Release vorbereiten

```sh
./scripts/set-version.sh 2.3.0
./scripts/build-release.sh 2.3.0
git add .
git commit -m "chore: prepare release 2.3.0"
git push origin main
git tag v2.3.0
git push origin v2.3.0
```

Ein Tag veröffentlicht nur dann, wenn sein Commit in `origin/main` enthalten ist.
Tags auf `dev`, `develop` oder Feature-Branches brechen vor der Veröffentlichung
ab. Branch-Pushes bauen keinen Release. Der lokale Build erstellt selbst weder Tag
noch GitHub Release.

Der Workflow erzeugt `kioski-app-VERSION.zip`, `kioski-wordpress-VERSION.zip`,
`kioski_VERSION.zip`, `release.json`, `latest.json`,
`SHA256SUMS.txt` und Release Notes. `workflow_dispatch` kann als Dry Run oder
Build-only verwendet werden.

## Rollback

Vorherige Assets bleiben in ihrem GitHub Release erhalten. Standalone wird aus
dem vorherigen ZIP wiederhergestellt, WordPress über das vorherige Plugin-ZIP,
TYPO3 durch eine explizite Composer-Versionsanforderung. Datenbank-Backups müssen
vor einem Downgrade separat angelegt und getestet werden.

## Dokumentation und nachträgliche Ergänzungen

Zu jedem Release die Einrichtung und Grenzen neuer Funktionen im Changelog und
in [setup-guide.md](setup-guide.md) dokumentieren; das gesamte `docs/`-Verzeichnis
wird in die gemeinsame Oberfläche und damit alle Plattformpakete übernommen.
Die HACS-Integration besitzt eine eigene Version und ein eigenes öffentliches
Repository; Kompatibilität und Installationslink müssen ausdrücklich genannt werden.

Nachträgliche Erläuterungen können die GitHub-Releasebeschreibung, öffentliche
Anleitungen und `release-notes.md` auf dem aktuellen Distributionszweig ergänzen.
Im veränderlichen `main/latest.json` dürfen Release-Text und Dokumentationslinks
nachgeführt werden; seine aktuelle Prüfsumme in `SHA256SUMS.txt` mitführen.
Bereits veröffentlichte Tags, Paket-ZIPs, Paket-Prüfsummen und versionierte
`release.json` bleiben unverändert. Keine neue Programmversion nur für einen
Dokumentationsnachtrag behaupten.
