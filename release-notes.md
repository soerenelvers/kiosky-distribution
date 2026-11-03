## [3.13.3] – 2026-11-03

- Standalone-Player laden ein Display über dessen ID/Slug direkt. Sammelabfragen lesen Gruppenzuordnungen gemeinsam; ein zusätzlicher SQLite-Index unterstützt die Suche nach Displaymitgliedschaften. Die bisherige Abfrage aller Displays samt einer Gruppenabfrage pro Display entfällt.
- WordPress und TYPO3 projizieren bei Player-Anfragen nur das angefragte Display. Kanal-, Slide-, Standort- und Matrixzuordnungen sowie Standortzähler werden für Listen gemeinsam aufbereitet.
- Displays gelten in allen Ausgaben nach mehr als 60 Sekunden ohne gültigen Heartbeat als offline. Frische Heartbeats stellen den Status wieder her; dafür werden keine zusätzlichen Status-Schreibvorgänge ausgelöst.
- Gemeinsamer Player speichert unveränderte Offline-Inhalte höchstens einmal pro Minute statt bei jedem Fünf-Sekunden-Poll. Inhalts-, Warnungs- und Lizenzänderungen bleiben sofort speicherbar; volle oder gesperrte Browserablagen unterbrechen die Live-Wiedergabe nicht und werden gedrosselt erneut versucht.
- Die Bildschirmsperre wird nach Freigabe durch Browser/Betriebssystem wieder angefordert; doppelte Anforderungen und beim Schließen noch ausstehende Sperren werden aufgeräumt.
- TYPO3 bricht bei ungültigem oder nicht als Array lesbarem gespeichertem JSON mit einer sicheren Diagnose ab, statt einen leeren Bestand zu liefern. Bestehende Bytes bleiben bei Lese-/Schreibfehlern erhalten.
- Regressions- und Browserprüfungen für Abfrageaufwand, Offline-/Warnungserhalt, Speicherfehler, Heartbeatalter, Wake-Lock-Lebenszyklus sowie TYPO3-Datenfehler ergänzen. Prüfbericht und Messgrenzen: [Stabilität 3.13.3](docs/stability-3.13.3.md).
