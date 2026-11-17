## [3.48.0]

- DWD-Warnungen direkt aus der Übersicht auswählen; Ziele und Anzeigeregeln vor dem Auslösen festlegen. Mehrfachauswahl zeigt mehrere Warnungen gemeinsam in einer responsiven Ausgabe.
- Einmalig, alle X Minuten oder bis zum amtlichen Ende / zur Rücknahme; individuelle Regeln bleiben bei Abgleichen erhalten. Einzelablauf und Rücknahmen entfernen nur betroffene Einträge; Offline-Player beachten bekannte Ablaufzeiten.
- Player-, DWD-, Status- und Zielkatalogabfragen in WordPress/TYPO3 lesen nicht mehr die gesamte Mediathek. Ausgaben lösen nur zugewiesene Medien auf; native Berechtigungen, Dateiänderungen und geschützte Schriften bleiben berücksichtigt.
- WordPress führt Laufzeitstände unabhängiger Displays konfliktfrei zusammen; echte Inhalts-/Rechteänderungen und konkurrierende Änderungen desselben Ausspielwegs bleiben geschützt.
- Player-Uhrkorrektur zieht Serververarbeitung von der gemessenen Laufzeit ab. DWD-Speicherkonflikte wiederholen keinen bereits erfolgreichen externen Abruf.
- Standort / alle Displays / eigene Auswahl mit Suche, Online-Status und deduplizierter Empfängervorschau. Veraltete Gruppenbearbeitungen können neuere Auslösungen nicht überschreiben.
- Rücknahmen werden je Installation im Hintergrund geprüft: Standalone-Timer, WordPress-Cron und TYPO3-Scheduler beziehungsweise CLI. Fehler erhalten vorhandene Warnungen; keine ungefragte Freigabe neuer Warnungen.
