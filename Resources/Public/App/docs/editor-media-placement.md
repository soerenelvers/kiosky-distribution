# Medien platzieren und Elemente ausrichten

Im Slide-Editor stehen bei Bild- und Videoelementen unter **Medienplatzierung** drei Darstellungen bereit:

- **Inhalt beschneiden** füllt den Elementrahmen und schneidet überstehende Teile ab.
- **Inhalt einpassen** zeigt den gesamten Inhalt bei 100 % Zoom; bei unterschiedlichen Seitenverhältnissen bleiben freie Ränder.
- **Originalgröße anzeigen** verwendet die natürliche Bild-/Videogröße in den Designkoordinaten der Slide. Editor, Miniatur, Kanalvorschau und Player skalieren diese gemeinsam. Die Referenzbreite beträgt wie bei der bisherigen Slide-Typografie 1920 Pixel im Querformat und 1080 im Hochformat. Es handelt sich nicht um einen festen physischen Pixelmaßstab auf jedem Display.

**Zoom** reicht von 10 bis 400 Prozent. Horizontal/Vertikal wählen den Inhaltsausschnitt beziehungsweise die Ausrichtung im Elementrahmen. **Zurücksetzen** stellt die bisherige Darstellung wieder her: Beschneiden, 100 %, mittig; PDF-Seiten bleiben standardmäßig eingepasst. Breite/Höhe, Drehung, Deckkraft, Innenabstand und Eckenradius gelten weiterhin für den Elementrahmen. Die Mediendatei wird nicht verändert. Eine zusammenhängende Reglerbewegung zählt als ein Rückgängig-Schritt. Beim Einstellen wird das Videoelement weiterverwendet, sodass die Wiedergabe nicht neu geladen wird. Stummschaltung und gespeicherte Schnittzeiten bleiben erhalten.

Die Einstellungen liegen additiv im Elementdokument: `mediaFit` (`cover`, `contain`, `none`), `mediaZoom`, `mediaX`, `mediaY` und gegebenenfalls `mediaNaturalWidth`/`mediaNaturalHeight`. Für ältere Slides bleiben Cover/Contain und die bisherigen Standardwerte gültig. Bei externen oder älteren Medien ohne Dimensionsangabe liest der Browser Bild- beziehungsweise Video-Metadaten. Für die reine Darstellung wird kein zusätzlicher API-Schreibzugriff ausgelöst. Export, Import, Duplikate und Vorlagen übernehmen die Felder im bestehenden Dokumentformat; eine Datenbankmigration ist nicht nötig.

## Automatische Hilfslinien

Beim Verschieben über den Verschiebegriff rasten Elemente an den Slidekanten, der horizontalen/vertikalen Mitte sowie Kanten und Mittelpunkten anderer Elemente ein. Passende Linien werden während des Verschiebens angezeigt und anschließend entfernt. Die Toleranz beträgt acht Bildschirm-Pixel je Achse, unabhängig von Slideformat und Editorgröße.

Mehrfachauswahlen bleiben beim Verschieben zusammen. Die tatsächlichen gerenderten Begrenzungen einschließlich Drehung und CSS-Mindestgrößen werden berücksichtigt. Gesperrte Elemente bleiben stehen und können als Ausrichtungsreferenz dienen. **Einrasten** in der Editorleiste schaltet die Funktion aus oder ein; diese Browserpräferenz wird lokal gespeichert. **Alt** erlaubt für eine Bewegung freies Positionieren. Auf Touchgeräten stehen derselbe Verschiebegriff und der Einrasten-Schalter zur Verfügung. Numerische Positionsfelder und Tastaturverschiebung bleiben für genaue Korrekturen frei einstellbar. Das Einrasten gilt für Verschiebungen, nicht für Größenänderungen.

Hilfslinien werden nur als temporäre Editoroberfläche dargestellt und weder im Slide-Dokument gespeichert noch auf dem Player ausgegeben.

## Verifikation

`pnpm test` prüft Standardwerte, Wertebereiche, Mittellinien/Kanten, Alt-Ausnahme, Fangabstand und gedrehte Gruppen. Nach `pnpm build` führt `python tests/editor-media-alignment-browser.py` die Oberfläche über Standalone-, WordPress- und TYPO3-Transport mit echten Bild-/Videodimensionen aus. Dafür sind Playwright, Pillow, FFmpeg und Chromium unter `/usr/bin/chromium` nötig. Die Prüfung umfasst Bildpixel, Originalgröße, Video-Wiedergabeposition, Speichern, Miniaturen, Kanalvorschau, Player sowie Maus-/Touch-Ereignisse und Smartphone-/Tablet-Layouts. Die nativen CMS-Routen werden zusätzlich durch die verpflichtende CMS-CI geprüft.
