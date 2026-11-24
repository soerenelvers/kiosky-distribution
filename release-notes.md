## [3.54.0]

- Gekoppelte Player unabhängig benennen und einzeln identifizieren; die Einblendung zeigt den Namen und die Gerätekennung. Displayweite Befehle bleiben erhalten.
- Geschützte Bildschirmfoto-Anforderung für native WebView2-/Chromium-Player: tatsächliche Browserausgabe statt Konfigurationsvorschau, zeitlich begrenzte Anforderung und Anzeige, begrenzte Dateigröße, keine Aufnahme fremder Desktopfenster und kein Debugging-Port. Nicht unterstützte Browser melden dies ausdrücklich.
- Dauerhafter HTTPS-App-/Mediencache für Offline-Kaltstarts nach einer erfolgreichen Ersteinrichtung; neue Caches ersetzen vorherige Caches erst nach vollständiger Vorbereitung. Externe Streams und zu große Medien bleiben onlineabhängig.
- Globale gestaffelte Playerupdates mit Pause sowie 5-, 25-, 50- und 100-Prozent-Freigabe. Gruppenzuordnung bleibt lokal erhalten; zuerst die neuen Updater einmal bei vollständiger Freigabe verteilen.
- Gemeinsame Umsetzung für Standalone, WordPress und TYPO3 einschließlich Geräte-/Standort-/Rollenprüfungen, privater CMS-Fotoablage und Playernamen bei verschlüsselten Systemumzügen.
- Ticketanbieterprüfung für Eventim, ADticket/Reservix und eintrittskarten.io dokumentiert. Der automatische Restkartenabruf bleibt mangels freigegebener Bestandsverträge und Testzugänge offen; bestehende manuelle Angaben und Ticket-Auswahlseiten bleiben nutzbar.
