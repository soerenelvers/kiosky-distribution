# Full HD, UHD / 4K und Hochformat

Kiosky verwendet die gesamte verfügbare Playerfläche. Es gibt im gemeinsamen
Web-Player keine feste Full-HD-Obergrenze. Dies gilt für Standalone, WordPress
und TYPO3 sowie deren Desktop-Player und unterstützte Browser.

| Format | Querformat | Hochformat |
|---|---|---|
| Full HD | 1920 × 1080 | 1080 × 1920 |
| UHD / 4K (16:9 beziehungsweise 9:16) | 3840 × 2160 | 2160 × 3840 |
| DCI 4K (anderes Seitenverhältnis) | 4096 × 2160 | 2160 × 4096 |

## Einrichten

1. Im Betriebssystem die gewünschte Monitorauflösung und Ausrichtung einstellen.
   Bei mehreren Monitoren „Erweitern“ und je Monitor eine Playerinstanz verwenden.
2. In Kiosky unter **Displays → Bearbeiten** die Ausrichtung auswählen. Der Modus
   **Automatisch · Monitor / Browserfenster** übernimmt die verfügbare Fenstergröße.
3. Für feste Planungs-/Matrixmaße **Feste Zielauflösung** und eine Auflösungsvorlage
   wählen. Hochformat vertauscht die Maße automatisch. Benutzerdefinierte Breite
   und Höhe bleiben möglich. Vorhandene Displayeinstellungen werden nicht verändert.
4. Player im Vollbild starten. Die feste Zielauflösung ändert weder die
   Betriebssystemauflösung noch den HDMI-Modus und begrenzt das Playerfenster nicht.

Die 1920×1080-/1080×1920-Referenz im Slide-Editor ist eine Gestaltungsgrundlage,
keine Rastergrenze. Prozentpositionen und responsive Schriftgrößen skalieren mit
der tatsächlichen Slidefläche. Bewusst nicht responsive Pixel-Schriftgrößen
bleiben gemäß ihrer Einstellung unverändert. Für Originalgröße/Zoom von Medien
bleibt die bestehende Medienplatzierung maßgeblich. Scharfe Fotos und Videos
benötigen entsprechend hoch aufgelöstes Ausgangsmaterial; ein Full-HD-Video
enthält durch Hochskalieren keine zusätzlichen Bilddetails.

## HiDPI und Diagnose

Bei 200 Prozent Skalierung können auf einem UHD-Monitor 1920×1080 CSS-Pixel und
3840×2160 Rasterpixel zusammengehören. Die Displayübersicht, der Hinweis im
Bearbeitungsdialog und **Display identifizieren** unterscheiden dies ausdrücklich.
Das Raster ist aus Browsergröße × Pixeldichte berechnet; Browserzoom, Compositor,
Bildschirmspiegelung oder TV-Skalierung können die tatsächliche Panelauflösung
zusätzlich beeinflussen. Die Anzeige ist keine Messung des HDMI-Signals.

Layout, Heartbeat und Matrixberechnung verwenden weiterhin CSS-Koordinaten und
mischen sie nicht mit Rasterpixeln. Die vorhandene API bleibt kompatibel.
Der Windows-WebView2-Host ist per-monitor-v2-DPI-bewusst und behandelt DPI-Wechsel
mit erneuter Anpassung an den zugeordneten Monitor. Es wird kein globaler
Browser-Skalierungsfaktor erzwungen. Die neue Windows-Laufzeit wird über die
vorhandene Player-Update-Routine verteilt; nach dem Laufzeitupdate neu starten.
Linux/Mint/Ubuntu und macOS verwenden weiterhin das native HiDPI-Verhalten ihrer
Browser. Eine live geänderte Fenstergröße passt auch automatische Slideausrichtung
und responsive Schriftgrößen ohne erneute Kopplung an.

## Hardware- und TV-Grenzen

- Unter **Displays → Bearbeiten → Ausgabe drehen** kann die gesamte Player-Ausgabe
  unabhängig je Display auf 0°, 90° links, 90° rechts oder 180° gestellt werden.
  Bei 90° werden die logischen Maße vertauscht; automatische Slides, Matrixsegmente,
  Warnungen und Identifikation folgen der Drehung. Sie wird beim nächsten Zustandsabruf
  übernommen und im Offline-Stand gespeichert. „Ausrichtung“ bleibt die Inhaltsauswahl.
  Die Einstellung dreht die Webausgabe, nicht das HDMI-Signal oder das Betriebssystem.
- Raspberry Pi 4/5 können geeignete UHD-Ausgänge anbieten; Kabel, Port, Firmware,
  Bildwiederholrate und Videocodec sind mitzuprüfen. Für den Zero 2 W bleibt Full HD
  das vorgesehene Ziel; dieses Update macht aus ihm keine garantierte UHD-Hardware.
- Samsung-Consumer-Tizen-Apps haben laut Samsung üblicherweise eine Full-HD-
  Anwendungsoberfläche, auch auf UHD-Panels. Videoflächen können modell-/codecabhängig
  UHD unterstützen. Kiosky erfindet hierfür keine native 4K-UI-Freigabe und verändert
  den unterstützten 1920-Pixel-TV-Viewport nicht. Eine garantierte native UHD-
  Oberfläche beziehungsweise Hochformat-Ausgabe erfordert geeignete Hardware und
  deren Plattformunterstützung, zum Beispiel einen Desktop-/Pi-Player mit gedrehtem
  UHD-Ausgang. Die gemeinsame responsive Ausgabe bleibt auch für Tizen enthalten.

Samsung-Quellen:
[General Specifications](https://developer.samsung.com/smarttv/develop/specifications/general-specifications.html),
[Managing Screen Resolution](https://developer.samsung.com/smarttv/develop/guides/fundamentals/managing-screen-resolution.html),
[4K / 8K UHD Video](https://developer.samsung.com/smarttv/develop/guides/multimedia/4k-8k-uhd-video.html).

## Abnahme

`python3 tests/player-uhd-browser.py` prüft alle drei gemeinsamen Plattformtransporte
mit erzeugten Medien: UHD quer/hoch bei Pixeldichte 1 und 2, tatsächliche
Screenshot-Raster und farbige Bildschirmränder, responsive Texte, Größen-/
Ausrichtungswechsel, Identifikation und gespeicherte Vorlagen. Erzeugte echte
3840×2160- und 2160×3840-MP4s werden im Videoelement decodiert/abgespielt.
Mit `KIOSKY_BROWSER_ENGINE=webkit` wird zusätzlich WebKit verwendet. Für eine
reine Layout-/HiDPI-Abnahme ohne Videodecoderprüfung kann ausdrücklich
`KIOSKY_UHD_VIDEO_CHECK=0` gesetzt werden; der Ergebnisbericht weist den ausgelassenen
Videotest aus. Die lokale WPE-WebKit-Umgebung erkennt UHD-H.264, spielt es jedoch
nicht zuverlässig fort. UHD-Videowiedergabe ist hier nur mit Chromium bestätigt;
die WebKit-Layoutabnahme ist davon getrennt. Die nativen
CMS-Installations-/Upgradeprüfungen laufen gesondert in CI. Der Windows-CI-Test
prüft die DPI-Awareness des tatsächlich ausgelieferten PE-Fensters sowie die
Vollbildbehandlung einer DPI-Benachrichtigung.

Browseremulation ersetzt keine Abnahme mit physischen UHD-Monitoren, gemischten
Monitor-DPI-Werten, den vorgesehenen TV-Geräten oder einem Hardware-Videodecoder.
Keine feste Bildrate oder unterbrechungsfreie 4K-Wiedergabe auf beliebiger Hardware
wird zugesichert.

## Matrix-Standardinhalt

Im Matrixdialog kann der Standardinhalt ein Kanal oder ein fester Slide sein.
Die Matrix stellt diesen über ihre aktiven Mitglieder gemeinsam dar. Zeitpläne,
Presets und Warnungen behalten die bestehenden Prioritäten. Inaktive Kopien
reservieren keine Displays. Das Ändern des Inhalts erhält vorhandene Zuschnitte,
Versätze und Blenden; beim Aktivieren wird eine Doppelbelegung abgefangen.
