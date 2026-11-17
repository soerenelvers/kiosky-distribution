# Update-Dienst: Sicherheitsblocker und Korrektur (3.47.0)

## Bestätigter Befund

Der npm-Audit des optionalen Update-Dienstes blockierte die Freigabe von 3.47.0.
GHSA-fc5q-39xc-vjpf (CVE-2026-112366, CVSS 7.5) betrifft picomatch bis einschließlich
4.0.7. Der am 17. November 2026 aktualisierte Hinweis nennt keine korrigierte
Version; 4.0.7 ist weiterhin die aktuelle npm-Version. Acht hohe Audit-Einträge
hingen an dieser transitiven Abhängigkeit der Framework-Werkzeuge. Die
Hauptanwendung bestand ihren separaten pnpm-Audit.

Ein Override auf dieselbe betroffene Version löst das Problem nicht. Ein
Downgrade des Frameworks, eine Audit-Ausnahme oder ein lediglich umbenannter
ungeprüfter Fork wurde nicht verwendet.

## Korrektur und erhaltener Vertrag

Der Dienst benötigt nur eine statische Statusseite und seine vorhandene
Worker-API. Der Next-/Vite-/Vinext-Starter wurde deshalb entfernt. Die bestehende
React-Seite wird einmalig beim Build gerendert; es gibt keinen Browser-JavaScript-
Payload, keine externen Schriftabrufe und keinen Bildproxy. Der kompilierte
Worker enthält nur die beiden eigenen Worker-Quelldateien und keine externen
Laufzeitimporte. Die Statusseite wird ohne Datenbankzugriff mit CSP ausgeliefert.

Lizenzprüfung, Admin-Authentifizierung, Hashes, signierte Downloads und die
API-Implementierung in `worker/update-service.ts` bleiben unverändert. Der
Einstieg routet zusätzlich den bereits implementierten Release-Notiz-Endpunkt
korrekt. Hosting-Metadaten, ASSETS-/DB-/RELEASES-Bindungen und SQL-Migrationen
bleiben im Build vorhanden. Der Drizzle-Schemagenerator funktioniert mit dem
aktuellen esbuild; das vorhandene Schema erzeugt keine neue Migration.

Der npm-Abhängigkeitsgraph wurde auf 112 Einträge einschließlich optionaler
Plattformpakete reduziert. npm audit meldet nach dem sauberen Installieren
**0 bekannte Schwachstellen**, einschließlich Entwicklungsabhängigkeiten.
Die bestehende GitHub-Sicherheitsprüfung und ihre Schwelle bleiben unverändert.

## Prüfungen

- Sauberes `npm ci`, TypeScript-Prüfung und sieben Update-Dienst-Tests.
- Tests des tatsächlich gebauten Workers: Status/CSP/HEAD, Paketmetadaten,
  geschützte Publikation, drei lizenzierte Manifeste, Composer, korrekte und
  manipulierte Downloads, Lizenzwiderruf und Release-Notizen.
- Wrangler-Deployment-Probelauf ohne Veröffentlichung: rund 6,4 KiB gzip,
  gültige Ressourcenbindungen und statische Assets.
- Lokale Cloudflare-Laufzeit: HTTP-Tests mit isolierter D1-/R2-Ablage für
  Lizenzanlage, Upload, Publikation, drei Manifeste und Downloads, manipulierte
  Signatur, Composer, Release-Notizen und Statusseite. Keine produktiven Daten.
- Die vollständigen Plattformprüfungen und die exakten GitHub-CI-Commits bleiben
  vor Merge und Veröffentlichung verpflichtend; lokale Ergebnisse ersetzen sie nicht.

## Betrieb und Rücknahme

Die Änderung aktiviert den optionalen Dienst nicht und verändert keine
produktiven Ressourcen, Secrets, Update-URLs oder GitHub-Einstellungen. Der
produktive Distributionsweg bleibt derselbe. Keine Lizenz-/Inhaltsmigration.

Bei einem getrennten späteren Deployment dieses optionalen Dienstes müssen die
bestehenden Produktionsbindungen eingesetzt werden; die lokale Platzhalter-
Konfiguration darf sie nicht ersetzen. Ein Rückrollen des Worker-Artefakts
benötigt keine Datenrückmigration. Ein Rückwechsel auf den alten Build würde
jedoch wieder die betroffenen Framework-Abhängigkeiten einführen.
