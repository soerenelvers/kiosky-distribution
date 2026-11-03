# Stabilität und Ressourcenverbrauch in 3.13.3

Der Durchlauf umfasst den gemeinsamen Player/Editor, die Standalone-Playerabfragen,
die PHP-Projektionen beider CMS und die TYPO3-Speicherung. Produktionssysteme und
Kundendaten wurden dafür nicht verändert.

## Bestätigte Probleme und Änderungen

| Bereich | Vorher | Ab 3.13.3 |
| --- | --- | --- |
| Standalone-Displayzugriff | Pro Player-Aufruf alle Displays und eine Gruppenabfrage je Display | Parametrisierte ID-/Slug-Suche und gemeinsame Gruppenabfrage |
| SQLite-Mitgliedschaften | Primärschlüssel beginnt mit Gruppe | Zusätzlicher Index auf Display und Gruppe |
| CMS-Player und Listen | Alle Displays projizieren; wiederholte Suchläufe durch Kanäle, Slides und Matrizen | Ein Player-Display projizieren; Zuordnungen für Listen einmal aufbereiten |
| Online-Anzeige | Ein gespeichertes Online-Flag blieb ohne neue Heartbeats aktiv | Gültiger Heartbeat innerhalb von 60 Sekunden; neuere Heartbeats stellen online wieder her |
| Offline-Cache | Gesamten Zustand alle fünf Sekunden schreiben | Unveränderte Inhalte einmal pro Minute sichern; Änderungen sofort |
| Voller Browserspeicher | Derselbe fehlgeschlagene Schreibversuch bei jedem Poll | Wiederholung desselben Stands frühestens nach einer Minute; neue Inhalte sofort versuchen |
| Bildschirmsperre | Freigegebene Sperre blieb als belegte Referenz erhalten | Bei Sichtbarkeit neu anfordern, parallele Anforderungen verhindern, beim Schließen aufräumen |
| TYPO3-Zustand | Ungültiges JSON konnte als leerer Bestand weiterverarbeitet werden | HTTP 500 mit `TYPO3_STATE_CORRUPT`; ursprüngliche Daten bleiben erhalten |

Das Heartbeatlimit ist eine Leseprojektion. Es entstehen keine zusätzlichen
CMS-Schreibkonflikte durch einen Hintergrundprozess zum Offline-Markieren.
Die bisherigen Player-Abfrageintervalle und Warnungsreaktionszeiten bleiben
erhalten. Ausstehende Player-Befehle werden nicht im Offline-Cache gespeichert.

## Messung

Isolierte SQLite-In-Memory-Fixture: 1.000 Displays, 100 Abfragen desselben Slugs.

| Messwert | 3.13.2 | Optimierter Zugriff |
| --- | ---: | ---: |
| SQL-Abfragen | 100.100 | 200 |
| Gelesene Displayzeilen | 100.000 | 100 |
| Lokale Gesamtlaufzeit | rund 997 ms | rund 12 ms |

Die Zeitmessung zeigt nur diesen Zugriffspfad auf der Testmaschine, nicht die
Gesamtbeschleunigung einer Installation. Die Regression begrenzt Abfragen und
gelesene Zeilen statt empfindlicher Zeitgrenzen. Der SQLite-Abfrageplan bestätigt
die indizierte Suche nach Mitgliedschaften.

Für eine Stunde unveränderte Playerdaten prüft die simulierte Pollfolge
60 erfolgreiche Cache-Schreibvorgänge statt 720. Inhalts-, Warnungs-, Lizenz- und
Displayänderungen werden gesondert geprüft. Monotone Zeitmessung steuert das
Speicherintervall unabhängig von Korrekturen der Systemuhr.

Gemeinsamer PHP-Kern: zehn Aufbereitungen je Sammlung mit jeweils 1.000
Einträgen und 1.000 referenzierten Kanälen/Slides/Standorten in einer lokalen
Fixture. Die Ausgabe-Hashes stimmen vor/nach der Optimierung überein; nur das
bewusst geänderte Online-Flag ist aus diesem Vergleich ausgenommen.

| Sammlung | 3.13.2 | Optimierte Aufbereitung |
| --- | ---: | ---: |
| Displays | 2.289 ms | 100 ms |
| Standorte | 908 ms | 2,5 ms |
| Presets | 495 ms | 2,6 ms |
| Zeitplaneinträge | 1.064 ms | 3,8 ms |

Auch dies sind isolierte Aufbereitungszeiten, keine Antwortzeitgarantie für
WordPress, TYPO3 oder die Datenbank eines Hosters.

## Verifikation

- `pnpm lint`, `pnpm test`, `pnpm test:release`, `pnpm test:php`, Plattformbuilds.
- Chromium: gemeinsame Oberfläche mit Standalone-, WordPress- und TYPO3-Transport;
  Eventquellen, Countdown/Offset, Tagordner, URL-Slides, laufende Playeraktualisierung,
  Smartphone-/Tablet-Hoch-/Querformat, Dialoge und acht Arbeitsbereiche.
- WebKit: derselbe Editor-/Player-Durchlauf in der isolierten Standalone-Fixture.
- `tests/player-stability-browser-smoke.py`: echte Chromium-Player mit allen drei
  Transporten, unveränderte Polls ohne erneuten Cache-Schreibzugriff, sofortiges
  Sichern einer Warnung, Wiedergabe bei Speicherfehlern und Neuladen mit blockierter
  Player-API aus dem letzten gespeicherten Inhalt einschließlich Warnung.
- Speisekarten: echter lokaler HTTPS-PDF-Download, Poppler, automatischer Abgleich,
  Fehlererhalt, Playerupdates ohne Navigation und PDF-Ecken in vier Seitenverhältnissen.
- Zeitplaneintrag mit WordPress-Transport: lokaler Entwurf, atomarer Abschluss,
  Fehlererhalt, Wiederholung und unveränderte Ziel-IDs.
- TYPO3-Store-Fixture: beschädigte Daten, sichere Fehler, unveränderter Bestand,
  erstmaliges Anlegen, Unicode, identische Inhalte und veraltete Schreibzugriffe.
- Native WordPress-/TYPO3-Installation und Upgrade bleiben verpflichtende CI-Gates.

Browsertransporte gegen die isolierte Node-Fixture ersetzen keine nativen
CMS-Tests. WebKit und mobile Browseremulation ersetzen keine Abnahme auf echten
iPhones, Android-Geräten oder dauerhaft betriebenen Fernsehern. Ein mehrtägiger
Hardware-Dauerlauf und Produktionsprofile großer Kundenbestände stehen noch aus.

Die CMS speichern ihren Zustand weiterhin als Gesamtbestand; dieses Update
führt keine riskante Umstellung auf neue CMS-Fachtabellen durch.

## Update und Rückbau

Die vorhandenen Updatewege liefern alle drei Ausgaben als Version 3.13.3 aus.
Vor dem Update Datenbestand und Dateien sichern. Standalone legt mit Migration
037 ausschließlich einen zusätzlichen Index an. Bestehende Inhalte und
API-Verträge werden nicht migriert; der Index kann beim Rückwechsel bestehen bleiben.
Ein unlesbarer TYPO3-Bestand muss aus einer geprüften Sicherung wiederhergestellt
oder fachgerecht repariert werden; das Update setzt solche Daten nicht zurück.
