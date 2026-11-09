# Eigene Schriften

Unter **Einstellungen → Schriften** können Administratoren bis zu 20 eigene
WOFF2-, WOFF-, TTF- oder OTF-Dateien registrieren (je höchstens 2 MB).
WOFF2 spart Übertragungsvolumen. Nur Schriften mit passenden Nutzungsrechten
hochladen. Die eingebauten Schriften Archivo und IBM Plex Mono werden lokal
mitgeliefert; ihre OFL-Lizenzen stehen unter `player/fonts/`.

Die Schriftauswahl steht bei allen Textelementen im Slide-/Vorlageneditor bereit,
auch bei Event-Details, Layout-Bausteinen, QR-Begleittext, Wegweisungszellen,
Uhr-Overlay und Warnungsdesigner. „Standard / vom Element“ erhält die bisherige
Darstellung. Nicht verfügbare Schriften verwenden eine Ersatzschrift; fehlende
Dateien blockieren die Wiedergabe nicht.

## Speicherung und Rechte

Alle Varianten bieten einen privaten Konfigurationsupload. WordPress und TYPO3
können zusätzlich bereits vorhandene Fonts im nativen Medienbrowser auswählen.
Dabei bleiben schon veröffentlichte native Medien ihren CMS-Regeln unterworfen;
dieser Import entzieht bestehenden Medien-URLs keine Rechte. Kiosky erweitert
keine globalen CMS-Upload-MIME-Freigaben.

Neue private Schriftbytes liegen außerhalb des Webroots bzw. im privaten
TYPO3-`var`-Verzeichnis, getrennt vom Plugin-/Extension-Updateordner:

- Standalone: `fonts/` neben der konfigurierten SQLite-Datei.
- TYPO3: `Environment::getVarPath()/kiosky/fonts`, sofern `var` außerhalb des
  Webroots liegt. Im Classic-Modus mit öffentlichem `typo3temp/var` wird
  stattdessen `private/kiosky-fonts-<Site-Hash>` neben dem Webroot verwendet;
  damit hängt der Schutz nicht von Apache-`.htaccess` ab.
- WordPress: standardmäßig `private/kiosky-fonts-<Site-Hash>` neben dem
  Document-Root. Für abweichende Hostingrechte in `wp-config.php` einen dauerhaften,
  vom PHP-Benutzer beschreibbaren Pfad **außerhalb des Webroots** setzen:

```php
define('KIOSKY_FONT_STORAGE_PATH', '/var/www/vhosts/example.org/private/kiosky-fonts');
```

Metadaten liegen im regulären Kiosky-Zustand, Binärdaten ausschließlich im
Schriftverzeichnis. Dateinamen sind SHA-256-Hashes; Format, Länge, Tabellenstruktur
und Inhalts-Hash werden geprüft. Dies ist keine vollständige Malwareprüfung.

Schriftverwaltung benötigt Administratorrechte. Lesen erfordert eine gültige
Benutzersitzung oder den gültigen Schlüssel des Displays **und** eine Schrift,
die dessen aktuell spielbarem Inhalt zugewiesen ist. Integrations-API-Tokens
erhalten dadurch keine neuen Rechte. Die API liefert keine Originalpfade,
CMS-Asset-IDs oder Zugangsdaten. Details: [Zugriffsvertrag](reviews/font-access-proposal.md).

## Offline, Löschen und Backup

Player laden nur benötigte eigene Schriften, prüfen deren Hash und speichern
höchstens 20 Dateien im lokalen Browsercache. Bereits heruntergeladene Inhalte
bleiben offline nutzbar; ein Schlüsselwiderruf löscht keine zuvor erlaubten
Offlinekopien. Eine neue oder fremde Schrift kann damit nicht heruntergeladen werden.

Referenzierte Schriften können nicht aus dem Katalog entfernt werden. Nach dem
Entfernen unbenutzter Einträge bleiben unveränderliche Dateien für Rollback und
Offlinebetrieb liegen. Wiederholter Austausch kann daher zusätzlichen Speicher
belegen. Speicherplatz überwachen; keine automatisierte Löschung unreferenzierter
Dateien ohne Abgleich mit gesicherten Inhaltsversionen durchführen.

Backups müssen Datenbank/CMS-Zustand, private Schriftdateien und die zugehörigen
Verschlüsselungsschlüssel gemeinsam wiederherstellen. Ein ZIP-Update enthält
keine kundeneigenen Schriftdateien. Inhaltsübertragungen zwischen Installationen
übernehmen aktuell Schrift-IDs, keine privaten Font-Binärdateien; die verwendete
Schrift auf dem Ziel separat registrieren (identische Bytes ergeben dieselbe ID).
