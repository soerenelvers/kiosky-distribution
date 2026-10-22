## [3.7.1] – 2026-10-22

- Web-Player übernehmen relevante Inhaltsänderungen in Standalone, WordPress und TYPO3 automatisch ohne Seitennavigation oder Ladebildschirm. Geänderte Kanalinhalte und direkte Slides starten die Rotation neu; unveränderte Abrufe lassen laufende Medien und Rotation bestehen. Matrix-Displays behalten einen gemeinsamen Takt.
- „Player neu laden (F5)“ direkt in der Display-Verwaltung. Schneller, geräteschlüsselgeschützter Befehlsabruf auch in beiden CMS-Ausgaben; selektive Bestätigung verhindert verlorene oder wiederholte Refresh-Befehle.
- Regelmäßige Heartbeats für Einzel-Slides, letzter erfolgreicher Stand bei Netzwerkfehlern und unabhängig behandelter Browser-Cache. Der native TYPO3-Tabellenplayer erkennt Playliständerungen ebenfalls ohne unnötige Neustarts.
- Additive Laufzeitinformationen ohne neue Datenbankmigration. Einrichtung und Verhalten: docs/player-refresh.md.
