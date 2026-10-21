## [3.5.0] – 2026-10-21

- Speisekarten-Slides für Standalone, WordPress und TYPO3: drei Vorlagen in Hoch- und Querformat, einfacher Inhaltseditor unter `/speisekarten`, bis zu acht Speisen je Slide mit Beschreibung und Preis.
- Separates Bearbeitungsrecht und explizite Kartenzuweisungen über bestehende Benutzerkonten; serverseitige Zugriffskontrolle, sofortiger Rechteentzug, Schutz vor fremden Karten, CSRF und veralteten Bearbeitungsständen.
- Küchen ändern ausschließlich Inhalte. Layout, Veröffentlichung, Kanäle, Standortrechte und Zeitplanung bleiben in der bestehenden Verwaltung. Änderungen sind versioniert und werden durch bestehende Player synchronisiert.
- Standalone übernimmt die exklusive Zugangswerk-Anmeldung; CMS-Ausgaben nutzen ihre native Anmeldung und Sitzungs-/Anfrageprüfung. Additive Migration und dokumentierte Einrichtung, Grenzen und Wiederherstellung.
