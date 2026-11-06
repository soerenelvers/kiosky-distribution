## [3.20.0] – 2026-11-05

- Unter `/player` modellbezogene Raspberry-Pi-Images für Zero 2 W und Pi 3/4/5 sowie eine Konfigurationsdatei mit der Browser-Player-Adresse der jeweiligen Installation anbieten. Separate unveränderliche Beta-Veröffentlichung mit Prüfsummen und Quellnachweis; Hardwareabnahme steht aus. Gemeinsame Downloadseite für Standalone, WordPress und TYPO3.

- Windows-Player mit eigenem Win32-Fenster und eingebetteter WebView2-Engine statt externem Edge-/Chrome-Aufruf. Offiziellen Loader und Runtime-Installer einbetten, getrenntes Datenprofil, begrenzte Wiederherstellung nach Browser-/Rendererfehlern und datensparsame Ereignisprotokolle ergänzen. Native Windows-Abnahme einschließlich Profilübernahme und gezielter Wiederherstellung in die verpflichtende CI aufnehmen.
- Medien beim Slidewechsel und beim Schließen der Vorschau ausdrücklich entladen: Video-/Audio-Decoder stoppen, Quellen lösen und Iframes zurücksetzen. Gemeinsame Oberfläche für Standalone, WordPress und TYPO3.
- Vollbildbrücke für das eingebettete Fenster ergänzen; bestehende Browser-Player und deren Erweiterung bleiben unterstützt.
