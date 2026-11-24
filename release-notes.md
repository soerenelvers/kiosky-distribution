## [3.52.0]

- Display-Ausgabe zentral um 90° links/rechts oder 180° drehen, unabhängig pro Player. Logische Maße, automatische Slides, Matrixausschnitte, Warnungen, Identifikation und Offline-Stand berücksichtigen die Drehung. Standalone erhält eine additive Migration; alte Displays bleiben ungedreht und alte API-Schreibzugriffe bewahren die Einstellung.
- Kopplungsanzeige um einen QR-Code erweitert. Backend scannt Kamera oder Foto lokal mit dem mitgelieferten, fest versionierten jsQR-Leser; die Zuordnung wird weiterhin ausdrücklich bestätigt. Manueller Code, bestehende Rechte und Ablaufprüfung bleiben erhalten.
- Gleiche Oberfläche für Standalone, WordPress und TYPO3; QR-Leser wird nur beim Scan geladen, Kamera bei Schließen/Verlassen abgeschaltet.
