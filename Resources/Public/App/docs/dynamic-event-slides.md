# Automatische Event-Slides, Countdown und Website-Slides

Ab 3.12.0 in Standalone, WordPress und TYPO3. Vorhandene Slides bleiben fest mit
ihrer ausgewählten Veranstaltung bzw. der Veranstaltung ihres Kanals verbunden.
Es ist keine Datenmigration erforderlich.

## Event-Daten automatisch auswählen

Im Slide-Editor **Eigenschaften → Event-Daten für diese Slide** öffnen und
**Veranstaltung automatisch auswählen** aktivieren. Die Auswahl gilt gemeinsam
für Eventfelder, Eventdatenblöcke, veranstaltungsbezogene Bilder und die neuen
Event-Countdowns. Ein optionaler Raum-/Saalname begrenzt die Auswahl auf genau
diesen Raum; Groß-/Kleinschreibung und äußere Leerzeichen werden ignoriert.

| Auswahl | Verhalten |
| --- | --- |
| Zeitlich nächstes Event | Frühester noch nicht vergangener Veranstaltungsbeginn |
| Aktuell laufendes Event | Beginn erreicht, Ende noch nicht erreicht; bei Überschneidungen der zuletzt begonnene Termin |
| Heutiges Event | Laufendes Event zuerst, sonst nächster Beginn heute, sonst zuletzt begonnener Termin heute |

„Heute“ richtet sich in allen Ausgaben nach **Europe/Berlin**, auch über
Sommer-/Winterzeitwechsel hinweg. Eine über Mitternacht laufende Veranstaltung
mit explizitem Ende bleibt bis dahin aktuell. Ohne Endzeit gilt ein begonnener
Termin bis zum Ende seines Berliner Kalendertags als aktuell. „Nächstes Event“
wählt den nächsten Beginn und wechselt nach dessen Erreichen zum folgenden
Termin; für eine laufende Veranstaltung deshalb „Aktuell“ oder „Heute“ wählen.

Nur freigegebene Veranstaltungen mit Status `ready`, `published` oder `sold_out`
und gültiger Beginnzeit werden automatisch ausgewählt. Entwürfe, zu prüfende,
abgesagte, archivierte und gelöschte Termine bleiben ausgeschlossen. Ohne Treffer
werden abhängige Eventfelder ausgeblendet. Die Event-Modulfreigabe bleibt nötig.
Die vorhandene feste Auswahl wird bei ausgeschalteter Automatik wieder benutzt.

Player lösen die Auswahl bei ihrer regulären Inhaltssynchronisierung neu auf;
Änderungen und Terminwechsel erscheinen bei laufender Verbindung normalerweise
innerhalb weniger Sekunden. Offline gilt der zuletzt geladene Event-Datensatz.
Editorvorschau und Kanalvorschau verwenden dieselben Auswahlregeln.

## Countdown auf einen Event-Zeitpunkt

1. Ein **Countdown**-Element hinzufügen.
2. Als Ziel **Zeit aus Veranstaltung / Ablauf** wählen.
3. Aufbau, Einlass/Öffnung, Beginn, Pausenbeginn, Pausenende, Ende oder Öffnung der
   Abendkasse auswählen.
4. Offset auf **Früher (−)** oder **Später (+)** stellen und Minuten/Sekunden
   eintragen. Möglich sind bis zu sieben Tage in beide Richtungen.
5. Text davor/danach und Anzeigeformat wählen. Neben Stunden/Minuten/Sekunden
   gibt es **Verbleibende Minuten als Text**; angebrochene Minuten werden dabei
   aufgerundet, zum Beispiel `Pause in 7 Minuten`.

Beispiele:

| Event-Zeitpunkt | Offset | Countdown-Ziel |
| --- | --- | --- |
| Pausenende 21:20 | −7 Minuten | 21:13 |
| Pausenende 21:20 | −3 Minuten 30 Sekunden | 21:16:30 |
| Beginn 19:00 | +5 Minuten | 19:05 |

Der Offset verändert ausschließlich das Countdown-Ziel, keine Veranstaltungszeit.
Die Anzeige läuft sekündlich. Fehlende oder ungültige Zeiten blenden das komplette
Element einschließlich Beschriftung aus. Nach einem erreichten festen Event-Ziel
bleibt der Countdown bei null; nur der bestehende tägliche Modus rollt weiter.
Ein- und ausgeblendete Sekunden gelten für die Uhrdarstellung. Alte tägliche und
einmalige Countdown-Elemente behalten ihre bisherige Bedeutung.

### Einzelne Ablaufpunkte

Als Event-Zeitpunkt **Ablaufpunkt der Veranstaltung** wählen. Kategorie und eine
optionale exakte Bezeichnung, etwa `Zweiter Teil`, bestimmen die passenden Punkte.
Dann **Beginn** oder **Ende** auswählen und den Offset festlegen. Kiosky verwendet
den nächsten noch nicht vergangenen Zeitpunkt **einschließlich Offset**. So kann
ein positiver Offset einen bereits begonnenen Punkt noch als Ziel verwenden.
Nach Erreichen wechselt diese dynamische Auswahl zum nächsten passenden Punkt.

Interne, inaktive, stornierte und durch die Typregeln ausgeblendete Ablaufpunkte
werden nicht an den öffentlichen Player übergeben. Metadaten und interne
Eventnotizen werden für die Playerdarstellung nicht benötigt. Die Auswahl bezieht
sich immer auf die für diese Slide aufgelöste Veranstaltung.

## Tags als Ordner

In **Slides & Kanäle** bei der jeweiligen Bibliothek **Ordneransicht** wählen.
Jeder zugewiesene Tag erscheint als virtueller Ordner mit Inhaltszähler.
**Alle Inhalte** hebt die Tagbegrenzung auf; Such- und Typfilter gelten weiterhin.
Ein Inhalt mit mehreren Tags erscheint in jedem passenden Ordner, ohne dupliziert
zu werden. Neue Tagzuweisungen aktualisieren die Ordner beim Neuladen der Inhalte.

Der Wechsel zurück zu **Tagfilter** behält denselben ausgewählten Tag. Die
bevorzugte Ansichtsart wird lokal im Browser gespeichert. Es entstehen keine
zusätzlichen Dateien oder Ordnerdatensätze. Standardinhalte und Werbung bleiben
in ihren jeweiligen Bereichen; Stapelauswahl und Aktionen funktionieren weiter.

## Website oder externes Display direkt als Slide

**Slide erstellen → Website / externes Display** oder **Slide aus URL** in der
Bibliothek wählen. Eine vollständige **HTTPS-Adresse**, optional einen Namen und
das Format eingeben. Ohne Namen wird der Hostname übernommen. Kiosky erstellt
einen Entwurf mit einem Website-Element, das die gesamte Slide füllt, und öffnet
den visuellen Editor zur Kontrolle. Anschließend veröffentlichen, einem Kanal
hinzufügen und wie andere Slides ausspielen. URL-Parameter und Fragment bleiben
erhalten. Bereits vorhandene allgemeine Website-Elemente bleiben nutzbar.

Die Zielseite muss iFrame-Einbettung erlauben. Ihre `X-Frame-Options`- und
`frame-ancestors`-Regeln, Anmeldung und Drittanbieter-Cookie-Regeln gelten auch im
Kiosky-Player. Bei gesperrten Fremdsystemen ist eine vom Anbieter freigegebene
Display-/Embed-URL erforderlich. Kiosky umgeht diese Regeln nicht und ruft keine
Webseiten serverseitig über einen Proxy ab. Passwörter im URL-Benutzerteil und
andere Protokolle werden im neuen Erstellungsdialog abgewiesen.

## Smartphone und Tablet

Die mobile Slide-Werkzeugleiste enthält **Hinzufügen**, **Ebenen**,
**Eigenschaften**, **Speichern** und **Schließen**. Speichern verwendet beim
Vorlageneditor die Vorlagenaktualisierung. Eigenschaften bleiben scrollbar;
Safe-Area-Abstände berücksichtigen die untere Gerätefläche. Formulare verwenden
bei Touch-Bedienung größere Eingaben und 16-Pixel-Schriften, um ungewolltes
Safari-Zoomen zu vermeiden. Allgemeine Dialoge und die Speisekarten profitieren
ebenfalls von den gemeinsamen Touch-Regeln.

Die Browserregression `tests/event-editor-browser-smoke.py` prüft Event-Auswahl,
Offsets, Tagordner, Websitevorschau und den laufenden Player sowie Smartphone- und
Tabletgrößen im Hoch-/Querformat. Zusätzlich prüft sie die Layoutgrenzen der
vorhandenen Dialoge. Chromium und WebKit sind Browserprüfungen, keine Tests auf
angeschlossenen echten Android-/iOS-Geräten. CMS-Transportvarianten der Oberfläche
ersetzen die separat in CI ausgeführten nativen CMS-Installationen nicht.
