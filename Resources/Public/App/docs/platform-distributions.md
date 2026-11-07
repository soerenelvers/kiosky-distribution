# Kiosky-Plattformausgaben

Kiosky wird als ein Produkt mit gemeinsamen Oberflächen- und Domainquellen gepflegt.
`pnpm build:platforms` erzeugt aus demselben Commit und derselben Versionsnummer:

- `artifacts/kiosky-app-<version>.zip`
- `artifacts/kiosky-wordpress-<version>.zip`
- `artifacts/kiosky_<version>.zip`

## Laufzeiten

- **Standalone:** Node.js/TypeScript und SQLite.
- **WordPress:** PHP-Domainkern, WordPress REST API, WordPress-Benutzer und lokale
  Speicherung in der WordPress-Datenbank.
- **TYPO3:** derselbe PHP-Domainkern, TYPO3 Backend-/Frontend-Routen,
  TYPO3-Backendbenutzer und lokale Speicherung über Doctrine DBAL.

WordPress und TYPO3 benötigen keinen Kiosky-Standalone- oder Node.js-Server.
Bei eingerichteter zentraler Modul-Lizenzierung beziehen sie ihre signierten
Freigaben von der Standalone-Masterinstanz. Offlinefreigaben bleiben erhalten;
Einrichtung und Scheduler sind in [Lizenzverwaltung](licensing.md) beschrieben.

Der gemeinsame JavaScript-Player, Editor und API-Vertrag werden bei jedem Build in
alle Ausgaben übernommen. Der PHP-Domainkern unter `platforms/php-core` wird
automatisch in beide CMS-Pakete kopiert. Plattformspezifisch bleiben nur Routing,
Benutzeridentität und der jeweilige State Store.

## Installation

### WordPress

Das WordPress-ZIP über **Plugins → Installieren** hochladen und aktivieren. Kiosky
erscheint mit seinen Arbeitsbereichen als Untermenüs im WordPress-Administrationsmenü.
Die interne Kiosky-Seitenleiste sowie die separaten Bereiche für Medien- und
Benutzerverwaltung werden in WordPress ausgeblendet. Öffentliche Display-URLs
verwenden `/kiosky-player/`.

CrewBrain-Livetest, einmaliger Tokenabruf, Importvorschau, manueller Import und
der tägliche automatische Import laufen über WordPress-HTTP und WP-Cron. Neue externe Integrationen verwenden eigene Kiosky-API-Zugänge unter
**Schnittstellen → Externe API** und die Basisadresse
`/wp-json/kiosky/v1/control` ohne CMS-Anmeldung. Der bisherige Weg mit einem
Administrator und WordPress-Anwendungspasswort bleibt kompatibel.

### TYPO3

Das TYPO3-ZIP als Extension `kiosky` installieren und anschließend ausführen:

```sh
vendor/bin/typo3 extension:setup --extension=kiosky
```

Das Modul erscheint unter **Web → Kiosky**. Öffentliche Display-URLs verwenden
ebenfalls `/kiosky-player/`.

Für den automatischen CrewBrain-Import kann im TYPO3-Scheduler die Aufgabe
**Kiosky: automatischer CrewBrain-Import** eingerichtet werden. Alternativ steht
`vendor/bin/typo3 kiosky:crewbrain:sync` zur Verfügung.

Neue externe Integrationen verwenden eigene Kiosky-API-Zugänge unter
**Schnittstellen → Externe API** und `/kiosky-api/v1`. Bearer oder `X-API-Key`
authentifizieren ohne TYPO3-Backend-Anmeldung. Der bisherige Konfigurationsschlüssel
`externalApiKey` und Query-Einstieg bleiben kompatibel.

## Release

1. Version mit `node scripts/set-version.mjs <version>` zentral setzen und Changelog ergänzen.
2. `pnpm test` und `pnpm test:php` ausführen.
3. `pnpm build:platforms` ausführen.
4. PHP-Syntax der CMS-Pakete prüfen.
5. die drei ZIP-Dateien aus `artifacts/` ausliefern.

## Downloads über GitHub

Der Branch **main** enthält die gemeinsame aktuelle Quelle. Die CI baut und prüft die drei Plattform-Pakete. Stable-Pakete stehen dauerhaft unter **Releases**; die Distribution enthält zusätzlich `latest.json` und alle drei ZIPs. Ein Git-Server benötigt nach dem Pull einen Build und Prozessneustart, siehe [Plesk/Git-Bereitstellung](plesk.md).

Die automatische App-basierte Veröffentlichung bleibt bis zur getrennten Einrichtung deaktiviert. Für ausdrücklich beauftragte Veröffentlichungen gibt es **Kiosky owner-approved release** (`manual-release.yml`): manueller Start auf `main`, Modus `publish`, Bestätigung `PUBLISH KIOSKY`. Dieser prüft zunächst die erfolgreiche vollständige CI derselben SHA und verwendet danach den bestehenden Installations-/Upgrade-Test- und Veröffentlichungsablauf. Ein Tag-Push allein löst keine Veröffentlichung aus.

## CMS-Integration

Die fachlichen Digital-Signage-Funktionen laufen in beiden CMS-Ausgaben lokal:
Veranstaltungen, Slides, Kanäle, Displays und Gruppen, Standorte, Matrix-Displays,
Zeitpläne, Player, Warnhinweise, DWD, CrewBrain und externe
Steuerungszuweisungen. Anmeldung, Benutzerkonten und Medienverwaltung bleiben
bewusst Aufgabe des jeweiligen CMS und werden nicht als zweite Kiosky-Verwaltung
dupliziert.

## Einrichtung, CMS-Medien und Home Assistant

Die [Einrichtungsanleitung](setup-guide.md) führt durch Updates, transparente
Lauftexte, native CMS-Medien, externe API-Zugänge und die öffentliche
[HACS-Integration](https://github.com/soerenelvers/hacs-kiosky).
WordPress nutzt seine Mediathek, TYPO3 FAL; die Standalone-App behält ihre eigene
Medienbibliothek. Details zu API und Migration: [External Control API](external-api.md).

## Speisekarten

Alle drei Ausgaben enthalten Speisekarten-Slides und den geschützten Einstieg
`/speisekarten`. Rechte, Kartenzuweisung und CMS-Anmeldung beschreibt die
[Speisekarten-Anleitung](speisekarten.md).

### Native WordPress-Medienauswahl

Ab 3.13.0 öffnen die Bild-/Videoauswahl sowie Hintergrund-, Logo-, Wegweisungs-,
Veranstaltungs- und Werbebild-Auswahl den originalen WordPress-Medienbrowser im
übergeordneten Verwaltungsfenster. Vorhandene Dateien anderer Autoren können
ausgewählt werden, soweit die WordPress-Berechtigungen dies erlauben. Suche,
Vorschau und Upload stammen aus WordPress; neue Dateien landen direkt in dessen
Mediathek. Die Auswahl filtert die zum jeweiligen Element passenden Dateitypen.

Kiosky übernimmt die vorhandene Attachment-ID, URL und Metadaten einschließlich
vorhandener Videotrim-Werte über den bestehenden CMS-Adapter. Es entsteht keine
Kopie der ausgewählten Datei. Die WordPress-Verwaltungsseite ist für die Auswahl
erforderlich; eine separat geöffnete App-Iframe-URL meldet einen fehlenden Host.
Benötigt werden die vorhandenen Rechte `kiosky_edit` und `upload_files`; private
Dateien und Bearbeitungsrechte anderer Autoren bleiben geschützt.
### Native TYPO3-Dateiauswahl ab 3.23.0

Alle Medienauswahldialoge im Backendmodul öffnen den originalen TYPO3-Dateibrowser:
Bilder/Videos, Hintergründe, Logos, Wegweisung, Veranstaltungen, Werbung und
Bulk-Import. Suche, Ordner, Upload und Mehrfachauswahl stammen aus TYPO3.
„Auswahl importieren“ übernimmt markierte Dateien; Schließen ohne Auswahl bricht ab.
Der Browser öffnet sich im nativen TYPO3-Modaldialog; Pop-ups sind nicht
erforderlich.

Ausgewählte Dateien bleiben im FAL-Speicher und behalten ihre `fal:`-Kennung.
Dateimounts, Leserechte und Uploadberechtigungen gelten weiterhin; Kiosky prüft
Verfügbarkeit und Medientyp nochmals über den CMS-Adapter. Private Dateien werden
nicht für die öffentliche Ausspielung freigegeben. Das native Systemmodul
`filelist` ist deshalb eine ausdrückliche Extension-/Composer-Abhängigkeit.
Nur Standalone verwendet weiterhin den eigenen Kiosky-Medienbrowser.

### Bulk-Slide-Import (alle Plattformen)

Unter „Slides & Kanäle → Bulk-Slide-Import“ bis zu 100 Bilder oder Videos auswählen.
WordPress öffnet die native Mediathek, TYPO3 den nativen Dateibrowser mit
Mehrfachauswahl. Standalone verwendet die eigene Medienauswahl mit Checkboxen
und einem gemeinsamen Erstellen-Button. Die Auswahl bleibt beim Wechseln von Ordnern erhalten.

Pro Datei entsteht ein Entwurf mit dem Mediennamen, passender Grundausrichtung
und vollflächiger Darstellung ohne Beschnitt. Videotrim-Werte werden übernommen.
Der Import referenziert vorhandene Dateien, veröffentlicht keine Slides und
ändert keine Kanäle. Ein ungültiges oder nicht mehr verfügbares Medium verhindert
den gesamten Import. PDF-Seiten verwenden weiterhin den bestehenden PDF-Import.

`POST /api/slides/from-media` erwartet `{ "mediaAssetIds": ["…"] }` und liefert
`201 { "slides": […] }`. Erforderlich sind Bearbeitungsrechte sowie die Module
Medien und Slides/Kanäle. Doppelte IDs werden einmal übernommen.
