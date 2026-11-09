# Sicherheits- und Mobilprüfung für 3.37.0

Geprüft werden gemeinsame Quellen und isolierte Fixtures für Standalone,
WordPress und TYPO3. Keine Produktionszugänge, Kundendaten, echten Warnmeldungen
oder Provider-Anmeldungen. Betriebsumgebung laut Betreiber: Plesk, HTTPS,
eigene Backups. Deren Konfiguration und Wiederherstellung wurden nicht geprüft.

## Befunde und Korrekturen

| Priorität | Befund / Ursache | Korrektur und Nachweis |
|---|---|---|
| P1 | URL-Auflösung mit leerem Wert verweist auf die aktuelle Playerseite und kann sie erneut verschachteln. | Leere/Whitespace-Werte ausdrücklich als `about:blank` behandeln. Isolierter Test scheitert vorher, danach prüft die echte Player-Fixture zwei inerte Frames für JavaScript- und Leerwerte. Gültige HTTP(S)- und relative Ziele bleiben erhalten. |
| P0 | Ein vor dem Abmelden gestarteter Passwortwechsel behält seine alte Identität; andere Sitzungen bleiben nach einem Passwortwechsel gültig. | Sitzung nach dem Body und unter derselben SQLite-Schreibsperre wie Passwortänderung erneut prüfen. Andere Sitzungen atomar beenden und nur die geprüfte aktuelle Sitzung erhalten. Zwei neue Regressionen scheitern vor dem Fix: gehaltener Request über Logout und zweite Sitzung nach Passwortwechsel. Native CMS-Konten bleiben bei der jeweiligen CMS-Kontoverwaltung. |
| P0 | Standalone prüft den leeren Benutzerbestand vor dem asynchronen Lesen des Setup-Requests. Zwei Anfragen können diese Prüfung gleichzeitig passieren. | Ersten Administrator innerhalb einer SQLite-Schreibtransaktion erzeugen und Bestand dort erneut prüfen. HTTP-Regression hält einen zweiten Setup-Request über die abgeschlossene Erstregistrierung hinweg; er muss mit 409 scheitern und genau ein Konto zurücklassen. |
| P1 | Große Auth-Payloads und wechselnde Benutzernamen umgehen eine rein namensbezogene Begrenzung. | Auth-Bodies auf 16 KB begrenzen; zusätzlich nach 50 fehlgeschlagenen Anmeldungen je Socketadresse für das bestehende 15-Minuten-Fenster begrenzen. Bestehende Sitzungen und Player-Abfragen bleiben nutzbar. Hinter einem Reverse-Proxy können mehrere Benutzer dieselbe Socketadresse und damit dieselbe Zusatzgrenze teilen; keine frei eingesandten Forwarded-IP-Header vertrauen. |
| P0 | CrewBrain verwendet einen benutzerdefinierten API-Key-Header; automatisches Fetch-Redirect kann ihn an einen anderen Ursprung weiterreichen. Native HTTP-Weiterleitungen konnten ebenfalls vertrauliche Header/Formulare weiterleiten. | Manuelle, maximal drei Weiterleitungen ausschließlich innerhalb des konfigurierten Ursprungs. Fremdhost, anderer Port, TLS-Downgrade und URL-Benutzerinformationen abweisen. Lokale Zwei-Server-Fixture reproduziert die Weitergabe vor der Korrektur; Node-/PHP-/Adaptertests prüfen das Ausbleiben weiterer Requests. |
| P0 | Player-Beschilderung setzt einzelne Texte, Farben, Logoattribute und Styles ungefiltert in HTML ein. Eingebettete Webseiten akzeptieren ausführbare URL-Schemata. | Texte/Attribute kontextgerecht escapen, JavaScript-URLs durch `about:blank` ersetzen. Der alte Renderer erzeugt mit dem lokalen Testpayload injizierte DOM-Attribute; Chromium/WebKit prüfen gespeicherte präparierte Slides in allen drei Transporten ohne injizierte Elemente/Attribute oder JavaScript-Fehler. |
| P1 | Externe Antworten und Streams können vor der Prüfung vollständig gepuffert werden; übergroßes Retry-After kann Requests lange blockieren. | JSON auf 10 MB, Token-/Fehlerantworten teilweise auf 1 MB, Binär-/native Antworten auf 20 MB begrenzen. Deklarierte und gestreamte Größen prüfen, übergroße Streams abbrechen/schließen, Wiederholungswartezeiten auf 3 Sekunden deckeln. Explizit konfigurierte private easyjob-Netze und TLS-Optionen bleiben erhalten. |
| P1 | Gleichzeitige easyjob-Aufrufe können denselben Login/Token-Refresh mehrfach starten. | Laufende Token-Anfrage teilen, bereits ersetzten Token nicht erneut erneuern und fehlgeschlagene Promises wieder freigeben. Zehn parallele Aufrufe mit abgelehntem altem Token erzeugen genau einen Login und einen Refresh. Native Redirects teilen ein gemeinsames Zeitbudget statt es pro Weiterleitung zurückzusetzen. |
| P1 | Unstrukturierte Provider-Fehler können den eigenen Schlüssel wiederholen; Kürzen vor der Schwärzung kann Schlüsselpräfixe offenlegen. | Bekannte Credentials samt URL-kodierter Form vor der Kürzung entfernen; typische Muster zusätzlich schwärzen. Regression mit einem über 300 Zeichen langen künstlichen Schlüssel in Node und PHP. |
| P1 | Neue Mediengestalter-Rolle benötigt Objektprüfungen, sonst reicht ein direkter API-Aufruf gegen eine fremde Slide-ID. | Additive Eigentümer-/Freigabeverwaltung, begrenzte Endpunktliste und aktuelle Objektprüfungen. Getrennte Append-Aktion erhält fremde Einträge und Kanalparameter. Werbe-Tags dürfen keine Freigabe umgehen; Berechtigung und Werbung verwenden denselben normalisierten Tag-Vergleich. Partielle CMS-Updates prüfen auch geerbte Tags. Menükarten bleiben ausschließlich über die gesonderten Küchenrechte bearbeitbar, auch bei übereinstimmendem Eigentümer. Siehe [Rollenvertrag](../roles-and-channel-access.md). |
| P1 | Jede Sitzung/API-Authentifizierung aktualisiert Nutzungszeitstempel; CMS speichert auch unveränderte API-Nutzungsdaten. | Zeitstempel höchstens einmal pro Minute schreiben, unveränderten CMS-Zustand nicht speichern. Token/Sitzung bei jedem Request erneut prüfen. 100 Sitzungs- plus 100 API-Prüfungen verursachen im Repository-Test nach dem ersten Zeitstempel keine weiteren Writes; Widerruf, Rollenänderung und Ablauf bleiben wirksam. |
| P1 | WordPress-Kopfzeile berücksichtigt bei 320 px weiterhin eine ausgeblendete Menüspalte; DWD-Felder erzwingen durch ihren Inhalt zu breite Gridspalten. | CMS-Kopfzeile auf zwei Spalten umstellen, DWD-Felder mit `min-width:0` und begrenzter Breite sowie mobiler `minmax(0,1fr)`-Spalte darstellen. |
| P2 | Änderungen des URL-Hashes wechseln die sichtbare Ansicht nicht zuverlässig. | Hashwechsel mit der berechtigten Navigation synchronisieren; unbekannte/gesperrte Ansichten erhalten keine zusätzlichen Rechte. |

Endgültiges Löschen bleibt administrativ. Die automatische Sicherheitsprüfung
hat die Erweiterung eines allgemeinen Löschpfades abgewiesen, weil dessen
irreversibler Datenbereich nicht eindeutig genug abgegrenzt war. Die Korrektur
bewahrt diese bestehende Schutzgrenze; Löschregressionen werden nicht gelockert.

## Abnahme

- `pnpm lint`, `pnpm test`, `pnpm test:release`, `pnpm test:php`,
  `pnpm build:platforms`, Paket-/Versions- und native CMS-Prüfungen.
- `tests/content-access.test.ts`, `content-access-http.test.ts` und
  `content-access-test.php`: Besitzerfälschung, fremde Versionen/Slides,
  Administrator-/Redakteurgrenzen, Delegation, aktuelle Freigabe, Archiv,
  Wiederherstellung, wiederholtes Hinzufügen und Erhalt fremder Einträge.
- `tests/provider-http*.{ts,php}`: sichere Redirects, abgewiesene Fremdziele,
  begrenzte Bodies/Streams, native Transportoptionen und Fehlerschwärzung.
- `python tests/security-mobile-browser.py`, zusätzlich mit
  `KIOSKY_BROWSER_ENGINE=webkit`: acht Arbeitsbereiche, Dialoggrenzen,
  erreichbares Speichern, Kanalfreigaben, eigenes Slide-Speichern und präparierte
  Playerdaten. Jeweils 320×640, 390×844, 412×915, 768×1024, 820×1180,
  1024×768 und 844×390; drei gemeinsame UI-Transporte. Das ersetzt keine
  Installation auf einem physischen iPhone/Android-Gerät oder nativen CMS.
- Bestehende Event-/Editor-, Schrift- und Medienfreigabe-Regressionen prüfen
  Drag/Resize/Löschen aller 13 Elementtypen, Touch, echte Schriftmetriken,
  Speicherung, Offlinecache und Vorschau/Player.
- Medienfreigabe-Test: 200 Medienwechsel, Decoder-/Iframe-Freigabe geprüft;
  der lokale Chromium-JS-Heap blieb annähernd konstant. Kein Test des echten
  Windows-Laufzeitprozesses oder 24-Stunden-Nachweis.
- Compositor-Fixture: beim vorhandenen optimierten Ticker keine zusätzlichen
  Layouts im Messfenster, identische Bewegung und keine DOM-Mutationen bei
  100 unveränderten Uhraktualisierungen. Der isolierte Test bindet wieder die
  aktuellen Sichtbarkeits-/Zeitzonenhelfer ein.

Testergebnisse und endgültige GitHub-Gates sind im zugehörigen Release-PR zu
protokollieren. Diese Liste beschreibt Prüfumfang und lokale Nachweise, keine
pauschale Sicherheitszertifizierung oder Garantie ruckelfreier Hardwareausgabe.

## Verbleibende Betriebs- und Abhängigkeitsthemen

- Haupt-Lockfile: Audit vom 9. November ohne Befund. Optionaler
  `update-service`: weiterhin 19 mittlere, keine hohen/kritischen Meldungen.
  `pnpm audit` aus einem Verzeichnis ohne eigenes pnpm-Lockfile kann den
  Hauptgraphen prüfen; für diesen Dienst ausdrücklich
  `npm audit --prefix update-service` verwenden.
- Automatisch angebotene Fixes enthalten rückwärtsgerichtete Versionssprünge
  (u. a. Drizzle/Vinext). Diese nicht ungeprüft erzwingen. Ein Dependency-/CI-
  Change benötigt gemäß Repository-Regeln eigenen Owner-Review und Tests des
  Dienstes. Keine Audit-Schwelle oder Sicherheitsprüfung wurde abgesenkt.
- Plesk-Header/Cookies, Logkonfiguration, MFA für native Administratoren,
  Schlüsselablage und ein isolierter vollständiger Backup-Restore erfordern
  Zugang zum tatsächlichen Betrieb. Hier unverändert.
- Persistenter Circuit Breaker, zentrale Importqueue und langfristige Messung
  auf realer Playerhardware bleiben gesonderte Architektur-/Betriebsschritte.
  Begrenzte Retries allein werden nicht als vollständiger Circuit Breaker bezeichnet.

Die übergeordnete [Betriebsroadmap](security-performance-resilience-2026-11-09.md)
bleibt für Wiederherstellung, Alarmierung und Bereitschaft gültig. Rollenangaben
dieses älteren Berichts werden durch den neuen Rollenvertrag ergänzt.
