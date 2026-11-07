# Medien platzieren und Elemente ausrichten

Im Slide-Editor stehen bei Bild- und Videoelementen unter **Medienplatzierung** drei Darstellungen bereit:

- **Inhalt beschneiden** füllt den Elementrahmen und schneidet überstehende Teile ab.
- **Inhalt einpassen** zeigt den gesamten Inhalt bei 100 % Zoom; bei unterschiedlichen Seitenverhältnissen bleiben freie Ränder.
- **Originalgröße anzeigen** verwendet die natürliche Bild-/Videogröße in den Designkoordinaten der Slide. Editor, Miniatur, Kanalvorschau und Player skalieren diese gemeinsam. Die Referenzbreite beträgt wie bei der bisherigen Slide-Typografie 1920 Pixel im Querformat und 1080 im Hochformat. Es handelt sich nicht um einen festen physischen Pixelmaßstab auf jedem Display.

**Zoom** reicht von 10 bis 400 Prozent. Horizontal/Vertikal wählen den Inhaltsausschnitt beziehungsweise die Ausrichtung im Elementrahmen. **Zurücksetzen** stellt die bisherige Darstellung wieder her: Beschneiden, 100 %, mittig; PDF-Seiten bleiben standardmäßig eingepasst. Breite/Höhe, Drehung, Deckkraft, Innenabstand und Eckenradius gelten weiterhin für den Elementrahmen. Die Mediendatei wird nicht verändert. Eine zusammenhängende Reglerbewegung zählt als ein Rückgängig-Schritt. Beim Einstellen wird das Videoelement weiterverwendet, sodass die Wiedergabe nicht neu geladen wird. Stummschaltung und gespeicherte Schnittzeiten bleiben erhalten.

Die Einstellungen liegen additiv im Elementdokument: `mediaFit` (`cover`, `contain`, `none`), `mediaZoom`, `mediaX`, `mediaY` und gegebenenfalls `mediaNaturalWidth`/`mediaNaturalHeight`. Für ältere Slides bleiben Cover/Contain und die bisherigen Standardwerte gültig. Bei externen oder älteren Medien ohne Dimensionsangabe liest der Browser Bild- beziehungsweise Video-Metadaten. Für die reine Darstellung wird kein zusätzlicher API-Schreibzugriff ausgelöst. Export, Import, Duplikate und Vorlagen übernehmen die Felder im bestehenden Dokumentformat; eine Datenbankmigration ist nicht nötig.

## Automatische Hilfslinien

Beim direkten Ziehen eines Elements oder Verschieben über den Verschiebegriff rasten Elemente an den Slidekanten, der horizontalen/vertikalen Mitte sowie Kanten und Mittelpunkten anderer Elemente ein. Passende Linien werden während des Verschiebens angezeigt und anschließend entfernt. Die Toleranz beträgt acht Bildschirm-Pixel je Achse, unabhängig von Slideformat und Editorgröße.

Mehrfachauswahlen bleiben beim Verschieben zusammen. Die tatsächlichen gerenderten Begrenzungen einschließlich Drehung und CSS-Mindestgrößen werden berücksichtigt. Gesperrte Elemente bleiben stehen und können als Ausrichtungsreferenz dienen. **Einrasten** in der Editorleiste schaltet die Funktion aus oder ein; diese Browserpräferenz wird lokal gespeichert. **Alt** erlaubt für eine Bewegung freies Positionieren. Auf Touchgeräten stehen derselbe Verschiebegriff und der Einrasten-Schalter zur Verfügung. Numerische Positionsfelder und Tastaturverschiebung bleiben für genaue Korrekturen frei einstellbar. Das Einrasten gilt für Verschiebungen, nicht für Größenänderungen.

Hilfslinien werden nur als temporäre Editoroberfläche dargestellt und weder im Slide-Dokument gespeichert noch auf dem Player ausgegeben.

## Verifikation

`pnpm test` prüft Standardwerte, Wertebereiche, Mittellinien/Kanten, Alt-Ausnahme, Fangabstand und gedrehte Gruppen. Nach `pnpm build` führt `python tests/editor-media-alignment-browser.py` die Oberfläche über Standalone-, WordPress- und TYPO3-Transport mit echten Bild-/Videodimensionen aus. Dafür sind Playwright, Pillow, FFmpeg und Chromium unter `/usr/bin/chromium` nötig. Die Prüfung umfasst Bildpixel, Originalgröße, Video-Wiedergabeposition, Speichern, Miniaturen, Kanalvorschau, Player sowie Maus-/Touch-Ereignisse und Smartphone-/Tablet-Layouts. Die nativen CMS-Routen werden zusätzlich durch die verpflichtende CMS-CI geprüft.


## Elemente verschieben, skalieren und löschen ab 3.24.0

Alle Elementtypen lassen sich direkt auf der Arbeitsfläche ziehen. Auswählen zeigt
acht Eck-/Kantengriffe für Breite und Höhe. Die Griffe liegen über den Inhalten,
auch bei überlappenden Ebenen und eingebetteten Webseiten; kleine Elemente erhalten
einen größeren Griffbereich. Drehungen werden beim Skalieren berücksichtigt.
Die schwebende Leiste bietet Verschieben, Drehen und gezieltes Löschen. Auf
Touchgeräten funktionieren dieselben Gesten. Abgebrochene Gesten stellen die
ursprüngliche Geometrie wieder her; eine Bewegung zählt als ein Rückgängig-Schritt.

Jede Zeile der Elementliste bietet Löschen, Sperren/Entsperren und Ebenenreihenfolge.
Der gezielte Löschknopf entfernt genau diese Ebene, auch wenn sie gesperrt ist.
Rückgängig stellt sie wieder her. Eine Sperre schützt Verschieben, Größe und Drehung;
vorhandene Sperren bleiben erhalten. Neue Veranstaltungsfelder starten ungesperrt.
Text lässt sich per Doppelklick direkt bearbeiten oder im Eigenschaftenfeld ändern.

## Eventdaten mit Freitext und ausgewählten Details

Beim Element „Eventdaten“ ist „Freitext / Überschrift“ frei beschreibbar. Ein leeres
Feld blendet die Überschrift aus. Über „Angezeigte Details“ werden Titel, Untertitel,
Datum, Einlass, Beginn, Pausenzeiten, Ende, Aufbau, Spielort, Raum, Veranstalter,
Kategorie, Ticketstatus, Abendkasse und öffentlicher Hinweis einzeln gewählt.
Nicht vorhandene Werte werden bei dieser ausdrücklichen Auswahl ausgelassen.
Mehrzeiliger Freitext und öffentliche Hinweise bleiben mehrzeilig darstellbar.

Die Daten stammen weiterhin aus der Event-Auswahl der Slide (verknüpft, nächstes,
aktuelles oder heutiges Event). Die Auswahl bleibt beim Speichern, Duplizieren und
in Vorlagen erhalten und wird in Vorschau, Miniatur und Player gleich ausgewertet.
Bestehende Eventblöcke behalten ihren bisherigen Begrüßungstext und Zeitblock,
bis ihre Einstellungen verändert werden. Die zusätzlichen Dokumentfelder
`eventHeading` und `eventDetails` benötigen keine Datenbankmigration.

## Ticket-QR und Event-Tabelle

Ein „Eventfeld“ kann **Ticket-Link als QR-Code** anzeigen. Alternativ bietet das
QR-Code-Element die Quelle **Ticket-Link des Events**. Beide lesen den Link aus
dem für die Slide ausgewählten Event; die Modi verknüpft, aktuell, heute und
nächstes Event gelten auch hier. Ändert sich das Event oder sein Ticket-Link,
übernimmt der Player die Änderung bei seiner nächsten Inhaltsaktualisierung.
Ohne gültigen HTTP-/HTTPS-Link wird kein QR-Code ausgegeben. Ein früherer Link
oder der statische Beispiel-Link wird nicht verwendet.

Das Element **Event-Tabelle** stellt die unter „Angezeigte Details“ angehakten
Felder zeilenweise dar. Überschrift, Schrift, Größe und Position sind editierbar.
Bezeichnungen können abgeschaltet und leere Felder wahlweise ausgelassen oder
als Strich gezeigt werden. Pro angehaktem Feld lassen sich die Ausrichtung
(links, zentriert, rechts oder vom Element übernehmen) und „Wenn leer“ ändern:
Ausblenden lässt folgende Zeilen nachrücken; Platz freihalten erhält eine leere
Zeile; Ersatztext zeigt einen frei wählbaren Text. Beim Ticket-QR reserviert
„Platz freihalten“ dessen quadratische Fläche. Diese Optionen stehen auch im
Element „Eventdaten“ zur Verfügung und bleiben in Vorlagen und Duplikaten erhalten. Zur Auswahl stehen die öffentlichen Veranstaltungsdaten
inklusive Beschreibung, Bild, Ticket-Link, Ticket-QR und Abendkassenverfügbarkeit.
Interne Notizen und Synchronisationsdaten werden nicht an Player weitergegeben.
Auch „Eventdaten“ bietet diese zusätzlichen Felder an.

QR-Codes entstehen lokal ohne externe Dienste. Sie unterstützen bis zu 2953
UTF-8-Bytes; längere Links werden nicht abgeschnitten. Bei langen Links den QR-Code
entsprechend groß platzieren und am späteren Display mit einem Telefon prüfen.
Die SVG-Erzeugung verwendet einen auf 24 Einträge beziehungsweise eine Million
Zeichen begrenzten Cache. Vorlagen und Duplikate behalten die Event-Feldwahl;
eine Datenbankmigration ist für diese Ergänzungen nicht notwendig.

Die unabhängige QR-Prüfung `tests/qr-code-readback.py` vergleicht die Matrizen mit
`qrcode==8.2` und liest alle Testlinks mit `zxing-cpp==2.3.0` zurück (Pillow nötig).
`tests/event-ticket-table-browser.py` prüft Editor, Speichern und echten Player
mit allen drei UI-Transporten sowie Änderungen und fehlende Ticket-Links.

## Eventdaten mit Zeilenlayout (3.27.0)

Neue Eventdaten-Elemente öffnen direkt den Zeileneditor rechts. Bestehende Elemente
werden mit **Zeilenlayout bearbeiten** übernommen; die bisherige Detailauswahl
bleibt erhalten und kann über **Zur Detail-Liste** wieder verwendet werden.
Dasselbe gilt beim Bearbeiten von Vorlagen.

Jede Zeile bietet ein Textfeld. Eventfelder lassen sich aus „Eventfelder einfügen“
per Ziehen oder Antippen einsetzen. Beispielsweise zeigt
`Einlass: {{Einlass}} · Beginn: {{Beginn}}` beide Zeiten nebeneinander.
Freitext, mehrere Leerzeichen, Trennzeichen und Zeilenumbrüche können direkt im
Textfeld eingegeben werden. Platzhalter erscheinen zusätzlich als ziehbare
Bausteine; ihre Reihenfolge und Zielzeile lassen sich auch über Schaltflächen
beziehungsweise „In Zeile verschieben“ ändern. Zeilen können hinzugefügt, gelöscht
und mit Griff oder Pfeiltasten umsortiert werden. Bei längeren Listen scrollt die
Eigenschaftenleiste während des Ziehens am oberen oder unteren Rand mit.

Unter jedem Feldbaustein stehen Text davor/danach und das Verhalten bei fehlendem
Wert bereit. Diese Texte werden zusammen mit dem Feld ausgeblendet; direkt im
Zeilentext eingetragene Zeichen bleiben Freitext. Ganze leere Datenzeilen können
nachrücken, Platz freihalten oder einen Ersatztext anzeigen. Ticket-QR und Bild
sind ebenfalls als Platzhalter verfügbar. Die Event-Auswahl der Slide (verknüpft,
aktuell, heute oder nächstes Event) bleibt maßgeblich; interne Notizen sind keine
verfügbaren Platzhalter.

„Zeilenabstand“ gilt für alle Zeilen; „Abstand danach“ überschreibt ihn für eine
Zeile. Die Abstände in em skalieren mit der Schrift, auch auf 4K-Displays.
Zeilenhöhe, Ausrichtung und relative Schriftgröße sind einstellbar. Grenzen:
50 Zeilen pro Element und 100 Bausteine pro Zeile. Rückgängig, Duplikate, Vorlagen,
Vorschauen und Player übernehmen das Layout. Die Speicherung erfolgt ergänzend
im Slide-Dokument; eine Datenbankmigration ist nicht erforderlich.

`python tests/event-row-layout-browser.py` prüft Maus-/Touch-Ziehen, Abbruch,
Rückgängig, Vorlage, unabhängige Duplikate, gespeicherte Abstände und echten Player
über alle drei UI-Transporte. PHP-Vertragstests prüfen zusätzlich die unveränderte
Übernahme des Zeilenlayouts in die nativen CMS-Playerdaten.

## Bedingter Event-Stempel (3.27.0)

„Event-Stempel“ ist ein eigenes Element. Text, Schrift, Farbe, Position, Größe und
Drehung werden wie bei anderen Elementen bearbeitet. Umrandung oder Füllung und
die Textfarbe der gefüllten Variante sind wählbar.

Standardbedingung ist „Tickets ausverkauft“. In der Veranstaltung kann im Feld
„Ticketstatus / Restkarten“ beispielsweise „Ausverkauft“, „Nicht ausverkauft“,
„Karten verfügbar“ oder eine Restkartenzahl eingetragen beziehungsweise aus der
Vorschlagsliste gewählt werden. Auch importierte Angaben wie „Sold out“ und null
verbleibende Tickets werden erkannt. Die normale Eventdaten-Anzeige kann das
Feld weiterhin als Text ausgeben.

Weitere Bedingungen sind Absage, verfügbare Abendkasse, ein ausgefülltes/leeres
öffentliches Eventfeld oder ein Textvergleich. Ohne aufgelöstes Event bleibt der
Stempel auf dem Player unsichtbar. Die gestrichelte Editorvorschau hilft beim
Positionieren, wenn die Bedingung gerade nicht erfüllt ist. Die Event-Auswahl der
Slide und Vorlagenübernahme gelten auch für den Stempel. Statusänderungen kommen
über die normale Player-Aktualisierung an; der Stempel muss nicht neu angelegt
werden. Interne Notizen können nicht als Bedingung verwendet werden.

## Bedingte Texte (3.27.0)

Ein **Text**-Element auswählen und rechts **Text sichtbar, wenn …** einstellen.
Vorgabe ist **Immer sichtbar**. Beispiele: **Eventfeld ausgefüllt ist** mit
**Ticket-Link**, **Tickets nicht verfügbar / abgesagt**, **Abendkasse geöffnet**
oder **Abendkasse geschlossen**. Leere Felder sowie eigene Vergleichswerte
(entspricht / enthält, ohne Beachtung der Großschreibung) sind ebenfalls möglich.
Verwendet wird die Event-Auswahl der Slide, einschließlich aktuell/heute/nächstes
Event. Ohne passendes Event werden bedingte Texte ausgeblendet. Im Editor bleibt
eine blasse, weiterhin bearbeitbare Vorschau erhalten; der Player blendet das
gesamte Textelement einschließlich Hintergrund aus.

„Geöffnet“ bedeutet: Abendkasse verfügbar, Öffnungszeit erreicht und das
Veranstaltungsende noch nicht erreicht. Ohne Öffnungszeit gilt die Verfügbarkeit.
Abgesagte oder beendete Events und ungültige Uhrzeiten öffnen keine Abendkasse.
Die Gegenbedingung „geschlossen“ gilt nur, wenn ein Event vorhanden ist.
Zeitgrenzen werden im laufenden Player über seinen vorhandenen Sekundentakt
aktualisiert; Datenänderungen kommen mit der normalen Player-Aktualisierung.
Dieselben ergänzten Bedingungen stehen auch im Event-Stempel zur Verfügung.
Interne Eventfelder sind nicht als Bedingung auswählbar. Keine zusätzliche
Abfrage, Datenbankmigration oder eigener Timer ist erforderlich.
