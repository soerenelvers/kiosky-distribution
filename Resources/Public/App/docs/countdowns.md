# Countdown im Slide-Editor

Unter **Slides & Kanäle → Slide bearbeiten → Countdown** lassen sich Zielzeit,
Anzeige und Verhalten nach Ablauf getrennt einstellen. Dieselben Einstellungen
gibt es für Countdowns in Laufelementen und Wegweisungszellen. Die Oberfläche
und Playerdarstellung werden gemeinsam für Standalone, WordPress und TYPO3 gebaut.

## Zeitformat

- **HH:MM**: Stunden und Minuten, z. B. `01:02`.
- **HH:MM:SS**: Stunden, Minuten und Sekunden, z. B. `01:02:04`.
- **MM:SS**: gesamte Minuten und Sekunden, z. B. `62:04`.
- **Stunden, Minuten oder Sekunden als Text**, z. B. `63 Minuten`.

Die neuen Stundenformate zeigen bei mehr als einem Tag die gesamte Stundenzahl,
z. B. `25:00`. Verbleibende Stunden/Minuten als Text werden aufgerundet; nach
Ablauf zeigt Hochzählen die vollständig verstrichenen Einheiten.

## Nach Ablauf

**Nach Ablauf ausblenden** blendet im Player das gesamte Countdown-Element
einschließlich Präfix, Suffix und Hintergrund aus. Der Slide und seine übrigen
Elemente bleiben sichtbar. Die Ebene bleibt im Editor zum Bearbeiten auswählbar.

Ohne Haken bestimmt **Wenn nach Ablauf sichtbar** das Verhalten:

- **Verstrichene Zeit hochzählen (+)**: z. B. drei Sekunden nach dem Ziel
  `+00:00:03`. Dies ist der Standard für neu eingefügte Countdown-Elemente.
- **Bei null stehen bleiben**: zeigt nach Ablauf weiterhin null.
- **Zur nächsten täglichen Zielzeit zählen**: nur bei täglicher Zielzeit;
  läuft nach Erreichen unmittelbar für den nächsten Tag weiter.

Tägliche Countdowns verwenden die Ortszeit des Abspielgeräts. Bei Ausblenden,
Hochzählen und Stehenbleiben gilt jeweils das Ziel des aktuellen Kalendertages;
um Mitternacht wird das Ziel für den neuen Tag berechnet. Einmalige Ziele mit
Datum bleiben nach Ablauf ausgeblendet beziehungsweise zählen weiter hoch.

Event-Zeitpunkte und positive/negative Offsets bleiben verwendbar. Ein für
Hochzählen oder Ausblenden gewählter nächster Ablaufpunkt bleibt während derselben
Wiedergabe das Ziel, wenn seine Zeit erreicht ist. Ein geänderter Inhalt oder neu
gestarteter Slide kann den dann nächsten Ablaufpunkt wählen. Fehlende Event-Zeiten
bleiben unsichtbar.

Vorhandene Slides behalten ohne Bearbeitung ihre bisherigen Formate und
Ablaufregeln: tägliche Ziele rollen zum nächsten Tag weiter, feste Ziele bleiben
bei null. Es ist keine Datenmigration erforderlich. Nach dem Serverupdate müssen
bereits geöffnete Player einmal neu geladen werden, um die neue Darstellung zu laden.
