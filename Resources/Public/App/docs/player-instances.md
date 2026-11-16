# Mehrere Displays an einem PC

Ab Kiosky 3.45.0 bieten Windows 64 Bit, Ubuntu, Debian, Linux Mint und macOS
eine geführte Einrichtung mehrerer Bildschirme. Jede Ausgabe ist eine unabhängige
Player-Instanz unter demselben angemeldeten Benutzer.
Windows muss die Bildschirme als **Erweitern**, Linux als getrennte Ausgaben konfigurieren;
bei gespiegelten Bildschirmen lässt sich kein unterschiedlicher Inhalt darstellen.

Beim ersten manuellen Start einer frischen Installation mit mehreren erkannten
Bildschirmen wird die Einrichtung angeboten. Display 1 verwendet den primären
Bildschirm. Mit **Weiteren Bildschirm anlegen** pro freiem Bildschirm einen eigenen
Player ergänzen und die Einrichtung abschließen. Die gestarteten Player dann jeweils
mit einem eigenen Kiosky-Display koppeln. Bestehende Profile und automatische
Neustarts öffnen keinen neuen Einrichtungsdialog.

Nachträglich:

1. **Kiosky Player-Instanzen** im Windows-Startmenü beziehungsweise Linux-Anwendungsmenü öffnen. Unter macOS liegt die
   Verwaltungs-App in `~/Applications/Kiosky Player-Instanzen.app`.
   Unter Windows/Linux alternativ den installierten Player mit `--instances` starten. Unter Windows öffnet F10
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

macOS speichert weitere Ausgaben unter
`~/Library/Application Support/Kiosky/players/<Server-ID>/instances/<ID>`. Jede hat
ein eigenes Chrome-/Edge-/Chromium-Profil und einen eigenen LaunchAgent unter
`~/Library/LaunchAgents`. Die Standardausgabe behält ihr bisheriges Profil und den
kanonischen LaunchAgent. Monitorzuordnungen werden über macOS-Display-UUIDs und
aktuelle Bildschirmkoordinaten bestimmt; auch links/oberhalb des Hauptbildschirms
angeordnete Monitore und unterschiedliche Skalierungen werden berücksichtigt.
Für voneinander unabhängige Vollbildausgaben in macOS „Monitore verwenden
verschiedene Spaces“ aktivieren. Gespiegelte Ausgaben bilden einen gemeinsamen
Ausspielweg. Die Hardwaregrenzen des Macs für externe Bildschirme gelten weiter.
Die Raspberry-Pi-Images behalten den Einzelplayerbetrieb.

## Erneuter Download (ab 3.30.1)

Ein neuer Standarddownload wird beim Ausführen als Aktualisierung der vorhandenen
Installation behandelt. Er erzeugt keine zusätzliche Standardausgabe. Unter Linux
werden vorherige Standarddownloads anderer Adressen abgelöst; ihre Profile bleiben
erhalten. Ausdrücklich angelegte Multi-Display-Instanzen behalten ihre Kopplung,
Monitorzuordnung und Autostarts. Mehrere Ausgaben weiter hier über **Neue Instanz**
anlegen, statt mehrere Downloads parallel zu installieren.

Unter macOS ersetzt ein neuer Standarddownload den aktiven
Standardplayer und verwendet einen kanonischen LaunchAgent
`~/Library/LaunchAgents/de.kiosky.player.plist`. Frühere verwaltete Standardplayer
und ihre adressgebundenen LaunchAgents werden abgelöst, Kopplungsprofile und
fremde Programme bleiben erhalten. Dies gilt jeweils für den angemeldeten Benutzer.

## Prüfgrenzen

Linux wird mit realen X11-Bildschirmen und getrennten Browserprofilen geprüft; die
Einrichtung zusätzlich mit isolierten Monitor-Fixtures. Die Windows-CI führt den
eingebetteten WebView2-Player aus. Die macOS-CI prüft native Bildschirmabfrage sowie
Controller, Profile, LaunchAgents, Ausfall/Rückkehr und Updates mit simulierten
Monitoren und einem Test-Browserprozess. Eine Abnahme auf physischer Mehrmonitor-
Mac-Hardware mit den jeweiligen Browser-/Spaces-Einstellungen bleibt erforderlich.
