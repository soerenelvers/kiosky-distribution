## [3.13.4] – 2026-11-03

- WordPress-Playerdownloads serverseitig mit der tatsächlichen Kopplungsadresse versehen. Bisher wurde die unveränderte EXE-Vorlage mit `KIOSKY_PLAYER_URL_PLACEHOLDER` ausgeliefert, was beim Start einen DNS-Fehler erzeugte.
- TYPO3-Player-Center auf konfigurierte Download-Endpunkte umstellen; alte Asset-Downloadlinks ebenfalls mit der Serveradresse ausliefern. Unterverzeichnis, Inhaltstyp, Downloadname, HEAD und nicht cachebare Antworten werden berücksichtigt.
- macOS-Downloads beider CMS als vollständiges `.app.zip` mit ausführbarer Universal-Binary für Apple Silicon und Intel, passender App-Metadatei und gültigen ZIP-Zeitstempeln erzeugen.
- Gemeldete Windows-32-Bit-EXE bis zur Klärung der Virenerkennung aus der aktuellen Auslieferung zurücknehmen. Stattdessen steht in allen Varianten eine sichtbare `.url`-Browserverknüpfung zur Verfügung; Vollbild dort über F11. Dies ist keine Bestätigung eines Fehlalarms oder eine Codesignierung.
- URL- und Vorlagenprüfung sowie Regressionen für tatsächliche Download-Controller, native CMS-Routen, öffentliche Codekopplung, Playerzugriff und den nativen Linux-Start ergänzen. Nach Installation des Updates die Playerdateien neu herunterladen.
