# Kiosky-Plattformausgaben

Kiosky wird als ein Produkt mit gemeinsamen Oberflächen- und Domainquellen gepflegt.
`pnpm build:platforms` erzeugt aus demselben Commit und derselben Versionsnummer:

- `artifacts/kiosky-standalone-<version>.zip`
- `artifacts/kiosky-wordpress-<version>.zip`
- `artifacts/kiosky-typo3-<version>.zip`

## Laufzeiten

- **Standalone:** Node.js/TypeScript und SQLite.
- **WordPress:** PHP-Domainkern, WordPress REST API, WordPress-Benutzer und lokale
  Speicherung in der WordPress-Datenbank.
- **TYPO3:** derselbe PHP-Domainkern, TYPO3 Backend-/Frontend-Routen,
  TYPO3-Backendbenutzer und lokale Speicherung über Doctrine DBAL.

WordPress und TYPO3 benötigen keinen Kiosky-Standalone- oder Node.js-Server.

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
der tägliche automatische Import laufen über WordPress-HTTP und WP-Cron. Für die
External Control API wird die WordPress REST API mit einem Administrator und
einem WordPress-Anwendungspasswort verwendet.

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

Für die External Control API wird in den Extension-Einstellungen ein langer
`externalApiKey` hinterlegt. Anfragen gehen an
`/kiosky-api/?path=/api/v1/...` und senden diesen Wert als Header `X-API-Key`.

## Release

1. Version in `package.json` setzen.
2. `pnpm test` und `pnpm test:php` ausführen.
3. `pnpm build:platforms` ausführen.
4. PHP-Syntax der CMS-Pakete prüfen.
5. die drei ZIP-Dateien aus `artifacts/` ausliefern.

## Downloads über GitHub

Bei jedem Push auf `dev` oder `main` baut die GitHub Action
**Installierbare Pakete** automatisch alle drei ZIP-Dateien. Sie stehen im
zugehörigen Workflow-Lauf unter **Actions → Installierbare Pakete → Artifacts**
für 90 Tage zum Download bereit.

Wird ein Versions-Tag wie `v1.2.0` gepusht, veröffentlicht die Action die ZIPs
zusätzlich dauerhaft unter **Releases**.

## CMS-Integration

Die fachlichen Digital-Signage-Funktionen laufen in beiden CMS-Ausgaben lokal:
Veranstaltungen, Slides, Kanäle, Displays und Gruppen, Standorte, Matrix-Displays,
Zeitpläne, Player, Warnhinweise, DWD, CrewBrain und externe
Steuerungszuweisungen. Anmeldung, Benutzerkonten und Medienverwaltung bleiben
bewusst Aufgabe des jeweiligen CMS und werden nicht als zweite Kiosky-Verwaltung
dupliziert.
