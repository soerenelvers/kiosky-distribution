# Player-Downloads in 3.13.4

Der gemeldete Windows-64-Bit-Player aus WordPress öffnete den Hostnamen
`kiosky_player_url_placeholder`. Die WordPress-Downloadfunktion lieferte eine
unveränderte Binärvorlage aus. TYPO3 verlinkte ebenfalls direkt auf solche
Vorlagen. Standalone setzte die Adresse schon serverseitig ein.

## Korrigierter Ablauf

WordPress und TYPO3 verwenden jetzt denselben PHP-Generator. Er setzt die
öffentliche Kopplungsadresse der jeweiligen Installation in alle nativen
Architekturen ein. Unterverzeichnisse bleiben erhalten. Der Client erzeugt
einen Code, der im Backend einem Display zugeordnet wird; erst danach startet
die Ausspielung über dessen Geräteschlüssel.

- Standalone: `/player/downloads/PLATTFORM`, Einstieg `/player/browser.html`.
- WordPress: `/kiosky-player-download/PLATTFORM`, Einstieg `/kiosky-player-browser/`.
- TYPO3: dieselben relativen CMS-Pfade; alte `/kiosky-assets/launchers/...`-Links
  werden ebenfalls servergebunden ausgegeben.

| Plattform | Neuer Download | Start |
| --- | --- | --- |
| Windows 64 Bit | `.exe` mit Serveradresse | Vorhandenen Edge/Chrome im Kioskmodus öffnen |
| Windows 32 Bit | `.url`, reine Browserverknüpfung | Vorhandenen Browser öffnen, Vollbild mit F11 |
| Linux 64 Bit | Ausführbare Datei mit Serveradresse | Ausführbar machen; vorhandenen Chromium/Chrome im Kioskmodus öffnen |
| macOS Apple Silicon/Intel | `.app.zip` | App entpacken; beide Architekturteile enthalten die Serveradresse |

HTTPS-/HTTP-Adressen werden auf Format, Steuerzeichen, Zugangsdaten und Länge
geprüft. Ungültige, fehlende oder abgeschnittene Vorlagen werden mit einem Fehler
abgewiesen. Download-Controller liefern keinen unveränderten Platzhalter als
scheinbar startfähige Datei aus. Native Downloads verwenden weiterhin den
vorhandenen Browser; sie enthalten keine eigene Browser-Engine.

## Windows-32-Bit-Erkennung

Die bisherige x86-EXE wurde als Virus gemeldet. Sie wird bis zur Klärung nicht
weiter ausgeliefert; die aktuelle Quelle und die Plattformpakete enthalten sie
nicht mehr. Der Ersatz ist eine lesbare Internetverknüpfung mit ausschließlich
dem Abschnitt `[InternetShortcut]` und einer HTTP(S)-Adresse, kein verstecktes
EXE oder Skript. Schutzfunktionen bleiben aktiv.

Beide bisherigen Windows-Vorlagen enthalten keine Authenticode-Signatur.
Das allein beweist weder Schadsoftware noch einen Fehlalarm. Für eine konkrete
Analyse fehlen der Erkennungsname und das meldende Sicherheitsprodukt. Es wurde
keine Datei an einen externen Analysedienst hochgeladen und keine neue signierte
native 32-Bit-App erstellt. Für einen vertrauenswürdigen nativen Signierpfad ist
ein gesondertes Codesigning-Konzept nötig; das URL-Einbetten würde eine zuvor
angebrachte EXE-Signatur verändern.

## Aktualisierung

1. Kiosky in der jeweiligen Installation auf 3.13.4 aktualisieren.
2. Player Center neu öffnen und die Playerdatei erneut herunterladen.
3. Alte Dateien mit Platzhalter nicht weiter verwenden; sie kennen keine gültige
   Serveradresse und können sich daher nicht selbst aktualisieren.
4. Unter Windows 32 Bit die neue `.url` verwenden oder den Browser-Player direkt
   öffnen; unter Linux das Ausführungsrecht setzen und auf macOS das ZIP entpacken.

Inhalte, Displayzuordnungen und die Datenbank werden nicht migriert. Eine bereits
im selben Browserprofil gespeicherte Zuordnung bleibt erhalten.

## Nachweise und Grenzen

- Node- und PHP-Tests prüfen gebundene URLs, Unterverzeichnisse, MIME-Typen,
  Dateinamen, nicht cachebare Antworten, fehlende Platzhalter und alle Formate.
- Die WordPress-Produktionsfunktion wird in einer isolierten HTTP-Fixture mit
  GET, HEAD, ungültigen Plattformen und unerlaubten Methoden ausgeführt.
- Die nativen WordPress-/TYPO3-Smokes prüfen die Generatoren beziehungsweise
  Middleware-Routen, Legacy-Links, Codekopplung und Player-API-Zugriff. Diese
  Tests werden im bestehenden Installations-/Upgrade-Gate ausgeführt.
- Der Linux-Launcher wird mit einer lokalen Browser-Testattrappe tatsächlich
  gestartet; die vollständige Adresse und Kioskargumente werden geprüft.
- macOS-ZIP-Prüfungen kontrollieren CRC, Appstruktur, Ausführungsrecht und beide
  eingebetteten Architekturadressen. Es wird keine optionale PHP-ZIP-Extension
  vorausgesetzt.

Ein Live-Start auf realem Windows/macOS sowie ein Ergebnis des vom Nutzer
eingesetzten Virenscanners sind damit nicht nachgewiesen. Browser- und
Sicherheitsprodukt-Versionen können zusätzliche Kompatibilitätsgrenzen setzen.
