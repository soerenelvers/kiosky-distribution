# Kiosky unter Plesk betreiben

## Anwendungseinstellungen

- Node.js-Version: 24 LTS
- Paketmanager: npm
- Anwendungsmodus während Installation und Build: development
- Anwendungsstamm: Verzeichnis mit `package.json`
- Dokumentenstamm: Unterordner `public` im Anwendungsstamm
- Anwendungsstartdatei: `server.js`

## Erstinstallation

1. Den aktuellen Stand des Branches `dev` über die Plesk-Git-Erweiterung bereitstellen.
2. In den Node.js-Einstellungen den Anwendungsmodus vorübergehend auf `development` setzen.
3. „npm-Installation“ ausführen.
4. Unter „Node.js-Befehle ausführen“ das Skript `build` ausführen.
5. Den Anwendungsmodus auf `production` umstellen.
6. Die unten beschriebenen Umgebungsvariablen setzen.
7. Die Anwendung neu starten.

## Umgebungsvariablen

- `KIOSKY_BUILD_NUMBER` ist optional. Ohne Vorgabe erzeugt der Build automatisch eine eindeutige Nummer aus Version, Build-Zeitpunkt und Git-Commit.
- `KIOSKY_DATABASE_PATH=./data/kiosky.sqlite`
- `KIOSKY_ENCRYPTION_KEY`: ein eigener zufälliger Wert mit mindestens 32 Zeichen
- optional `KIOSKY_ADMIN_API_KEY`: nur für automatisierte Maschinenzugriffe
- `KIOSKY_PUBLIC_URL`: die öffentliche HTTPS-Adresse der Kiosky-Installation
- `KIOSKY_RESEND_API_KEY`: API-Schlüssel für den Einladungsversand ohne SMTP
- `KIOSKY_MAIL_FROM`: verifizierter Absender, zum Beispiel `Kiosky <einladungen@example.org>`

`PORT` und `KIOSKY_PORT` werden in Plesk nicht manuell gesetzt. Plesk stellt `PORT` selbst bereit. CrewBrain-Zugangsdaten werden nach der Ersteinrichtung im geschützten Kiosky-Administrationsbereich hinterlegt.

## Aktualisierung

1. In Plesk-Git „Jetzt bereitstellen“ ausführen.
2. Falls sich Abhängigkeiten geändert haben, „npm-Installation“ ausführen.
3. Das Skript `build` ausführen.
4. „App neu starten“ anklicken.

## Betriebslog

Kiosky schreibt bewusst keine hochfrequenten Wiedergabenachweise. Das von Plesk
erfasste stdout/stderr enthält stattdessen kompakte JSON-Zeilen für Serverstart
und -stopp, automatische Importe, fehlgeschlagene Requests, Anfragen ab zwei
Sekunden Laufzeit und unbehandelte Prozessfehler. Beispiel:

```json
{"timestamp":"2026-07-29T07:05:00.000Z","level":"warn","event":"http.request.slow","requestId":"…","method":"GET","path":"/api/operations","status":200,"durationMs":2450}
```

Die Einträge sind in Plesk unter „Websites & Domains → Protokolle“ sichtbar und
lassen sich nach `event`, `level`, `requestId` oder dem Integrationsnamen filtern.
