## [3.40.2]

- Geöffnete Lizenznehmer-Oberflächen übernehmen geänderte Modulfreigaben ohne Neuladen: Navigation, Einstellungen, offene Editoren und die angezeigte Freigabeliste folgen dem nächsten erfolgreichen Abgleich.
- WordPress und TYPO3 erneuern fällige Lizenzen zusätzlich bei aktiver Nutzung und gültigen Player-Anfragen, auch ohne laufenden Scheduler. Instanzbezogene Sperren vermeiden parallele Netzabfragen; Speicher-Konflikte erhalten zwischenzeitliche Änderungen.
- Entzogene Veranstaltungsfreigaben sperren Veranstaltungs-Elemente und entfernen die Laufzeitdaten eingebetteter Veranstaltungsübersichten aus der Player-Ausgabe. Gespeicherte Inhalte und lokale Modulschalter bleiben erhalten.
- Automatische Hintergrundprüfungen verändern die vereinbarte Offline-Regel nicht: Bei einem Ausfall bleibt die letzte gültige Freigabe erhalten, einschließlich bereits bestätigter Sperren.
