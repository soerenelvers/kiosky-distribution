# Kiosky: Sicherheit, Performance und Zuverlässigkeit

Stand: 9. November 2026. Untersucht wurden die aktuellen gemeinsamen Quellen,
Tests und Paketgrenzen für Standalone, WordPress und TYPO3. Produktionsumgebung
laut Betreiber: **Plesk, HTTPS, eigene Backups**. Plesk-Konfiguration,
Produktionslogs, echte Schlüssel, installierte Fremdplugins und Backups wurden
nicht eingesehen oder wiederhergestellt. Daher ist dies keine Zertifizierung der
Live-Installation und kein vollständiger Penetrationstest.

## Stack und geprüfte Grenzen

- Gemeinsamer JavaScript-Editor/Player, DOM/CSS-basierte Wiedergabe, Browsercache.
- Standalone: Node.js/TypeScript, HTTP-Server, SQLite, scrypt-Benutzerpasswörter,
  gehashte Sitzungs-/API-Tokens, AES-GCM für Integrationszugänge.
- WordPress: PHP-Domainkern, REST mit nativer Sitzung/Nonce, WordPress-Datenbank
  und Mediathek; kein zusätzlicher Node-Server nötig.
- TYPO3: derselbe PHP-Kern, Backend-/Frontend-Routen, native Benutzer,
  Doctrine DBAL und FAL; CMS-Verschlüsselungsschlüssel schützt Integrationsdaten.
- Externe Datenquellen: CrewBrain/easyjob; eigener Update-/Lizenzdienst,
  GitHub-CI und native Desktop-/Pi-Player.

## 1. Sicherheit

### Behoben

**TYPO3-Rechte werden vor dem Aufbau der gemeinsamen API verbindlich geprüft.**
Die gemeinsame Rolle `admin/editor/viewer` war gröber als die native Rechtematrix.
Isoliert reproduzierbar waren Systemeinstellungen als `manager`, Notfallauslösung
als `event_editor` und Slide-Erstellung als `display_operator`.
`PermissionService::isApiGranted()` und `ApiController` prüfen nun Methode und
API-Pfad anhand der freigegebenen nativen Rechte. Unbekannte Schreibendpunkte
benötigen Systemeinstellungsrechte. Native Administratoren behalten Vollzugriff;
Küchen-/Kartenrechte werden weiter separat geprüft. Presets und zeitversetzte
Preset-Auslösungen benötigen wegen möglicher indirekter Warnungen ebenfalls
Notfallrechte. [Rechtematrix](typo3-permissions-proposal.md).

**Eigene Schriften sind geschützt und begrenzt.** Kein anonymer Font-Endpunkt,
keine Remote-Import-URL, keine frei eingebbaren CSS-Familien. Native Rechte werden
zusätzlich zur gemeinsamen Rolle geprüft. Player erhalten nur zugewiesene
Schriften mit ihrem eigenen Schlüssel. Archiv-/Template-Referenzen verhindern
versehentliches Entfernen. Eingebaute Fonts liegen lokal, ohne Google-Abfragen.

**Diagnoselogging schwärzt typische Geheimnisfelder und Tokenmuster.**
Node-Logs/Audit-Metadaten und öffentliche PHP-Fehler verwenden eine gemeinsame
Redaktionslogik mit Paritätstests. Redaktionsregeln ersetzen keine Geheimnisdisziplin:
Frei formulierte Geheimnisse ohne erkennbares Feld/Muster können unerkannt bleiben.

### Authentifizierung, Tokens und Zugangsdaten

- Normale CrewBrain-/easyjob-Konfigurationsantworten verwenden maskierte
  öffentliche Konfigurationen; gespeicherte Integrationszugänge werden
  authentifiziert verschlüsselt. Gewöhnliche Inhalts-/Systemübertragungen
  entfernen geheime Felder. Daraus folgt kein Nachweis, dass ein älteres Release
  oder ein kundeneigener Patch dieselben Grenzen hatte.
- Standalone und WordPress behalten ihre bisherige gröbere Matrix
  `admin/editor/viewer`: `editor` darf neben Inhalten auch Betriebsaktionen
  einschließlich Warnungen ausführen, aber keine administrativen Zugänge ändern.
  Das ist keine mit TYPO3 identische Fachrollentrennung. Wenn Inhaltsredakteure
  keine Warnungen auslösen dürfen, ist dafür ein eigener freigegebener
  Rollen-/Migrationsschritt nötig; diese Rechte wurden hier nicht still geändert.
- Der optionale alte Standalone-`KIOSKY_ADMIN_API_KEY` besitzt weiterhin breite
  administrative Bestandsrechte außerhalb der neuen Font-API. Für neue
  Integrationen die beschränkten API-Benutzer nutzen und den Altschlüssel bei
  Nichtverwendung entfernen. Die neue Font-API akzeptiert nur Benutzersitzungen
  beziehungsweise zugewiesene Player-Schlüssel.
- Integrations-API-Tokens bleiben auf `/api/v1` beschränkt; sie werden nicht zu
  CMS-Administratorsitzungen. Player-Schlüssel bleiben an das Display gebunden.
- Standalone besitzt bereits Login-Begrenzung, Passwort-Hashing und Sitzungsprüfung.
  WordPress/TYPO3 verwenden native Konten und Berechtigungen.
- **Sofort im Betrieb prüfen:** individuelle Integrationskonten mit minimalen
  Quellrechten, ungenutzte Tokens widerrufen, getrennte Produktions-/Testschlüssel,
  native CMS-Admins mit MFA. Keine pauschale Rotation ohne Migrationsplan:
  Ein Wechsel des Verschlüsselungsschlüssels kann gespeicherte Zugänge unlesbar machen.
- **Plesk prüfen:** konsistentes HTTPS hinter Proxy, sichere Cookies, kein CDN-/Proxy-
  Cache für angemeldete APIs und Playerzustände; keine Query-Strings oder
  Authorization-/Cookie-Header in Access-/Fehlerlogs. Player-Startlinks können
  Schlüssel enthalten. `Referrer-Policy: no-referrer` schützt ausgehende Referer,
  ersetzt aber nicht die Bereinigung eigener Serverlogs.
- Verschlüsselung bei Speicherung: Anwendungsverschlüsselung schützt einzelne
  Zugangsdaten, nicht alle Veranstaltungs-/Mediendaten. Plesk-/Datenträger- und
  Backupverschlüsselung mit getrenntem Schlüsselbestand organisatorisch prüfen.

### OWASP-Schwerpunkte und Automatisierung

| Risiko | Konkreter Prüfpunkt | Werkzeug / Abnahme |
|---|---|---|
| Broken Access Control | native Rollen, Presets/Warnungen, Display-Key-Bindung | negative API-Matrix, echte CMS-Installation/Upgrade, neue Endpunkte default-deny |
| Injection/XSS/Upload | Eventtexte, Templates, Medien, Fontcontainer | Escaping-/Pfadtests, Semgrep-Regeln für JS/PHP als nächster separater CI-Schritt |
| Kryptografie/Secrets | geschützte Exporte, Maskierung, Schlüsselbackup | Redaktions-/Verschlüsselungstests; Gitleaks für Vollhistorie separat einführen |
| SSRF/Abhängigkeiten | konfigurierbare Quell-URLs und ausgehende Requests | Egress auf nötige Ziele begrenzen; pnpm/npm/Composer-Audits pro tatsächlichem Lockgraph |
| Fehlkonfiguration | Plesk, native CMS-Rollen, öffentliches `var`/Backups | Staging-DAST mit OWASP ZAP Baseline, anschließend authentifizierte Rollentests |

Bestehende CI führt Typprüfung, Tests, Paketprüfung, Dependency-Audits,
Credential-Mustersuche und native WordPress-/TYPO3-Läufe aus. Die Mustersuche
ist kein vollständiger Secret-Scanner, TypeScript kein SAST-Ersatz. Neue
SAST-/DAST-Workflows gehören in einen separat geprüften Control-Plane-Change.
Der lokale Lockfile-Audit am 9. November meldet im Hauptprojekt **0 Befunde**.
Im optionalen `update-service` verbleiben **19 mittlere**, keine hohen oder
kritischen Befunde (u. a. Drizzle/Esbuild, Next-Lint/Glob und Vinext/Satori/fflate).
Das sind Paketmeldungen einschließlich transitiver Entwicklungsabhängigkeiten,
nicht 19 nachgewiesene ausnutzbare Produktionslücken. Updates benötigen einen
separat geprüften Dependency-Change mit Build-/Kompatibilitätstests; sie wurden
nicht durch eine schwächere Audit-Schwelle verdeckt.

Keine aggressiven DAST-Scans auf produktiven Notfall-/Displayendpunkten.

## 2. Performance

### Bestehende und ergänzte Maßnahmen

- Schriftdateien werden nicht im CMS-Gesamtzustand gespeichert; nur kleine
  Deskriptoren. Spieler laden benötigte Dateien bei Bedarf, bündeln gleichzeitige
  Anforderungen und nutzen einen begrenzten Offlinecache. SHA-256 ermöglicht
  sichere Wiederverwendung; Originaldateien werden nicht bei jedem Poll übertragen.
- Lokale WOFF2-Standardschriften vermeiden externe Verbindungsaufbauten.
- Laufende Wiedergabe und Editor teilen Renderpfade; gespeicherte Fontauswahl
  gilt für Vorschau und Player. Später geladene Fonts lösen Textanpassung aus.
- Keine pauschale Migration auf Redis oder einen anderen Datenbankserver:
  erst Latenz, Schreiblast und Anzahl paralleler Player messen.

### Priorisierte Engpässe

1. **Schreibvorgänge pro Player-/API-Poll messen.** Standalone aktualisiert
   Sitzungs-/Tokenzeitstempel häufig; der PHP-API-Zugang speichert seinen Zustand
   auch dann, wenn sich fachlich wenig ändert. Bei vielen Displays konkurriert
   das mit Editoränderungen. Zeitstempel auf z. B. 60 Sekunden drosseln und
   unveränderte Zustände nicht speichern; vorher Konflikt-/Widerrufstests ergänzen.
2. CMS-Gesamtzustand und virtuelle Event-/Werbeprojektionen: Größen und
   Serialisierungszeit erfassen, Projektionen pro Inhaltsrevision wiederverwenden.
   Cache-Schlüssel müssen Standort, Displayzuweisung, Lizenz und Rechte enthalten.
   Warnungen, Zeitgrenzen und Widerrufe dürfen nicht über einen langen TTL veralten.
3. Editor/Player: große Medien, gleichzeitig aktive Videos, DOM-Neuaufbau und
   Eventlayout messen. Medien auf Ausgabeauflösung begrenzen, Videos passend
   codieren, inaktive Decoder lösen, Layoutmessungen bündeln. Kein pauschales
   Vorladen aller Veranstaltungen/Schriften in jeden Player.
4. Statische Assets mit Buildkennung am Webserver komprimiert und lang cachen;
   HTML/API-Zustände weiterhin kontrolliert revalidieren bzw. `no-store`.

Messbare Abnahme im Staging: 1/10/50 simulierte Displays, realistische Medien,
30 Minuten Last plus 24 Stunden Dauertest; p50/p95 API-Zeit, DB-Wartezeit,
Speicherverlauf, ausgelassene Frames und Warnungsumschaltzeit vergleichen.
Zielwerte vorab festlegen, z. B. p95 Zustandsabruf <300 ms im lokalen Netz,
kein stetiges Speicherwachstum. Das sind Ziele, keine gemessenen Produktivwerte.

## 3. Resilienz und Zuverlässigkeit

### Drittanbieter und Ausfälle

CrewBrain besitzt begrenzte Wiederholungen/Timeouts, easyjob Zeitlimits und einen
begrenzten erneuten Authentifizierungsversuch. Ein vollständiger, persistenter
Circuit Breaker und eine zentrale Jobqueue sind damit nicht belegt.

**Nächste Verbesserung:** Importjobs je Quelle serialisieren, Rückoff mit Zufall
und Obergrenze, `Retry-After` begrenzen, nach wiederholten Fehlern kurze Sperrphase
und genau einen Probeaufruf zulassen. Letzten gültigen Datenstand mit Zeitstempel
behalten. Lesende Provider-Ausfälle dürfen den Player nicht leeren.

```text
Scheduler -> Job pro Quelle -> Timeout -> begrenzter Retry/Backoff
          -> valide Daten -> atomarer Zustandswechsel + neue Revision
          -> Fehler -> letzter gültiger Stand + Alter + Alarm
```

Warnungen/Bedienkommandos brauchen getrennte Kapazität von langsamen Importjobs.
Nicht idempotente Schreibaktionen nicht blind wiederholen; Job-/Occurrence-IDs
und bestehende Konfliktprüfung für Mehrfachausführung beibehalten.

### Fehler und Überwachung

- Strukturierte Logs: Request-ID, Aktion, Dauer, Status; keine Nutzdaten-/Header-
  Komplettdumps. Logrotation und begrenzte Aufbewahrung in Plesk einrichten.
- Healthchecks von außen: Web/API verfügbar, letzte erfolgreiche Synchronisation,
  Alter des Player-Heartbeats, freier Speicher, DB-/Fontverzeichnis beschreibbar.
  Alarm bei alter Quelle und offline Display unterscheiden.
- Backupfehler, fehlgeschlagene Updates und Schlüssel-/Lizenzfehler separat melden.
  Browser-Fontfehler verwenden Ersatzschrift und blockieren keine Wiedergabe.
- Verantwortlichkeiten für Notfallinhalte und Bereitschaft ausdrücklich vereinbaren;
  ein erreichbarer Prozess beweist keine korrekte sichtbare Notfallausgabe.

### Backups und Wiederherstellung auf Plesk

**Vor dem nächsten Produktiveinsatz einen isolierten Restore nachweisen.**
Vorhandene eigene Backups sind eine Grundlage; ihre Vollständigkeit ist ungeprüft.
Zu sichern: konsistenter Datenbankstand, native Medien/FAL, private Fontdateien,
Konfiguration und verschlüsselt separat verwahrte Schlüssel (WordPress-Salts,
TYPO3-/Standalone-Verschlüsselungsschlüssel sowie betriebliche Updatezugänge).

Vorgeschlagene Ziele: **RPO 24 h, RTO 4 h**; bei häufigen Änderungen RPO 1 h.
3-2-1-Kopien, eine unveränderliche/offline Kopie, mindestens tägliche Prüfung der
Backupjobs und monatlicher Restore auf isolierter Stagingdomain. Nach Restore
prüfen: Anmeldung, Rollenverbote, Medien/Fonts, Eventkalender, Zeitplanung,
Warnung/Preset, Playerkopplung und Updatefähigkeit. Fremdversand und produktive
Notfall-/Provideraufrufe im Staging deaktivieren.

Vor Updates Datenbank und Dateien konsistent sichern; getestetes Paket behalten.
Bei Rollback gemeinsam passenden Zustand und Programmstand zurücksetzen.
Unveränderliche private Fontbytes erleichtern Rollback; alte Dateibestände
benötigen eine mit Backups abgestimmte Aufbewahrungsregel.

## Priorisierte Roadmap

| Priorität | Maßnahme | Aufwand | Abnahme |
|---|---|---|---|
| P0 | **Rechtefix und geschützte Schriften ausrollen** | dieses Release + Stagingtest | Rollenverbote, Schlüsselfälle, echte CMS-Installations-/Upgradetests grün |
| P0 | **Plesk-/Backup-/Secret-Konfiguration prüfen und Restore testen** | 0,5–1 Tag Betreiber | dokumentierter Restore, private Pfade, keine Secrets in Logs |
| P1 | **Poll-Schreiblast reduzieren und Lastprofil messen** | 1–2 Tage | weniger DB-Writes ohne Widerrufs-/Konfliktregression |
| P1 | Importserialisierung, Backoff/Circuit Breaker | 2–3 Tage | reproduzierbare 429/Timeout-/Teilausfalltests |
| P1 | Secret-Scanner, gezieltes JS/PHP-SAST, Staging-DAST | 1–2 Tage + Triage | separat geprüfte CI und behandelte reale Befunde |
| P2 | Revisionscaches und Medienbudget anhand Messungen | 2–4 Tage | reproduzierbarer p95-/Dauertest, keine stale Warnungen |
| P2 | Alarmierung, Restoreübung, Bereitschaft/Vertretung | laufender Betrieb | benannte Verantwortliche und geprobter Ablauf |

Aufwände sind Schätzungen und keine Zusage eines vollständigen Betriebsaufbaus.

## Manuel: Einordnung und offene Vereinbarungen

Die geprüfte Version und seine Patches liegen nicht vor. Die bestätigten aktuellen
Rechtefehler sind eigenständig reproduziert und korrigiert; seine drei damaligen
Befunde können damit nicht pauschal als identisch oder erledigt gelten. Seine
TYPO3-14-/Kalenderpatches erst mit Diff und Regressionstests übernehmen.

Die bestehende `LICENSE.md` unterscheidet proprietäre Standalone-Software von
GPL-2.0-or-later für die WordPress-/TYPO3-Distributionen; gemeinsame Bestandteile
haben dort die zusätzliche GPL-Freigabe. Keine pauschale Aussage, dass CMS-Nutzer
keine Änderungs-/Weitergaberechte hätten. Markenrechte, zusätzliche Module,
Support und Updateleistungen gesondert schriftlich klären; Lizenz unverändert.

Für die Zusammenarbeit: individuelle Repositorykonten, PRs mit Tests und Review,
Sicherheitsmeldungen vertraulich, reproduzierbare Befunde ohne Secrets. Betrieb,
Websiteintegration, Updatefreigabe, Notfallinhalte und Vertretung schriftlich
zuordnen. Gewünschte Live-Update-Deaktivierung im jeweiligen Deployment bewusst
konfigurieren und testen; in dieser Arbeit wurden weder Kontozugänge vergeben
noch produktive Updater oder Plesk-Einstellungen verändert.
