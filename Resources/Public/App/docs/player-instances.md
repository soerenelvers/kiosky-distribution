# Mehrere Displays an einem PC

Ab Kiosky 3.22.0 können Windows 64 Bit sowie Ubuntu, Debian und Linux Mint
mehrere unabhängige Player-Instanzen unter demselben angemeldeten Benutzer ausführen.
Windows muss die Bildschirme als **Erweitern**, Linux als getrennte Ausgaben konfigurieren;
bei gespiegelten Bildschirmen lässt sich kein unterschiedlicher Inhalt darstellen.

1. **Kiosky Player-Instanzen** im Windows-Startmenü beziehungsweise Linux-Anwendungsmenü öffnen.
   Alternativ den installierten Player mit `--instances` starten. Unter Windows öffnet F10
   im Player ebenfalls die Instanzverwaltung.
2. Die bestehende Instanz behält ihre Kopplung. Für sie bei Bedarf den ersten Monitor auswählen.
3. **Neue Instanz** anlegen, benennen, den zweiten Monitor auswählen und Autostart aktivieren.
4. **Speichern**, anschließend **Starten**. Jede neue Instanz zeigt einen eigenen
   Kopplungscode. In Kiosky jeweils mit einem anderen Display verbinden.

Jede Instanz besitzt ein eigenes Browserprofil, eine installierte Programmdatei,
einen Autostart-Eintrag und eine Update-Routine. Dateien im Downloadordner können
nach der Einrichtung entfernt werden. Zum Wechseln des Monitors die Instanz beenden,
die Zuordnung speichern und erneut starten. Kopplung und Inhalte bleiben erhalten.
Fehlt ein fest zugewiesener Monitor, wartet die Instanz im Hintergrund und startet
bei dessen Rückkehr; sie weicht nicht auf den anderen Bildschirm aus.

Windows speichert zusätzliche Instanzen unter
`%LOCALAPPDATA%\Kiosky\Player\Instances\<ID>` und eigene Einträge unter
`HKCU\Software\Microsoft\Windows\CurrentVersion\Run`.
Die bisherige Standardinstanz bleibt unter `%LOCALAPPDATA%\Kiosky\Player`.

Linux verwendet `$XDG_DATA_HOME/kiosky/players/<Server-ID>/instances/<ID>` und
je Instanz eine Desktop-Datei unter `$XDG_CONFIG_HOME/autostart`.
Die Standardpfade sind `~/.local/share` beziehungsweise `~/.config`.
Für feste Monitorzuordnungen wird X11 benötigt. In einer Wayland-Sitzung muss
XWayland verfügbar sein; die zugeordneten Browser laufen dann über XWayland.
Die Browserinstallation und deren bestehende Snap-/Flatpak-Profilpfade bleiben erhalten.

Die Linux-Instanzverwaltung öffnet eine nur auf `127.0.0.1` erreichbare Seite mit
zufälligem Zugangspfad. Das Fenster oder der Prozess kann nach der Einrichtung
beendet werden; die gestarteten Player laufen unabhängig weiter.

## Automatisch erhalten

Zuerst die Kiosky-Installation auf 3.22.0 aktualisieren. Bereits installierte
Player ab 3.21.0 fragen den verbundenen HTTPS-Server selbstständig ab:
zunächst etwa zwei Minuten nach dem Start, danach alle sechs Stunden.
`--check-updates` fordert eine frühere Prüfung an; dafür den jeweiligen installierten
Player aufrufen. Das Update erfolgt bei laufender Instanz, mit SHA-256-Prüfung und
Rücknahme bei fehlgeschlagenem Start. Ein kurzer Wiedergabeneustart ist erforderlich.
Die vorhandene Kopplung bleibt erhalten. Ältere Player benötigen einmalig den neuen Download.

Diese Instanzverwaltung betrifft Windows und Linux. macOS und die Raspberry-Pi-Images
behalten ihren bisherigen Einzelplayerbetrieb und ihre automatische Update-Routine.
