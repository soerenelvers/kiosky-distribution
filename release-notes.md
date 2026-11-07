## [3.26.0] – 2026-11-07

- Direkte Slide-zu-Slide-Übergänge für Überblenden, Schieben und Zoom statt Ausblenden über den Player-Hintergrund; Kanalvorgaben und individuelle Übergänge erhalten.
- Pro Standort eine zentrale Display-Hintergrundfarbe für freie Flächen und Slides ohne eigenen Hintergrund einstellen; Schwarz als neutrale Vorgabe und Live-Übernahme ohne Neustart der Wiedergabe.
- Ausgehende Medien während des Übergangs im bestehenden DOM belassen, maximal eine zusätzliche Slide halten und danach Decoder, Iframes, Listener und Zeitgeber freigeben; auf neue Medien höchstens eine Sekunde warten.
- Standortfarbe in Standalone, WordPress und TYPO3 sowie Systemexport/-import übernehmen; bestehende Standalone-Datenbanken mit Migration 043 ergänzen.
