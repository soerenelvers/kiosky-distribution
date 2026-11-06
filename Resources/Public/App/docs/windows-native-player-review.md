# Windows-Player 3.0.0 / Kiosky 3.20.0 – Freigabe und Prüfung

Status: Der Eigentümer hat am 2026-11-05 den vorbereiteten Umbau einschließlich
Microsoft-SDK, Build-/CI-Anpassungen und isolierter Windows-Abnahme ausdrücklich
freigegeben. Die Veröffentlichung folgt erst nach erfolgreicher vollständiger CI.

## Anlass

`msedge.exe` beendet sich nach längerer Laufzeit mit `0xe0000008`. Chromium
verwendet diesen Code für Out-of-Memory. Das belegt einen fehlgeschlagenen
Speicherzugriff/-allokationsversuch, aber noch nicht dessen Ursache auf dem PC.
Arbeitsspeicher, Commit-Limit/Auslagerungsdatei, Browserfassung, Inhalte und andere
Programme müssen bei einer Geräteabnahme berücksichtigt werden.

Quellen:
- https://codereview.chromium.org/2130293003/diff/160001/base/process/memory.h
- https://learn.microsoft.com/en-us/troubleshoot/microsoft-edge/performance/edge-high-cpu-memory

## Vorbereiteter Umbau

- Eigenes natives Win32-Fenster mit WebView2, ohne Aufruf des Standardbrowsers.
- Microsoft WebView2 SDK **1.0.4314.35**, x64-Loader und offizieller Evergreen-
  Bootstrapper als Ressourcen der EXE. SDK-Quelle und SHA-256 stehen in
  `assets/launchers/vendor/webview2/provenance.json`. Der unveränderte Loader und
  Bootstrapper enthalten Microsoft-Authenticode-Signaturen; deren Windows-
  Vertrauenskettenprüfung hat die native Windows-CI bestanden.
- Fehlt eine geeignete stabile Runtime, fragt ein manueller Erststart nach der
  einmaligen Microsoft-Installation. Autostart installiert keine Laufzeit ohne
  Bestätigung. Keine Administratorrechte werden durch Kiosky angefordert.
- Eigener dauerhafter Datenordner unter dem vorhandenen adressgebundenen Profil.
  Die Übernahme der alten dedizierten Edge-LocalStorage-Daten kopiert diese nur
  bei geschlossenem Quellprofil; Originaldaten werden nicht gelöscht. Die echte
  Edge-zu-WebView2-Übernahme hat die native Windows-CI bestanden.
- Ein Playerfenster pro Benutzer, Benutzerautostart, Vollbild, F11 und eine auf
  die konfigurierte Origin begrenzte Vollbildbrücke. Fremde Top-Level-Navigation
  und neue Browserfenster werden nicht nach außen geöffnet. Web-Slides können
  weiterhin externe Inhalte in ihren Frames laden.
- Navigation/Renderer-/Browser-Prozessfehler führen zu begrenzten Wiederanläufen
  mit 1–30 Sekunden Pause. Fehlgeschlagene HTTP-Serverantworten werden wiederholt.
  Der native Host und das Datenprofil bleiben dabei erhalten.
- Decoder/Frames werden beim Slidewechsel ausdrücklich entladen. Ein lokaler
  Browser-Lasttest prüft diese gemeinsame Änderung ohne Windows-Installation.
- Protokoll mit Zeit, Fehlerart, verfügbarem RAM/Commit und Browser-Privatspeicher;
  keine URLs oder Kopplungsschlüssel. Rotation auf eine zusätzliche Protokolldatei.
- Optional `--software-rendering` / `--hardware-rendering`, um einen problematischen
  Grafiktreiber gezielt zu untersuchen. Der Standard bleibt Hardwarebeschleunigung.
- TLS, Sandbox und Windows-Sicherheitsfunktionen bleiben aktiv. Die äußere
  Kiosky-EXE ist weiterhin nicht Authenticode-signiert; nur die eingebetteten
  unveränderten Microsoft-Komponenten tragen deren Signaturen.

## Freigabe und native Abnahme

Der Eigentümer hat den SDK-, Build- und CI-Umbau am 2026-11-05 ausdrücklich
freigegeben. Die native Windows-Prüfung hat im CI-Lauf 42129302066 bestanden:
Microsoft-Signaturen, installierte Kopie und Benutzerautostart, Übernahme der
Kopplung aus einem geschlossenen dedizierten Edge-Profil, erneuter Start,
Vollbild/F11, Vollbildbrücke, 100 Video-/Iframe-Wechsel sowie Wiederherstellung
nach gezieltem Beenden eigener Renderer- und Browserprozesse.

Der Test läuft nur auf einem frischen GitHub-Windows-Runner. Er verwendet lokale
HTTP-Testseiten und deren Rückmeldungen; Debugging-Schnittstellen und Änderungen
an Browserrichtlinien sind nicht erforderlich. Er beendet ausschließlich seine
eigenen WebView2-Kindprozesse und räumt den eigenen Autostart-Eintrag wieder auf.
Der produktive Player aktiviert keine Debugging-Schnittstelle.

Vor Veröffentlichung müssen zusätzlich die vollständige CI und die vorgeschriebenen
CMS-Installations-/Upgradeprüfungen für den endgültigen Commit erfolgreich sein.

Ein CI-Kurztest ersetzt keinen Langzeittest auf dem betroffenen Windows-10-PC.
Ein vollständig ausgelastetes systemweites Commit-Limit oder ein defekter Treiber
kann auch eine eingebettete Chromium-Engine treffen. Dafür dienen Wiederherstellung
und Diagnostik; eine Zusage „kein Absturz mehr“ wäre derzeit nicht belegt.


## Lokaler Nachweis vor der Windows-Freigabe

- MinGW-x64-Build mit `-Wall -Wextra -Werror`: erfolgreich, native Vorlage gebaut.
- 317 Node-Tests einschließlich Komponenten-/Build-Hashes, Vollbildbrücke und
  Medienfreigabe: erfolgreich.
- PHP-, Release- und Automationstests sowie alle drei Plattformpakete: erfolgreich.
- Lokaler Chromium-Test: 200 Video-/Iframe-Wechsel, JavaScript-Heap nach GC von
  1.266.624 auf 1.272.260 Bytes. Keine verbliebenen Medienknoten im Testbereich.
  Privater Prozessspeicher war in der Sandbox nicht auslesbar; daraus wird keine
  Aussage über den nativen Windows-RAM-Verbrauch abgeleitet.
- Nicht ausgeführt: Installation der Microsoft-Runtime auf dem Windows-CI-Runner,
  echte Profilmigration nach WebView2, native Vollbild-/Absturztests, Windows-10-
  Langzeitprüfung und Veröffentlichung. Bestehende Windows-CI-Fixtures erwarten
  noch den bisherigen externen Browser und müssen nach Freigabe angepasst werden.


### Windows-CI ohne Debugging-Konfiguration

Die Abnahme verwendet die unveränderte Release-Konfiguration: lokale HTTP-
Testmeldungen statt Debugging-Port oder Registry-Overrides. Gezielte Prozess-
beendigungen betreffen ausschließlich WebView2-Prozesse des eindeutig adressierten
Testprofils und deren Kinder auf dem frischen Wegwerf-Runner. Andere Browser-
prozesse werden nicht beendet. So wird die normale Produktionseinstellung mit
abgeschalteter DevTools-Oberfläche geprüft.
