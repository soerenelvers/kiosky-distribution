# Stabilität, Geschwindigkeit und mobile Bedienung – 17. November 2026

## Umfang

Gemeinsamer JavaScript-Editor/Player und Verwaltungsoberfläche, Standalone-APIs,
nativer PHP-Kern für WordPress/TYPO3 sowie die vorhandenen Integrations- und
Paketprüfungen. Alle Schreibtests laufen in isolierten lokalen Datenbanken.
Keine Produktivdaten, echten Warnmeldungen oder Infrastrukturänderungen.

## Bestätigte Fehler und Korrekturen in 3.46.3

| Priorität | Befund | Korrektur / Abnahme |
|---|---|---|
| P1 | Eine gehaltene Eventantwort aus Konto A überschreibt nach Wechsel zu Konto B noch den Katalog. | Jede Verwaltungsantwort wird vor der Rückgabe gegen dieselbe aktuelle Identität geprüft. Alte 401-Antworten melden ein neues Konto nicht ab. Bei Standalone-Abmeldung/erneutem Login wird das Dokument neu geladen, damit alte Formulare, Auswahl, Modulzustände und eingebettete Editoren nicht übernommen werden. Benutzerpräferenzen und Player-Kopplungen werden nicht gelöscht. |
| P1 | Ein älterer Katalog kann nach erfolgreichem Speichern eine neu geladene Ansicht wieder überschreiben. | Schreibzugriffe trennen laufende Katalog-Snapshots; Inhalts-, Veranstaltungs-, Display-, Betriebs-, Dashboard-, Medien- und Zeitplanloader übernehmen nur ihren neuesten Ladevorgang. Kontrollierter Browsertest hält die alte Slideliste, speichert eine neue Slide und liefert danach die alte Antwort: Die neue Slide bleibt sichtbar und gespeichert. |
| P1 | Ein noch laufender Katalogabruf kann die vor dem Abruf erteilte Modulfreigabe überholen. | Lizenzgebundene Leseabrufe prüfen die aktuelle Freigabe auch nach Ankunft der Antwort; entzogene Module erhalten keine alten Katalogdaten zurück. Serverrechte bleiben verbindlich. |
| P1 | Ein HTTP-200-Login-/Proxy-HTML wird als `null` weitergereicht; unvollständige Listendaten können Auswahl und Inhalte leeren. | Ungültige JSON-Erfolgsantworten werden ausdrücklich abgewiesen. Inhalts-/Veranstaltungslisten werden vor der Übernahme vollständig geprüft; der bisherige Stand bleibt bei Fehler erhalten. Leere, gültige Listen funktionieren weiterhin. Fehler beim Öffnen der Standalone-Mediathek werden ebenfalls sichtbar gemeldet statt als unbehandelte Promise-Exception zu enden. |
| P1 | Unbegrenzte langsame Leseantworten blockieren Arbeitsabläufe; gleichzeitige gleiche Reads erzeugen doppelte Serverarbeit. | Normale JSON-Reads haben 30 Sekunden, Integrations-/Update-Reads 120 Sekunden Frist. Identische laufende Standard-GETs teilen einen Request und erhalten getrennte Ergebnisobjekte. Nach Abschluss wird nichts dauerhaft gecacht. Explizite Abbrüche bleiben unterscheidbar; große Dateidownloads und Schreibanfragen bekommen keine neue pauschale Frist. |
| P1 | Netzwerkabbrüche beim Speichern sagen nicht, ob der Server die Anfrage noch verarbeitet hat. | Klare Rückmeldung zum unklaren Ausgang; Formularwerte bleiben erhalten, Speichern wird wieder bedienbar. Der Transport wiederholt Schreibzugriffe nicht. Bestehende serverseitige Konflikt-/Transaktionsregeln werden nicht verändert. |
| P2 | Leere Display-/Inhaltskataloge werden mit „noch nicht geladen“ verwechselt und lösen in Zeitplanung/Betrieb unnötige Folgeabrufe aus. | Erfolgreich geladene leere Kataloge erhalten einen eigenen Ladezustand; explizites Neuladen bleibt möglich. |
| P2 | 30 Video-Slides starten bereits beim Öffnen der Bibliothek 30 Videometadatenabrufe. | Miniaturen verwenden `preload=none`, vorhandene Poster und einen Play-Hinweis. In Chromium/WebKit bleiben 30 Videos ohne Videoabruf; beim Öffnen des Editors wird nur das gewählte echte MP4 decodiert, seine 2,4-Sekunden-Metadaten werden weiter gespeichert. |
| P2 | 5.000 ausgewählte Einträge benötigen 12.502.500 ID-Lesezugriffe. | Ein ID-Set ersetzt wiederholte Vollsuchen; gleiche Reihenfolge, Zugehörigkeit und Entfernung ungültiger Auswahlen mit 5.000 ID-Lesezugriffen im Vergleichsaufbau. Keine Änderung der Kanalreihenfolge. |
| P2 | Die Bibliotheksauswahl ist auf Smartphones nur 22 Pixel breit, Aktionen sind 38 Pixel hoch. | Mindestens 44 × 44 Pixel für diese Auswahl-/Aktionsflächen, größere Beschriftung und Umbruch. Tap-Auswahl und Videoeditor bleiben bei 320/390/820/1024 Pixeln erreichbar. |

Die große, proportional skalierte Editor-Vorschau wurde separat mit 3.46.2
veröffentlicht und bleibt enthalten; siehe [Vorschau](../editor-preview.md).

## Fehler- und Zustandsregeln

- Der neue Transport verwendet keinen dauerhaften GET-Antwortcache und keine neue Offline-Speicherung
  von Verwaltungsdaten. Ein erneuter Abruf nach Abschluss liest den Server neu.
- Berechtigungen, Nonces, Cookie-/Tokenprüfung und Datenänderungen bleiben auf dem
  Server verbindlich. Die zusätzlichen Clientprüfungen ersetzen keine Autorisierung.
- Sitzung und Katalog-Snapshot werden auch nach dem Lesen des Antwortbodys geprüft.
  Dateidownloads bleiben an die Identität gebunden. Unabhängige Provider-Vorschauen
  laufen bei fremden Inhaltsänderungen weiter; sie sind kein Inhaltskatalog.
- Normale HTTP-Fehler behalten Status und Fehlercode, insbesondere 409 für die
  vorhandene CMS-Konfliktbehandlung. Blob-Downloads und 204-Antworten bleiben kompatibel.
- Standalone-Kontowechsel erneuert den privaten Seitenzustand; native CMS-Anmeldung bleibt beim jeweiligen CMS. Bei abgelaufener Sitzung werden
  dadurch ungespeicherte lokale Formulare nicht in ein anderes Konto übernommen.
- Zeitplaneinträge, Medien, Inhalte und Rechte werden nicht migriert oder bereinigt.

## Systematische Abnahme

Die Prüfung kombiniert vorhandene Node-/PHP-Tests und tatsächliche Browserabläufe.
Chromium und WebKit prüfen gemeinsame Standalone-/WordPress-/TYPO3-Transporte;
die nativen CMS-Installations-/Upgrade-Gates ergänzen diese Fixtures.

- `tests/admin-request-resilience.test.ts`: parallele Reads, getrennte Ergebnisse,
  Kontowechsel, alter 401-Status, Schreibtrennung, keine Schreibwiederholung,
  ungültige Antworten, HTTP-Fehler, Fristen/Abbruch, aktuelle Kataloge, große
  Auswahl, Login-Neustart und unabhängige Provider-/Dateianfragen.
- `tests/admin-resilience-browser.py`: gehaltene alte Katalogantwort über eine
  echte Slidespeicherung, HTTP-200-HTML, kontrollierter fehlgeschlagener Event-PUT,
  erhaltenes Formular/gespeicherte Daten, Abmeldung und unveränderte Präferenzen.
- `tests/library-performance-browser.py`: 30 Videos ohne Metadatenabruf,
  Touch-Ziele/Umbruch, echte Tap-Auswahl und MP4-Decodierung im Editor.
- `tests/security-mobile-browser.py`: acht Arbeitsbereiche, Dialoggrenzen,
  erreichbares Speichern, freigegebene Kanäle, Inhaltsrollen und inerte XSS-/URL-
  Testeingaben; 320×640, 390×844, 412×915, 768×1024, 820×1180, 1024×768, 844×390.
- Player-Bestandstests prüfen Offline-Neustart, vollen Speicher, Warnungen,
  Heartbeats, Medienabbau und Animation. Die Ticker-Fixture erzeugt weiterhin
  keine zusätzlichen Layout-Neuberechnungen im 1,5-Sekunden-Messfenster.
  Bei 200 Medienwechseln werden Decoder/Iframes freigegeben; der gemessene JS-Heap
  blieb etwa bei 1,27 MB. Prozessspeicher war in diesem Container nicht auslesbar.
- Ergänzende Bestandsabläufe: Editor-Transformationen, Zeitplan-Schließen,
  Kundenfreigaben, Kalender, Speisekarten-Gastzugang, Videowerbung und Systemumzug.

Die konkreten Ergebnisse stehen im Release-PR. Keine Zusage einer festen
Bildrate auf beliebiger Hardware und keine physische iPhone-/iPad-/Android-Abnahme.
WebKit unterstützt die Prüfung Safari-naher Browserlogik, emuliert aber weder die
vollständige iOS-Tastatur noch alle Betriebssystemdialoge. Für die Geräteabnahme:
Eingabe mit Bildschirmtastatur, Drehung, Hintergrund/Rückkehr, Touch-Drag/Resize,
große Medien und 24-Stunden-Ausspielung mit den vorgesehenen Geräten testen.

## Abhängigkeiten und Betrieb: verbleibender Review

Zum Prüfzeitpunkt: Hauptprojekt `pnpm audit` ohne gemeldete Advisories.
Der separate `update-service` meldet 19 moderate, keine hohen/kritischen Hinweise.
Das sind Sammelmeldungen im aufgelösten Abhängigkeitsgraph, keine 19 bestätigten
Laufzeit-Exploits. Direkte betroffene Pakete: `next` 16.4.2 (Audit nennt 16.4.6),
`drizzle-kit` und `vinext`. Die automatischen Vorschläge für die beiden letzteren
sind größere Versionswechsel beziehungsweise ältere Versionen; sie dürfen nicht
blind übernommen werden. Der Next-Hinweis betrifft die Kombination aus `use cache`
und `unstable_cache`; Eine Quelltextsuche in diesem Stand findet dafür keine direkte Verwendung in
den Service-Quellen; dies ersetzt keinen Reachability-Review transitiver Bibliotheken. Der Haupt-/CMS-Client wird dadurch nicht automatisch verwundbar.

Kein stiller Lockfile-/Frameworkwechsel: AGENTS.md verlangt für Abhängigkeiten
expliziten Eigentümerreview. Nächster separater Schritt ist ein konkreter Upgrade-
PR mit Lockgraph, Build-, Start-, Lizenz-/Update- und Rückrolltests. Diese Hinweise
bleiben offen; dieses UI-/Resilienzupdate behauptet keine Behebung davon.

Plesk-/WAF-/Betriebssystem-Konfiguration, produktive Fremdplugins, echte Provider-
Konten und Wiederherstellung der Betreiberbackups sind nicht Teil der lokalen
Fixtures. Keine Schlüsselrotation und keine Änderungen an produktiven Zugängen.
