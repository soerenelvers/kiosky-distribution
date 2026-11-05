# Lokale Entwicklung

Die Countdown-Regression `python tests/countdown-browser-smoke.py` prüft nach
`pnpm build` alle drei UI-Transporte mit echtem Chromium: Formate, Ausblenden des
gesamten Elements, Hochzählen, täglicher Neustart, nächster Ablaufpunkt,
verschachtelte Countdowns, gespeicherte Einstellungen und Smartphone-/Tablet-Bedienung.
Erforderlich sind Python mit Playwright und Chromium unter `/usr/bin/chromium`.
Server und Datenbank sind isolierte Fixtures; native CMS-Speicherung und Upgrades
laufen zusätzlich in der regulären CI.

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

Die zentrale Lizenzverwaltung, der gestufte Rollout und der isolierte
HTTPS-Browsertest sind in [licensing.md](licensing.md) beschrieben. Lizenzdaten
gehören nicht in gewöhnliche Inhaltsübertragungen; Clients behalten bei
Netzausfall den letzten signierten Zustand, einschließlich bestätigter Sperren.

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


## Browserregression für PDF-Weblink-Abonnements

Nach `pnpm build` kann `python tests/menu-web-browser-smoke.py` den vollständigen
Ablauf im **Hauptmenü → Speisekarten** prüfen. Erforderlich sind Chromium unter
`/usr/bin/chromium`, Python mit Playwright, ReportLab und Pillow, OpenSSL und Poppler.
`KIOSKY_BROWSER_PLATFORM=wordpress` beziehungsweise `typo3` prüft zusätzlich die
CMS-Transportvarianten der gemeinsamen Oberfläche gegen den isolierten Testserver;
die nativen CMS-Installationen werden separat in CI geprüft. Mit
`KIOSKY_BROWSER_OUT` lässt sich der Ergebnisordner festlegen.

Der Test startet einen isolierten HTTPS-PDF-Server mit kurzlebigem Testzertifikat,
verwendet den echten Downloader und Poppler und prüft Formularsichtbarkeit,
Slides/Kanal/Tags, Umbenennung, Intervalländerung, automatische Aktualisierung ohne
offenen Editor, Player-Aktualisierung, Fehlererhalt, SSRF-Weiterleitungsschutz und
Mobilansicht. Er greift weder auf Kundendaten noch auf externe PDF-Server zu.
Der Netzwerkadapter liegt ausschließlich unter `tests/fixtures` und wird nur vom
Testprozess geladen. Der gesonderte Kücheneditor darf die Abo-Verwaltung weiterhin
nicht anzeigen. Der zusätzliche Zugang über die Moduleinstellungen bleibt geprüft.

Die PDF-Player-Prüfung verwendet farbige Eckmarken und kontrolliert anhand echter
Screenshot-Pixel die Sichtbarkeit aller vier Seitenecken in Querformat, Hochformat,
Quadrat und einem sehr breiten Fenster. Sie prüft außerdem bestehende PDF-Rahmen,
responsive Slides, aus dem PDF ermittelte Randfarben und manuelle Hintergrundfarben.

## Event-, URL- und mobile Editor-Regression

`python tests/event-editor-browser-smoke.py` prüft nach dem Build automatische
Eventquellen, Event-Countdown/Offset, Tagordner, direkte HTTPS-Website-Slides und
Playeraktualisierung ohne Navigation. Fünf Smartphone-/Tabletformate im Hoch- und
Querformat sowie die Layoutgrenzen sämtlicher vorhandener Dialoge werden geprüft.
Mit `KIOSKY_BROWSER_ENGINE=webkit` wird ein installiertes Playwright-WebKit benutzt;
`KIOSKY_WEBKIT_EXECUTABLE` erlaubt einen eigenen MiniBrowser-Pfad.
`KIOSKY_BROWSER_PLATFORM=wordpress` oder `typo3` prüft den gemeinsamen
CMS-Oberflächentransport gegen den isolierten Node-Server. Native CMS-Installation
und Upgrade werden zusätzlich durch die obligatorische CI geprüft.
`KIOSKY_BROWSER_OUT` legt den Ordner für Screenshots und Serverlogs fest.

Die externe Displayseite wird ausschließlich als lokale Browserfixture erfüllt.
Es erfolgt kein Zugriff auf ein fremdes Kundendisplay. Die Bibliotheks-/Dialogtests
sind Browseremulation mit Touch-Eingabe; echte Android-/iOS-Geräte sind davon
getrennte Abnahmeziele. Funktionsbeschreibung: [Event- und URL-Slides](dynamic-event-slides.md).

## WordPress-Speicherregression

Der native WordPress-Smoke (`tests/wordpress-external-api-smoke.php`) führt
`wordpress-state-store-scenario.php` gegen eine isolierte temporäre Optionstabelle
aus. Geprüft werden gemischte utf8-/utf8mb4-Spalten sowie reine utf8mb4-Spalten,
Unicode-Inhalte, Slide-Erstellung/-Änderung/-Löschung, Einstellungen, Optionscache,
veraltete Schreibzugriffe und Erhalt der Daten bei einem SQL-Schreibfehler.
Die eigentliche WordPress-Optionstabelle wird nicht verändert.

Bei „Kiosky-Daten konnten nicht in WordPress gespeichert werden“ wurde ein
Schreibzugriff abgewiesen. Bis 3.12.0 konnte die tabellenweite Zeichensatzprüfung
einer direkten SQL-Abfrage gültige Inhalte in einer utf8mb4-Datenspalte ablehnen,
wenn andere Spalten noch utf8 verwenden. Ab 3.12.1 verwendet der Store die native
WordPress-Funktion `$wpdb->update()` mit spaltenweiser Validierung und dem bisherigen
Wert als Bedingung gegen paralleles Überschreiben. Das vorhandene serialisierte
Datenformat bleibt erhalten; keine Konvertierung von Kundentabellen ist nötig.
Bleibt der Fehler bestehen, müssen die konkrete Datenbankfehlermeldung,
Schreibberechtigungen, Verbindungszustand und Datenbankgrenzen geprüft werden.
SQL-Abfragen und Optionsinhalte können Zugangsdaten enthalten und dürfen nicht
ungefiltert weitergegeben werden.

### WordPress-Schreibgrenzen ab 3.12.2

Der Store validiert neue Inhalte weiterhin mit WordPress je Datenbankspalte.
Die ASCII-Abfrage überträgt den Wert einmal als Base64; `FROM_BASE64()` schreibt
unveränderte serialisierte Bytes. `SHA2(option_value, 256)` vergleicht den zuvor
gelesenen Zustand atomar und unabhängig von einer Groß-/Kleinschreibung der
Textkollation. Dies reduziert die Abfragegröße gegenüber zwei vollständigen
Zustandswerten. Es gibt weder eine Datenmigration noch ein neues Speicherformat.

Vor dem UPDATE wird `@@max_allowed_packet` gelesen. Überschreitet bereits die
verkleinerte Abfrage dieses Limit, bleibt der bestehende Datensatz erhalten;
das Datenbanklimit muss dann beim Hoster angepasst werden. Das Plugin ändert
keine Serverkonfiguration und komprimiert oder entfernt keine Inhalte.
Fehlgeschlagene Lesezugriffe und nicht als Array lesbare gespeicherte Zustände
brechen ab, anstatt mit einer leeren Datenbasis fortzufahren.

Öffentliche Fehler enthalten feste Kennungen wie `WP_DB_PACKET_LIMIT`,
`WP_DB_PERMISSION`, `WP_DB_CONNECTION`, `WP_DB_TEXT_FORMAT`, `WP_DB_STATE_CORRUPT`,
`WP_DB_DISK_FULL` oder `WP_DB_WRITE`, aber keine ursprünglichen SQL-Fehlertexte.
Die WordPress-Fehlerausgabe wird für die Schreibabfrage vorübergehend unterdrückt
und anschließend wiederhergestellt, damit keine serialisierten Inhalte in die
Antwort oder das Standard-Datenbankfehlerlog gelangen.

Der native Smoke führt die CRUD-/Einstellungsszenarien zusätzlich mit 700 KB
Bestand und 1 MB Abfragebudget aus. Lokal wurde derselbe Fall gegen MariaDB 11
mit echtem `max_allowed_packet=1048576` nachgestellt: 3.12.1 scheitert, 3.12.2
besteht. Weitere Fälle prüfen Überschreitungen vor dem Senden, unveränderte Daten
bei Fehlern sowie eine zweite Datenbankverbindung als gleichzeitigen Schreiber
(dieselben Fixture-Zugangsdaten). Der eigenständige Test
`php tests/wordpress-storage-errors-test.php` prüft sichere Diagnosen und
verhindert das Überschreiben nach einem Lesefehler.

## Native WordPress-Medienauswahl und Online-Zeitplanfilter

`php tests/wordpress-native-media-test.php` prüft das Laden der originalen
WordPress-Medienassets ausschließlich auf Kiosky-Seiten mit Bearbeitungs- und
Uploadrechten. Der native Medien-Smoke prüft außerdem vorhandene Dateien anderer
Autoren, private Dateien und Viewer-Rechte mit echten WordPress-Benutzern.
Die Node-Suite prüft Dialog-Abbruch, das native Schließen-vor-Auswahl-Ereignis,
Filter, Editor-Referenzen und die unveränderten anderen Plattformen.

`python tests/wordpress-media-browser-smoke.py` läuft ausschließlich gegen eine
isolierte Loopback-WordPress-Fixture (Standardport 18085). Die Fixture benötigt
die lokalen Testkonten `fixture` (Administrator), `native-browser-editor`
(Kiosky-Redakteur), die Datei-IDs in
`work/wp-native-media-3.13.0/browser-seed.json` sowie das Upload-Testbild im selben
Ordner. Der Test prüft den originalen `wp.media`-Dialog im Elternfenster, Dateien
anderer Autoren, Abbrechen, echte native Uploads, Bilder/Videos, Metadaten,
Attachment-IDs ohne Doppelimport und Desktop-/Mobilbedienung.
`KIOSKY_BROWSER_ENGINE=webkit` verwendet ein vorhandenes Playwright-WebKit;
`KIOSKY_WEBKIT_EXECUTABLE` erlaubt den lokalen MiniBrowser.

Der Online-Filter verwendet ausschließlich das Backend-Feld `display.online`,
keine zusätzliche Heartbeat-Grenze im Browser. Gruppen und Matrizen bleiben bei
mindestens einem Online-Mitglied sichtbar. Er filtert die Ansicht und Timetable,
nicht gespeicherte Zuweisungen oder die Zielauswahl bestehender Einträge.
Die Reihenfolge verborgener Zeilen bleibt beim Sortieren erhaltbar. Statusabfragen
laufen alle 15 Sekunden nur bei aktivem Filter und sichtbarer Zeitplanung;
Dialoge und aktive Zeileninteraktionen werden dabei nicht unterbrochen.

`python tests/media-schedule-browser-smoke.py` prüft nach dem Build den Bulk-Import
über Ordnergrenzen, Entwürfe ohne Dateikopien, ungültige Gesamtimporte sowie den
Online-Filter in Tages-/Wochenansicht, Live-Heartbeat-Übernahme und mobile Layouts.
`KIOSKY_BROWSER_PLATFORM=typo3` prüft denselben Ablauf mit CMS-Transport; die echte
CMS-Speicherung wird zusätzlich in `cms-media-smoke.php` geprüft. WordPress läuft
für diese Medienauswahl im echten nativen Fixture über
`tests/wordpress-media-browser-smoke.py` einschließlich Mehrfachauswahl.

## WordPress: Speichern während laufender Player

Ab 3.13.1 darf eine reine Hintergrundänderung an Player-Status,
Befehlszustellungs-IDs oder API-Nutzungszeitpunkten den gemeinsamen WordPress-
Zustand beim Speichern nicht allein als Inhaltskonflikt sperren. Der Store
vergleicht den geladenen Ausgangsstand, die beabsichtigte Änderung und den
frischen Datenbankstand. Nur wenn mindestens eine Seite ausschließlich solche
Statuswerte verändert hat, werden die Änderungen vor dem atomaren UPDATE
zusammengeführt. Eine Heartbeat-Beobachtung wird dabei vollständig erhalten;
ältere Zeitstempel ersetzen keine neueren Beobachtungen.

Zwei Konfigurationsänderungen werden weiterhin nicht automatisch zusammengeführt.
Geänderte/widerrufene API-Zugänge erzwingen die erneute Autorisierung. Dasselbe
gilt für Status-/Zustellungsanfragen eines inzwischen geänderten oder gelöschten
Players. Neue Befehle werden nicht durch alte Heartbeats entfernt. Der
SHA-256-Vergleich bleibt auch nach dem Zusammenführen aktiv; bei einem weiteren
Rennen liest der Store erneut, statt die komplette API-Aktion zu wiederholen.

`php tests/wordpress-state-merge-test.php` prüft diese Grenzen. Die native
`wordpress-state-store-scenario.php` erzeugt mit einer zweiten Datenbankverbindung
vor jedem Speichern eine Statusänderung: 3.13.0 erschöpft die API-Wiederholungen
mit exakt der gemeldeten Fehlermeldung; 3.13.1 speichert/löscht Slides und speichert
Einstellungen jeweils ohne Aktionswiederholung. Ein weiteres Rennen zwischen
frischem Lesen und SQL-UPDATE sowie die bisherigen echten Konflikt-, Unicode-
und 700-KB-/1-MB-Pakettests bleiben Teil desselben Szenarios.

## Zeitplaneintrag: beim Schließen speichern

Der Dialog hält Datum, Uhrzeit, Priorität, Wiederholung und Ziele lokal, bis
„Schließen“ oder das Schließen-Kreuz betätigt wird. Unverändertes Schließen sendet
keine Schreibanfrage. Enter speichert nicht; Escape hält einen geänderten Dialog
geöffnet und weist auf „Schließen“ hin. Während des Speicherns verhindert eine
Sperre weitere Bearbeitung und doppelte Abschlüsse. Validierungs-/Speicherfehler
lassen Eingaben und Dialog erhalten.

Ab 3.13.2 sendet der Dialog `PUT /api/schedules/block` mit den beim Öffnen
gelesenen `entries`, allen gewählten `targets` und `changes` (Zeitraum,
Priorität, Wiederholung). Vorhandene Zuweisungen behalten ihre IDs; neue Ziele
werden ergänzt und entfernte Ziele im selben Vorgang gelöscht. Der gemeinsame
CMS-Kern speichert den Zustand einmal, Standalone verwendet einen Savepoint.
Fehler bei Prüfung, Speicherung oder späten SQL-Änderungen hinterlassen keine
Teiländerungen. Ein abweichender aktueller Eintrag liefert 409
`SCHEDULE_CONFLICT`; ein automatisches Überschreiben neuerer Daten findet nicht
statt. Drag-and-drop außerhalb des Dialogs behält sein bisheriges Verhalten.

`tests/schedule-close-save.test.ts` prüft Entwürfe, Schließen, Doppelklick,
Reihenfolge, Fehler und Escape. `python tests/schedule-close-browser-smoke.py`
prüft nach dem Build unveränderte Serverdaten vor dem Schließen, mehrere Ziele,
Fehler/Retry und mobile Layouts. `KIOSKY_BROWSER_PLATFORM=wordpress|typo3` prüft
den CMS-Transport; `KIOSKY_BROWSER_ENGINE=webkit` verwendet das lokale WebKit.

### Reale Heartbeats und Zeitplan-Blocktransaktionen

Der neue native `kioskyWordPressRealHeartbeatScenario()` verwendet die echte
Player-API während der Zeitplanspeicherung. Unter 3.13.1 kopierte deren Heartbeat
berechnete Displayfelder aus `listCollection()` in den Zustand. Wechselnde
`currentSlideId`-Projektionen wurden deshalb als Konfigurationsänderung erkannt
und erschöpften die bisherigen Wiederholungen. Ab 3.13.2 wird die Status-Whitelist
auf den ursprünglichen gespeicherten Displaydatensatz angewendet. Anzeigenamen,
Auflösung, Standort und Matrix bleiben ausschließlich Leseprojektionen.

Der native Test prüft zwei Zuweisungen mit einem Zustands-UPDATE neben einem
echten Heartbeat sowie vollständige Unverändertheit bei abgewiesenem SQL-UPDATE.
Die Node-Tests lösen späte UPDATE-/DELETE-Fehler aus und prüfen das Zurückrollen
aller Ziele. `php tests/schedule-block-test.php` prüft dieselbe API, ihre
Snapshot-/Zielprüfung, genau einen Speichervorgang und Viewer-Schutz. Der
Medien-Smoke prüft den neuen Endpunkt zusätzlich in realen WordPress- und
TYPO3-Installationen. Die Browserprüfung erwartet eine einzige Schreibanfrage
beim Schließen und unveränderte Serverdaten nach einem Speicherfehler.

## Windows-Player

Build, Autostart und native Windows-CI sind in [windows-player.md](windows-player.md)
beschrieben. `python tests/player-startup-browser-smoke.py` prüft nach dem Build
die Kopplung über zwei echte persistente Chromium-Sitzungen und automatische
Wiederholung bei verzögertem API-Start für alle drei gemeinsamen Transportvarianten.
