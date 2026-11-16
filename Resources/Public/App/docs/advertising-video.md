# Videowerbung (ab 3.46.0)

## Veranstaltung

Im Veranstaltungseditor gibt es zusätzlich zum Bild das optionale Feld
**Werbevideo · Link oder Mediathek**. Eine direkt erreichbare Videodatei verlinken
oder **Video auswählen / hochladen** öffnen. WordPress nutzt seine Mediathek,
TYPO3 seine native Medienauswahl und Standalone die eigene Medienbibliothek.

Die Quelldauer wird im Browser aus den Videometadaten gelesen und zusammen mit
der Veranstaltung gespeichert. Sie kann bei nicht lesbaren Metadaten ersatzweise
manuell eingetragen werden. Ohne gültige Dauer lässt sich ein neues Werbevideo
nicht speichern. Zulässig sind endliche Videos bis 24 Stunden; Video-Buchungsseiten
oder YouTube-Seiten sind keine direkten Videodateien. Für breite Player-Kompatibilität
MP4 mit H.264 verwenden. Die Uploadgrenzen des jeweiligen Systems gelten weiter.

Das Video ersetzt **denselben dynamischen Bildplatzhalter** der Werbevorlage:
Position, Größe, Zuschnitt/Einpassen, Zoom, Drehung und die übrigen Texte, QR-Codes
und Stempel bleiben erhalten. Dies gilt für Querformat und Hochkant. Statische
Logobilder werden nicht ersetzt. Falls die Vorlage bisher keinen dynamischen
Bildplatzhalter hat, gilt die bestehende automatische Hintergrundfläche.

Das Veranstaltungsbild bleibt getrennt erhalten und erscheint weiterhin im
Website-Kalender und in Veranstaltungsübersichten. Es dient, wenn vorhanden, auch
als Poster beim Laden des Videos. **Video entfernen** stellt die bisherige
bildbasierte Veranstaltungswerbung wieder her. Die Änderung mit der Veranstaltung
speichern. Veranstaltungsduplikate, Veranstaltungsvorlagen und Systemumzüge
übernehmen die Videoangaben.

## Selbst angelegte Werbeslides und Zwischenslides

Ein Video im Slide-Editor hinzufügen oder per Bulk-Medienimport übernehmen. Im
Editor wird die ausgelesene Quelldauer gespeichert; der Bulk-Import übernimmt
vorhandene bzw. beim Import gemessene Metadaten. Den Slide veröffentlichen und
mit **Werbung**, **Werbung quer** oder **Werbung Hochkant** versehen oder dem
Werbekanal ausdrücklich hinzufügen.

In den automatischen Werbekanälen überschreibt die Videolänge die gewöhnliche
Werbe-Slidedauer und eine ältere gespeicherte Reihenfolgendauer. Bei zugeschnittenen
Videos gilt `Trim-Ende − Trim-Start`, ansonsten `Quelldauer − Trim-Start`.
Mehrere parallel sichtbare Videos verwenden die längste abspielbare Videospur.
Dies gilt auch für Video-Zwischenslides; deren Einfügeabstände und Werbeplatz-Zählung
ändern sich nicht. Normale Bild-/Textwerbung behält ihre konfigurierte Dauer.

Bruchteile von Sekunden und Clips unter drei Sekunden werden in Player und
Kanalvorschau berücksichtigt. Außerhalb der automatischen Werbekanäle behalten
manuell konfigurierte Kanäle ihre eigene Dauer und ihre bestehende Zeitsteuerung.

Wenn eine ältere Slide keine auslesbare Videolänge besitzt, bleibt zunächst die
bisherige Dauer erhalten. Die Slide einmal im Editor öffnen, die Metadaten laden
lassen und speichern. Bei defekten oder gesperrten Videoquellen kann kein Browser
zuverlässig die Dauer oder Wiedergabe ermitteln; dafür stehen Player-Diagnosen zur
Verfügung. Metadatenabrufe sind zeitlich begrenzt und laden nur im Browser; der
Server ruft keine eingegebenen Video-URLs ab.

## Daten und Prüfungen

Migration `052_event_advertising_video.sql` ergänzt ausschließlich
`events.advertising_video_json`. Das Kalenderbild und vorhandene Zeitpläne werden
nicht geändert. Der CMS-State nutzt das Feld `advertisingVideo` mit `src`,
`durationSeconds`, optionaler `mediaAssetId` sowie `trimStart`/`trimEnd`.

`pnpm test` prüft Platzhaltergeometrie, Node-/PHP-Parität, Videos und Trims,
exakte kurze Laufzeiten, native CMS-API-Ausgabe, Entfernen des Videos und
Systemumzüge. `python3 tests/advertising-video-browser.py` prüft eine tatsächlich
decodierte MP4-Datei, Metadatenerkennung, externe Links, native Picker-Callbacks,
Persistenz, beide Werbeformate und den tatsächlichen Player-Platzhalter in Chromium;
`KIOSKY_BROWSER_ENGINE=webkit` nutzt zusätzlich WebKit. Native CMS-Picker-Callbacks
werden im Browser isoliert simuliert; die CMS-Domain wird separat über beide
nativen PHP-APIs getestet.
