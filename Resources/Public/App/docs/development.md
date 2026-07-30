# Lokale Entwicklung

## Voraussetzungen

- Node.js 22.5 oder neuer
- pnpm 10 oder neuer

## Einrichtung

1. `pnpm install`
2. `.env.example` nach `.env` kopieren
3. optional für Maschinenzugriffe einen langen zufälligen `KIOSKY_ADMIN_API_KEY` setzen
4. für `KIOSKY_ENCRYPTION_KEY` mindestens 32 zufällige Zeichen setzen
5. nur die tatsächlich verwendete CrewBrain-Authentifizierungsvariante konfigurieren
6. für Einladungs-E-Mails optional `KIOSKY_PUBLIC_URL`, `KIOSKY_RESEND_API_KEY` und `KIOSKY_MAIL_FROM` konfigurieren
7. `pnpm db:migrate`
8. `pnpm dev`

Die Anwendung ist standardmäßig unter `http://127.0.0.1:8080` erreichbar. CrewBrain-Secrets bleiben serverseitig. `.env` und SQLite-Dateien sind von Git ausgeschlossen.

## Einladungs-E-Mails ohne SMTP

Kiosky sendet Einladungen direkt per HTTPS an die Resend-API. Ein eigener SMTP-Server und SMTP-Zugangsdaten sind nicht erforderlich.

1. Bei Resend ein Konto und einen API-Schlüssel mit ausschließlich Versandberechtigung anlegen.
2. Die Absenderdomain bei Resend verifizieren und die dort genannten DNS-Einträge setzen.
3. `KIOSKY_PUBLIC_URL` auf die von außen erreichbare Kiosky-Adresse setzen.
4. `KIOSKY_RESEND_API_KEY` und `KIOSKY_MAIL_FROM`, zum Beispiel `Kiosky <einladungen@example.org>`, setzen.
5. Kiosky neu starten.

Ohne API-Schlüssel bleibt die Einladung nutzbar und die Oberfläche zeigt einen Link zum manuellen Weitergeben. `KIOSKY_MAIL_TIMEOUT_MS` begrenzt optional die Wartezeit auf den Dienst und steht standardmäßig auf 10 Sekunden.

## Qualitätssicherung

- `pnpm lint` – strikte TypeScript-Prüfung
- `pnpm test` – Build und Tests
- `pnpm build` – Produktionsbuild
- `pnpm crewbrain:check-schema` – aktuelle OpenAPI gegen Snapshot prüfen, ohne Dateien zu überschreiben
- `pnpm crewbrain:generate-client` – Snapshot und Typen bewusst aktualisieren

Ein geändertes CrewBrain-Schema führt bei `crewbrain:check-schema` zu Exit-Code 2. Erst nach Prüfung wird die Client-Generierung manuell ausgeführt.

## Datenbank und Migrationen

Migrationen liegen nummeriert in `migrations/` und werden in einer Transaktion ausgeführt. Bereits protokollierte Migrationen werden nicht erneut ausgeführt. Lokale Daten liegen standardmäßig in `data/kiosky.sqlite`.

Vor Produktion sind regelmäßige konsistente Datenbank-Backups, Wiederherstellungstests, eine zentrale Secret-Verwaltung und der Wechsel auf eine für den Betrieb dimensionierte relationale Datenbank einzuplanen.

## CrewBrain

Die Demo-Instanz wird nur lesend verwendet. Die aktuelle Umsetzung ruft ausschließlich dokumentierte GET-Endpunkte auf. Ohne gültige, leseberechtigte Zugangsdaten kann die OpenAPI geprüft werden, aber kein Verbindungstest mit geschützten Daten erfolgen.
