# Flüssige Wiedergabe und Bedienung

Ab 3.22.1 verwenden Standalone, WordPress und TYPO3 dieselben Optimierungen:

- Lauftexte animieren ausschließlich `transform`. Die bisher pro Bild animierten
  Layoutwerte `left`/`right` entfallen. Richtung, Laufweg und Dauer bleiben gleich.
- Ziehen, Vergrößern und Drehen im Editor verarbeiten den letzten Eingabestand
  einmal je Bildschirm-Frame. Beim Loslassen wird die letzte Bewegung vor dem
  Speichern übernommen; abgebrochene Touch-Gesten lösen ihre Listener.
- Automatisches Einpassen von Texten fasst Resize-/Render-Anfragen zusammen.
  Schriftwechsel werden weiter berücksichtigt; entfernte Slides geben Observer
  und ausstehende Frame-Aufträge frei.
- Uhren und Countdowns ändern das DOM nur bei einer tatsächlichen Änderung.
  Unveränderte Warnungsdesigns werden beim Hintergrundabruf weiterverwendet.
- Der Player lädt höchstens zwei Bilder des nächsten Slides vor. Explizites
  Dekodieren ist auf insgesamt 4.194.304 Pixel begrenzt (etwa 16 MiB RGBA).
  Bei gemeldetem Gerätespeicher bis 1 GiB gilt ein Viertel dieses Budgets.
  Videos/Iframes werden nicht vorab gestartet. Datensparmodus oder abgeschalteter
  Display-Cache unterbinden diese zusätzlichen Bildabrufe.

## Reproduzierbare Prüfung

`python3 tests/playback-performance-browser.py` prüft mit Chromium Layout-/Style-
Neuberechnungen, identische Laufwege beider Richtungen und unveränderte Uhren.
Im isolierten Test mit sechs Tickern: zuvor etwa 90 Layout-Neuberechnungen in
1,5 Sekunden, danach 0. Das ist eine Messung des Testaufbaus, keine allgemeine
Bildratenzusage.

`tests/editor-media-alignment-browser.py` prüft Maus-/Touch-Bedienung, Medien-
Geometrie und Vorschauen über alle drei Plattformtransporte.
`tests/player-stability-browser-smoke.py` prüft Hintergrundabrufe, unveränderte
Warnungsdesigns, Speicherfehler und Offline-Wiedergabe. Unit-Tests prüfen Frame-
Bündelung, letzten Eingabestand, Observer-Abbau und begrenztes Bildvorladen.

Hardware, Videoauflösung/-codec, parallele Player, Netzwerk und eingebettete
Fremdseiten beeinflussen weiterhin die erreichbare Bildrate. Für den Dauerbetrieb
repräsentative Inhalte auf dem vorgesehenen Gerät prüfen; insbesondere Pi Zero 2 W
und ältere Windows-PCs benötigen passende Medienauflösungen.

Die Optimierungen kommen mit dem Systemupdate. Bereits geöffnete Browser-Player
laden neue Webdateien über ihre bestehende Updateprüfung; native Kopplungen und
Autostart-Einstellungen bleiben erhalten.

## Direkte Übergänge und Standortfarbe (3.26.0)

Unter **Displays → Standorte → Standort bearbeiten → Display-Hintergrundfarbe**
wird die gemeinsame Grundfarbe aller Displays dieses Standorts gewählt. Ohne
Standort beziehungsweise bei bisherigen Standorten gilt Schwarz (`#000000`).
Eigene Slide-Hintergründe bleiben erhalten; die Standortfarbe füllt freie Flächen.
Ein laufender Player übernimmt Farbänderungen beim nächsten Zustandsabruf, ohne
dafür den Kanal von vorn zu starten. Die Farbe wird auch offline gespeichert.

Überblenden, Schieben und Zoom zeigen die nächste Slide direkt über der bisherigen
Slide; es gibt keine vorgelagerte Ausblendphase und keine Zwischenfarbe. Die Wahl
am Kanaleintrag beziehungsweise die geerbte Kanalvorgabe gilt weiter. „Ohne
Übergang“ schaltet direkt um; reduzierte Bewegung wird berücksichtigt. Auch die
Kanalvorschau verwendet denselben Ablauf. Transparente Slides zeigen ihre
Standort-Grundfarbe, sobald die vorherige Slide ausgeblendet ist.

Die ausgehende Slide bleibt mit ihrem echten DOM angeheftet, damit Iframes ihre
Browserumgebung behalten. Eingehende Bilder/Videos/Iframes dürfen bis zu 1000 ms
laden, während die alte Slide sichtbar bleibt; anschließend dauert der Übergang
450 ms. Höchstens eine ausgehende Slide existiert zusätzlich. Alte Medien werden
dabei stumm und nach dem Übergang freigegeben. Schnelle Wechsel, Vorschauende und
sofortige Inhaltsänderungen räumen auch ausstehende Übergänge auf. Die Zeitplanung
und der gemeinsame Wiedergabezeitpunkt bleiben maßgeblich.

`python tests/player-crossfade-browser.py` prüft alle drei UI-Transporte inklusive
Pixelvergleich gegen einen absichtlich grünen Standort-Hintergrund, Medienabbau,
Iframe-Kontexterhalt, langsamen Bildern, schnellen Wechseln, reduzierter Bewegung
und mobiler Standort-Farbwahl. Node/PHP-Prüfungen sichern Speicherung, Alt-Clients,
Standortwechsel, Validierung und Export/Import. Migration 043 ergänzt ausschließlich
die Standalone-Spalte `locations.player_background_color`; CMS-Daten bleiben im
vorhandenen Zustandsspeicher. Älterer Code ignoriert diese zusätzliche Spalte.

## Verwaltungsoberfläche und mobile Bibliothek (3.46.3)

Parallele identische Leseabrufe teilen nur den laufenden Request; abgeschlossene
Antworten werden nicht dauerhaft gecacht. Veraltete Sitzung-/Katalogantworten
werden verworfen. Große Auswahlen verwenden lineare ID-Sets, Videominiaturen
starten keine Videometadatenabrufe. Touch-Auswahl und Bibliotheksaktionen erhalten
mindestens 44-Pixel-Flächen. Details, Prüfumfang und offene Punkte:
[Stabilitäts- und Bedienungsprüfung](reviews/stability-usability-2026-11-17.md).

## Bedarfsgerechte Zugriffe (3.48.1)

- Standalone löst Medien im Player und in der Ausgabevorschau über indizierte
  Einzelzugriffe auf. Dieselbe ID und fehlende IDs werden je Antwort nur einmal
  abgefragt. Textausgaben benötigen keinen Medien-Datenbankabruf. Kein Cache
  über Requestgrenzen: verschobene/geänderte/gelöschte Dateien werden beim nächsten
  Zustandsabruf wieder geprüft. Die vollständige Mediathek bleibt in der Verwaltung
  verfügbar. Simulations-Snapshots behalten ihren bestehenden unveränderlichen Bestand.
- WordPress und TYPO3 überspringen die Mediathek für den CMS-Anmeldestatus und die Einstellungen
  `features`, `events`, `public-calendar`, `menus`, `menu-share` und `player-health`.
  Vorhandene Medienreferenzen bleiben unverändert; diese Aufrufe starten keine
  historische Medienmigration. Werbe-/Schriftverwaltung und Medienauswahl behalten
  ihre eigenen Zugriffe und Berechtigungen.
- Die aufgeklappte aktuelle Ausspielung lädt Karten im sichtbaren Bereich plus
  einer Bildschirmhöhe Vorlauf. Sichtbare Matrizen laden ihre zugehörigen Displays.
  Höchstens zwei Vorschauabrufe laufen gleichzeitig; Scrollen lädt nach. Offene
  Bearbeitungsdialoge halten weitere Vorschauabrufe an. Bereits beim Server
  eingetroffene Aufrufe können dort noch zu Ende laufen.
- Die Einstellungsübersicht lädt Werbe- und Kalendereinstellungen erst beim Öffnen
  des jeweiligen Bereichs. Damit entfällt auch die vorzeitige Mediathek-Abfrage.
- Die Überwachung fragt periodisch nur in der Displayansicht bei offenem Panel ab.
  Automatische Display-/Warnungsansichten aktualisieren sich nicht in versteckten
  Tabs oder über geöffneten Dialogen. Manuelles Aktualisieren bleibt möglich.
  Lizenzprüfungen und die normalen Player-/Heartbeat-/Befehlsintervalle bleiben erhalten.

Nachweise: `tests/playback-media-performance.test.ts` prüft eine Bibliothek mit
mehr als 2.500 Medien gegen tatsächlich verwendete Referenzen, Nullabrufe bei Text,
fehlende IDs sowie Dateiänderungen und Löschung. `tests/cms-media-test.php` prüft
Lesen/Schreiben der sechs Einstellungsbereiche bei gesperrtem Medienkatalog,
Medienerhalt und unveränderte Rechte. `tests/dwd-load-test.py` kombiniert fünf
Player, einen absichtlich langsamen Provider und zwanzig Einstellungsänderungen.
`tests/resource-efficiency-browser.py` prüft 40 Displays, sichtbare Vorschauen,
Nachladen beim Scrollen und ruhende Hintergrundabfragen in allen drei UI-Transporten.

Die Messungen sind isolierte Regressionstests, keine Zusage für jedes Hosting.
Plesk-PHP-Worker, Datenmenge, SQL-Latenz, Netz und Mediencodec bleiben relevant.
Produktive PHP-FPM-/Datenbank-/Browser-Profile wurden hier nicht erhoben. Es gibt
keine Schema-/Datenmigration, Serverkonfigurationsänderung oder neue Abhängigkeit.
Rollback auf 3.48.0 benötigt keine Datenrückmigration. Nach dem Update die
Verwaltungsoberfläche und Player einmal neu laden.
