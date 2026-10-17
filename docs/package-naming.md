# Einheitliche Namen: Kiosky

Neue Builds und Releases verwenden ausschließlich diese kanonischen Namen:

| Ausgabe | ZIP-Datei | Ordner im ZIP |
| --- | --- | --- |
| Standalone | `kiosky-app-VERSION.zip` | `kiosky-app/` |
| WordPress | `kiosky-wordpress-VERSION.zip` | `kiosky/` |
| TYPO3 Classic | `kiosky_VERSION.zip` | `kiosky/` |

Das Composer-Paket bleibt `casesound/kiosky`, der TYPO3-Extension-Key bleibt
`kiosky`. Neue Release-Manifeste verwenden `product: "kiosky"`. Neue
Build-/Deployment-Variablen beginnen mit `KIOSKY_`.

## Bereits veröffentlichte Releases

Historische Tags, ZIP-Dateien, Prüfsummen und ihre Downloadadressen bleiben
unverändert. Alte Namen sind ausschließlich Eingabekompatibilität:
Die aktualisierten Updater akzeptieren vorhandene Manifeste, Paket-URLs und
Standalone-Archivordner aus früheren Releases. Der Update-Dienst leitet den
Objektnamen aus der im Manifest angegebenen Datei ab; alte Pakete bleiben abrufbar.
Bei Build- und Deployment-Einstellungen haben `KIOSKY_`-Variablen Vorrang;
bisherige `KIOSKI_`-Bindings werden als Übergang weiterhin gelesen.

## Einmaliger Wechsel von älteren Installationen

Updater früherer Releases prüfen den bisherigen Produktbezeichner strikt;
Standalone-Updater erwarten außerdem den bisherigen Archivordner.
Sie können das erste vollständig kanonische Paket deshalb nicht zuverlässig
selbst installieren. Der Wechsel muss einmal über den manuellen Deploymentweg
mit Sicherung erfolgen. Erst danach übernimmt der aktualisierte Updater
weitere Releases. Die bisherige Stable-Distribution wird durch die Änderung
am Quellcode nicht ersetzt; eine neue Version ist vor der Freigabe zu bauen.

### Standalone

Dienst stoppen und Datenbank, Medien, `.env` sowie Verschlüsselungsschlüssel
konsistent sichern. Das neue Paket getrennt entpacken und den Inhalt von
`kiosky-app/` über den vorgesehenen Deploymentweg bereitstellen. Bestehende
Daten und Konfiguration erhalten, Migrationen ausführen und den Dienst neu starten.
Healthcheck, Version und Player prüfen. Einen alten Anwendungsordner im
Dateisystem muss man dafür nicht umbenennen.

### WordPress

Die Datenbank und Uploads sichern. Prüfen, ob das bestehende Plugin bereits in
`wp-content/plugins/kiosky/` liegt. Ist das der Fall, kann das kanonische ZIP
über den normalen Ersetzungsweg installiert werden.

Bei einem früher abweichend benannten Pluginordner: Plugin deaktivieren,
**nicht deinstallieren**, und den vorhandenen Ordner einmalig in `kiosky`
umbenennen. Nur bei frei verfügbarem Zielpfad umbenennen; keine zweite Kopie
anlegen und keinen vorhandenen Ordner überschreiben. Danach das neue
WordPress-ZIP installieren beziehungsweise ersetzen und das Plugin erneut
aktivieren. Nicht gleichzeitig zwei Kiosky-Pluginordner aktivieren.

Menüs, Player, Inhalte, Medien und API prüfen. Die unveränderte Datenbank
und Uploads weiterverwenden. Der Release-Smoke-Test vollzieht diese
Ordnerumstellung explizit vor dem Upgrade nach.

### TYPO3

Composer-Paket und Extension-Key ändern sich nicht. Das erste kanonische
Release über Composer beziehungsweise das neue Classic-ZIP installieren,
anschließend `extension:setup --extension=kiosky` und `cache:flush` ausführen.
Bereits vorhandene Backend-Updater können einen manuellen Updateweg erfordern.

## Releaseprüfung

`pnpm build:platforms` erzeugt kanonische ZIPs. `pnpm release:build`
prüft Archivordner, Manifest-Produkt, URLs und Prüfsummen. Regressionsprüfungen
umfassen sowohl kanonische Pakete als auch historische Updateeingaben.
Keine bestehenden Tags erneut veröffentlichen oder alte Stable-Dateien
mit neu gebauten Inhalten derselben Versionsnummer überschreiben.
