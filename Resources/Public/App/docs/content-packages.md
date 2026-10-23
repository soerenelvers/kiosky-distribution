# Slides, Kanäle und Vorlagen übertragen

Ab Kiosky 3.3.3 enthalten Standalone, WordPress und TYPO3 dieselben Funktionen für portable Inhaltspakete im JSON-Format.

## Exportieren

- **Slides:** Unter „Slides & Kanäle“ die gewünschten Slides mit den Checkboxen markieren und in der Auswahlleiste „Exportieren“ wählen. Im Slide-Editor gibt es ebenfalls „Exportieren“; offene Änderungen werden davor gespeichert.
- **Kanäle:** Einen Kanal in der Kanalübersicht öffnen und „Kanal exportieren“ wählen oder mehrere Kanäle mit ihren Checkboxen markieren und in der Auswahlleiste „Exportieren“ wählen. Alle verwendeten Slides werden mitgenommen, mehrfach verwendete Slides nur einmal. Der aktuelle Arbeitsstand wird gespeichert. Entwürfe und leere, noch unfertige Kanäle sind erlaubt.
- **Vorlagen:** „Vorlagen verwalten“ öffnen. Jede Vorlage hat „Exportieren“; „Alle Vorlagen exportieren“ erzeugt ein gemeinsames Paket. Eine gerade bearbeitete Vorlage kann auch im Editor gespeichert und exportiert werden.

Das Paket enthält den aktuellen Stand einschließlich Elemente, responsive Layouts, Tags, Sichtbarkeitszeiten, Kanalreihenfolge, Anzeigedauern, Übergänge und Wiederholung. Benötigte Veranstaltungen und ihre Ablaufdaten sowie Standortdefinitionen werden ergänzt. Frühere Slide-Versionen werden für diesen gezielten Transfer nicht mitgenommen; der Systemtransfer unter Schnittstellen bleibt für vollständige Übertragungen bestehen.

## Importieren

In der Slide-Übersicht, Kanalübersicht, Vorlagenauswahl oder Vorlagenverwaltung „Importieren“ wählen. Im Dialog werden Quelle, Anzahl der Inhalte und Abhängigkeiten angezeigt. Erst „Jetzt importieren“ übernimmt das Paket. Auch der bestehende Dateiimport unter Schnittstellen erkennt Inhaltspakete.

Importe erstellen neue IDs und ergänzen Kopien; sie überschreiben keine vorhandenen Slides, Kanäle oder Vorlagen. Die Namen und Bearbeitungszustände bleiben erhalten. Automatisch erzeugte Werbeinhalte werden als eigenständige Kopien übernommen, damit die Ziel-Automatik sie nicht verändert oder archiviert. Verweise innerhalb des Pakets werden auf die neuen IDs umgeschrieben. Benutzer, Schnittstellen-Zugänge, Geräte und System-Einstellungen gehören nicht zu einem Inhaltspaket.

Bei eingeschränkten Standortrechten berücksichtigt der Export die freigegebenen Inhalte. In Standalone wird ein Import in die bereits freigegebenen Standorte des importierenden Benutzers eingeordnet. Administratoren können die exportierten Standortdefinitionen übernehmen.

## Medien und Größe

Benötigte Dateien aus der Kiosky-Mediendatenbank beziehungsweise der nativen WordPress- oder TYPO3-Medienverwaltung werden eingebettet. Dazu gehören Bilder, Logos, SVG-Grafiken, Videos inklusive Trimm-Metadaten und Dateianhänge wie PDFs. Wiederverwendete Medien mit denselben Einstellungen werden innerhalb eines Pakets dedupliziert. Eigenständige Trimm- oder Zuschnittvarianten bleiben getrennt, damit ihre Einstellungen erhalten bleiben. Beim Import werden sie in der Medienverwaltung der Zielinstanz angelegt.

Externe Webseiten und externe Medien-URLs, die nicht als lokale Datei in der Medienbibliothek vorliegen, bleiben Links. Ein Hinweis im Paket und in der Importvorschau zeigt solche Abhängigkeiten an. Der Export ruft keine fremden Server ab. Fehlende lokale Dateien werden als Fehler gemeldet, statt ein unvollständiges Paket zu erzeugen.

Pro Datei gilt wie bei CMS-Uploads eine Grenze von 20 MB, pro Paket 250 MB. Für umfangreiche Bibliotheken mehrere kleine Pakete erstellen. CMS- und Server-Limits wie PHP `post_max_size` und `memory_limit` können zusätzlich die maximal nutzbare Paketgröße begrenzen.

Die Funktionen stehen Administratoren und Redakteuren zur Verfügung. Betrachter können keine Inhaltspakete importieren oder exportieren.

## Kanal-Stapelaktionen

Die Kanalliste bietet Checkboxen und „Alle auswählen“ für die gerade sichtbaren Such- und Tag-Filterergebnisse. Bereits ausgewählte, anschließend ausgefilterte Kanäle bleiben ausgewählt; der Zähler zeigt die gesamte Auswahl. Eine leere Suche hebt die Auswahl nicht auf. Beim Wechsel zwischen normalen Inhalten und Werbung wird die Auswahl auf den jeweiligen Bereich begrenzt.

„Umbenennen“ fragt jeden neuen Namen einzeln ab und prüft vor dem ersten Speichern auf leere oder bereits vergebene Namen. Abbrechen überspringt den jeweiligen Kanal. „Tags zuweisen“ verwendet denselben Dialog wie Slides und ergänzt Tags, ohne vorhandene Tags zu ersetzen. Umbenennen und Tags lesen nach dem Speichern offener Kanaländerungen den aktuellen Serverstand.

„Duplizieren“ erstellt einen eigenständigen Kanalentwurf mit eigener Abspielreihenfolge und denselben Slide-Verweisen. Die Slides bleiben bewusst gemeinsame Bibliotheksinhalte; eine Änderung der Slide selbst erscheint weiterhin in allen verwendenden Kanälen. CMS-Kopien übernehmen keine Zuständigkeit der Werbeautomatik. Bestehende Display-, Zeitplan- und Preset-Zuweisungen wechseln nicht auf die Kopie.

„Archivieren“ verschiebt Kanäle in den bestehenden Papierkorb; „Löschen“ ist endgültig und bleibt Administratoren vorbehalten. Beide verlangen eine Bestätigung. Endgültiges Löschen entfernt zugehörige Zeitplaneinträge und Ausspielzuweisungen und löst Display-, Matrix- und Preset-Verweise. Gemeinsame Bibliotheks-Slides und Verknüpfungen anderer Kanäle bleiben erhalten. Archivieren erhält diese Zuordnungen für die spätere Wiederherstellung. Ein Teilfehler wird mit der Zahl bereits verarbeiteter Kanäle gemeldet; erfolgreich verarbeitete Kanäle werden aus der Auswahl entfernt. Betrachter erhalten keine Stapelaktionen und können auch über die API keine Kanaländerungen ausführen.
