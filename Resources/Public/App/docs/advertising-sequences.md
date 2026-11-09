# Werbeabfolge und dynamische Übersichten (ab 3.33.0)

Die automatischen Kanäle **Werbung quer** und **Werbung Hochkant** sind in
**Slides & Kanäle** und im Werbemodul sichtbar. Den Kanal auswählen, die aktuelle
Abfolge ansehen und eigene Slides aus der Bibliothek hinzufügen oder per Drag
and Drop dazwischen platzieren. Die Reihenfolge der gespeicherten Einträge,
Anzeigedauern und Übergänge bleiben bei späteren Synchronisierungen erhalten.
Automatische Veranstaltungs-Slides können in der Abfolge verschoben werden;
ihre Inhalte werden weiterhin durch die getrennten Werbevorlagen bestimmt.

Unter **Einstellungen → Werbung → Zwischen den Veranstaltungswerbungen** pro
Format bis zu zehn weitere Slides auswählen. Mit ↑ und ↓ ihre Reihenfolge
festlegen. Beispiel mit zwei Zwischenslides:

`Veranstaltung 1 → Slide A → Slide B → Veranstaltung 2 → Slide A → Slide B → Veranstaltung 3`

Die Zwischenslides erscheinen zwischen zwei Veranstaltungen, nicht zusätzlich
nach der letzten. Im Kanal sind sie als Zwischenslides gekennzeichnet; ihre
Definition wird in den Werbeeinstellungen bearbeitet. Nur veröffentlichte,
zeitlich gültige Slides werden ausgespielt. Abgelaufene, archivierte, gelöschte
oder noch nicht begonnene Slides entfallen ohne leere Plätze.

**Nächste Veranstaltungen** begrenzt ausschließlich Veranstaltungen, unabhängig
von getaggten, manuell hinzugefügten und wiederkehrenden Zwischenslides.
Die frühere Gesamtbegrenzung **Maximale Slides je Werbekanal** heißt jetzt
**Maximale getaggte Werbeslides (zusätzlich)**. Sie begrenzt den automatischen
Beitrag der Werbe-Tags, ohne Veranstaltungsplätze zu verbrauchen. Eigene im Kanal
bewusst gespeicherte Ergänzungen bleiben erhalten. Weiterhin gelten die
vorhandenen Werbe-Tags und deren Ablaufdatum; ohne Datum beim Taggen sieben Tage.

Neue passende Veranstaltungen rücken automatisch nach. Bereits gespeicherte
Einträge behalten ihre relative Reihenfolge; neu hinzukommende Einträge werden
angehängt. Werbe-Tags, die beim Speichern bewusst als Teil der Kanalabfolge
übernommen wurden, bleiben dort als explizite Referenz erhalten, bis sie entfernt
werden oder die Slide abläuft. Das Deaktivieren pausiert die automatischen Kanäle;
erneutes Aktivieren schaltet sie wieder frei.

## Eigenständige Übersichts-Slides

Unter **Einstellungen → Werbung → Dynamische Veranstaltungsübersichten** einen
Entwurf erstellen:

- **Quer · 6 Veranstaltungen:** drei Spalten, zwei Reihen.
- **Hochkant · 10 Veranstaltungen:** zwei Spalten, fünf Reihen.
- **Hochkant · 15 Veranstaltungen:** drei Spalten, fünf Reihen.

Dunkler Hintergrund, Karten mit Veranstaltungsbild, Datum, Titel, Untertitel,
Beginn, Einlass und Ticket-QR-Code. Fehlt ein Ticket-Link oder enthält er nur
Leerzeichen, erscheint kein QR-Code und kein Ticket-Begleittext. Ohne Bild wird
eine gestaltete Platzhalterfläche angezeigt. Bei weniger Veranstaltungen bleiben
nur die vorhandenen Karten sichtbar. Ohne passende Termine erscheint ein Hinweis.

Der neue Slide öffnet sich direkt im Editor und steht anschließend in der
normalen Slide-Bibliothek bereit. Überschrift, Position und Größe sind editierbar.
Beim Element **Veranstaltungsübersicht** kann die Anzahl von 1 bis 50 frei eingestellt werden (ab 3.34.0). Das Element kann auch direkt im Slide-/Vorlageneditor
hinzugefügt werden. Den Entwurf veröffentlichen und einem beliebigen Kanal,
Zeitplan oder Display zuweisen.

Die Übersicht verwendet die nächsten aktiven Veranstaltungen aller Quellen;
Filter und Begrenzungen des Werbekanals beeinflussen sie nicht. Abgesagte,
archivierte und abgelaufene Termine werden ausgelassen. Änderungen an den Daten
und nachrückende Veranstaltungen erreichen die laufende Ausgabe über die normale
Player-Aktualisierung. Es werden keine Veranstaltungskopien und keine wiederholten
Slide-Versionen angelegt. Interne Notizen gelangen nicht in die Player-Daten.
Die Module Veranstaltungen und Slides/Kanäle müssen freigeschaltet sein.

Standalone, WordPress und TYPO3 verwenden dieselbe Oberfläche und Darstellung;
der CMS-Domainkern setzt die Regeln für beide CMS lokal um. Keine neue
Datenbankmigration. Vor einem Downgrade neue Übersichten aus Ausspielungen nehmen;
ältere Versionen kennen das Element nicht. Neue Abfolge-Einstellungen werden von
älteren Versionen nicht ausgewertet, vorhandene Inhalte werden nicht gelöscht.

Ab 3.34.0 verhindern leere oder ungültige optionale Endzeiten keine gültigen
kommenden Veranstaltungen mehr; dies gilt auch für die automatische Werberotation. Uhrzeiten ohne Zeitzone beziehen sich auf
Europe/Berlin. Die Vorschau verwendet die Serverzeit und kann über
**Veranstaltungen aktualisieren** neu geladen werden. Gespeicherte alte
Momentaufnahmen werden durch aktuelle Daten ersetzt. Die Auswahl wird pro
Player-Abfrage einmal berechnet, auf 50 Karten begrenzt und gemeinsam verwendet;
für einzelne Karten werden keine zusätzlichen API-Abfragen ausgeführt.

## Veranstaltungskarten im Slide gestalten (ab 3.36.0)

Das Element **Veranstaltungsübersicht** auf der Arbeitsfläche oder in der
Ebenenliste auswählen und rechts unter **Eigenschaften → Karten gestalten**
bearbeiten. Im Vorlageneditor stehen dieselben Einstellungen bereit:

- Automatische oder 1–6 Spalten, Karten-/Innen-/Detailabstände, Rundung und Farbe.
- Bild, Datum, Titel, Untertitel, Beginn, Einlass und Ticket-QR einzeln anzeigen
  oder ausblenden. Leere Details und leere Ticket-Links samt Begleittext entfallen.
- Pro Detail Zeile, Breite, Schriftgröße/-art, Ausrichtung, Fett und Text davor /
  danach wählen. Gleiche Zeilennummern ordnen Details nebeneinander an; dafür
  passende Breiten wählen, etwa je 50 % für Beginn und Einlass.
- Reihenfolge durch Ziehen oder die Pfeiltasten verändern; Pfeile sind auch auf
  Touchgeräten verfügbar. Für neue Zeilen eine andere Zeilennummer vergeben.
- Bildhöhe, Beschneiden/Einpassen, Platzhaltertext und QR-Größe einstellen.

Änderungen erscheinen direkt auf der Arbeitsfläche und werden mit der Slide
bzw. Vorlage gespeichert. Die Player verwenden dieselbe Gestaltung und aktuelle
Veranstaltungen. **Standardlayout wiederherstellen** entfernt nur die
Kartengestaltung. Bereits vorhandene Übersichten bleiben unverändert, bis ihre
Karten gestaltet werden. Keine Veranstaltungskopien oder zusätzlichen Abfragen
pro Karte. Der [Veranstaltungs-Bildeditor](event-image-editor.md) wirkt ebenfalls
auf die Bilder in diesen Karten.
