# Kiosky unter Plesk betreiben

## Anwendungseinstellungen

- Node.js-Version: 24 LTS
- Paketmanager: npm
- Anwendungsmodus während Installation und Build: development
- Anwendungsstamm: Verzeichnis mit `package.json`
- Dokumentenstamm: Unterordner `public` im Anwendungsstamm
- Anwendungsstartdatei: `server.js`

## Erstinstallation

1. Den aktuellen Stand des Branches `main` über die Plesk-Git-Erweiterung bereitstellen.
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

## Aktualisierung aus Git

1. Repository `soerenelvers/kiosky`, Branch **main**, in Plesk auswählen; Änderungen abrufen und „Jetzt bereitstellen“ ausführen.
2. Abhängigkeiten einschließlich Build-Werkzeugen installieren. Bei Plesk/npm dafür vorübergehend den Modus `development` verwenden, „npm-Installation“ und danach das Skript `build` ausführen. Anschließend mit dem Skript `verify:standalone` den Build prüfen.
3. Alternativ im Anwendungsstamm mit Node.js 24 und pnpm 11.9.0 ausführen:

   ```sh
   git pull --ff-only origin main
   pnpm deploy:standalone
   ```

4. Plesk wieder auf `production` setzen und **App neu starten** anklicken. Bei systemd oder einem anderen Prozessmanager den tatsächlich zuständigen Kiosky-Prozess neu starten.
5. `https://DEINE-KIOSKY-DOMAIN/api/health` aufrufen: `version` muss der gewünschten Version laut Datei `VERSION` entsprechen. `buildCommit` muss zum bereitgestellten Commit passen. Die Sidebar zeigt dieselbe Build-Nummer.

Ein Git-Pull allein verändert die laufende Anwendung nicht: Der Node-Prozess und die generierten Dateien in `dist/` und `public/` bleiben bis zu Build und Neustart alt. Diese erzeugten Verzeichnisse werden bewusst nicht in Git eingecheckt. `pnpm verify:standalone` erkennt eine abweichende Version, einen alten Commit, veraltetes HTML/JavaScript/CSS oder einen fehlenden kompilierten Server.

`.env`, Datenbank und Medien beibehalten; keine Produktionsdaten durch Dateien aus einer Entwicklungsinstallation ersetzen.

## Update-Suche in einer Git-Installation

Git-Checkouts verwenden ab 3.3.8 ohne eigene Konfiguration dasselbe stabile GitHub-Manifest wie die Standalone-Pakete:

```text
https://api.github.com/repos/soerenelvers/kiosky-distribution/contents/latest.json?ref=main
```

Eine explizite `KIOSKY_UPDATE_MANIFEST_URL` hat weiterhin Vorrang, danach folgt die Paket-Metadatei. Alte Beispieladressen wie `https://updates.example.org/...` aus der eigenen `.env` oder den Plesk-Umgebungsvariablen entfernen oder durch die tatsächliche Manifest-Adresse ersetzen. Ein eigenes Update-Hosting ist für den GitHub-Pfad nicht erforderlich. Bei einer privaten Quelle ist zusätzlich ein fein eingeschränkter Read-only-Token in `KIOSKY_UPDATE_ACCESS_TOKEN` erforderlich.

Für Git-Bereitstellungen weiterhin Git-Pull, Build und Neustart verwenden. Ein Release in GitHub aktualisiert keinen laufenden Server automatisch. Die integrierte Paketinstallation und eine Bereitstellung aus Git sind unterschiedliche Update-Wege.

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
