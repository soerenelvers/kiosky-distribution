# Display-Auswahl und Ausspielungsübersicht ab 3.38.0

In den Display-Einstellungen stehen über Standardkanal und Standardslide Suchfelder.
Die Auswahl ist alphabetisch nach deutscher Sortierung mit natürlicher Zahlensortierung
geordnet. Filtern verändert die gespeicherte Auswahl nicht. Ein ausgewählter Eintrag
bleibt erreichbar, auch wenn er nicht zum Suchtext passt. Beim erneuten Öffnen ist
die Suche leer; gespeichert wird weiterhin die ID der Auswahl.

„Aktuelle Ausspielung“ fragt denselben aufgelösten Zustand wie der echte Player ab.
Damit gelten Warnungen, Presets, Zeitplanung, leere Kanäle, Standardinhalt und
Standardslide mit derselben Priorität. Die Miniatur ist eine Vorschau der aktuellen
Serverkonfiguration; sie ist kein Bildschirmfoto des PCs. Der zuletzt vom Player
gemeldete Slide wird getrennt genannt. Bei Offlinebetrieb kann der Player einen
älteren Cache zeigen; dessen aktuelles Bild lässt sich vom Server nicht bestätigen.

Die Vorschau erzeugt keine Heartbeats und erhält keine Fernsteuerungsbefehle.
Der Verbindungsstatus bleibt von echten Player-Meldungen abhängig. Abfragen laufen
nur bei geöffneter Übersicht, sichtbarem Browser und aktivem Display-Arbeitsbereich,
alle zehn Sekunden mit höchstens vier parallelen Anfragen. Beim Schließen wird
der lokale Vorschauzustand verworfen. Die vorhandene Displayliste aktualisiert
Heartbeat-Metadaten weiterhin regelmäßig. Keine zusätzlichen Capture-/Streaming-
Prozesse laufen auf den Player-PCs.
