## [3.43.0]

- Player-Gesundheit unter Displays mit Verbindung, Version, letztem erfolgreichen Inhaltsabruf und Medienfehlern; verzögerte Warnungen für Offline-Geräte, Abruffehler und leere Ausgabe. Konfigurierbare Wartezeiten und Wartungspause, mobil bedienbar in allen drei Ausgaben.
- Player-Schlüssel werden Leseberechtigten nicht mehr offengelegt; authentifizierte Ausgabevorschau ersetzt den Schlüsselzugriff. Native CMS-Steuerungsrechte und WordPress-Statuskonflikte werden berücksichtigt.
- Öffentliche Player-Anfragen sind größenbegrenzt, PHP-Metadaten werden gefiltert und Standalone prüft widerrufene Geräteschlüssel vor dem Schreiben erneut. Standortfreigaben gelten auch für operative Ziele und Zeitzuweisungen.
- Bedienung und Prüfgrenzen: docs/player-health.md; Sicherheitsbefunde und offener Abhängigkeitsreview des separaten Update-Diensts: docs/reviews/security-resilience-2026-11-15.md.
