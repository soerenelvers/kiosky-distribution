# Windows-Player ab Kiosky 3.14.0

## Einrichtung

1. Kiosky auf 3.14.0 aktualisieren und unter `/player` den Windows-64-Bit-Player neu herunterladen. Derselbe Player wird von Standalone, WordPress und TYPO3 mit der jeweiligen Serveradresse bereitgestellt.
2. Die heruntergeladene EXE einmal starten. Sie kopiert sich nach `%LOCALAPPDATA%\Kiosky\Player\KioskyPlayer.exe` und setzt unter `HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Run` den Eintrag `Kiosky Display Player`. Administratorrechte sind nicht erforderlich.
3. Den angezeigten Code in Kiosky einem Display zuweisen. Danach startet der Inhalt im Vollbild. Der Player benötigt Edge oder Chrome; Edge wird bevorzugt.
4. Bei jeder weiteren Windows-Anmeldung startet die installierte Kopie automatisch, wartet auf die Erreichbarkeit des Kiosky-Servers und öffnet das gespeicherte Display im Vollbild. Die ursprüngliche Datei im Downloadordner wird dann nicht mehr benötigt.

Windows muss einen Benutzer anmelden. Windows-Autologin und Energieoptionen werden nicht eingerichtet. Ein in Windows ausdrücklich deaktivierter Start wird nicht über die Windows-Freigabeliste hinweg aktiviert.

## Kopplung und Profil

Der Player verwendet ein dauerhaftes, separates Browserprofil unter
`%LOCALAPPDATA%\Kiosky\Player\Profiles`. Der Profilordner wird aus der eingebetteten
Startadresse abgeleitet; erneute Downloads derselben Adresse behalten ihn bei.
Edge-Kiosk-/InPrivate-Modus wird vermieden, weil er die Kopplung beim Beenden
verwerfen würde. Beim ersten Wechsel vom bisherigen Player kann deshalb eine
neue Kopplung nötig sein. Danach bleibt sie in diesem Profil gespeichert.

Es gibt einen Autostart-Player je Windows-Benutzer. Ein Download für eine andere
Kiosky-Installation ersetzt die installierte Startadresse und verwendet ein eigenes
Profil. Den Player vor einem Wechsel der Installation beenden.

Ist der Server beim Start noch nicht erreichbar, prüft ein einzelner Bootstrapprozess
die Verbindung mit einem Intervall von 3 bis maximal 30 Sekunden. TLS-Prüfung bleibt
aktiv. Sobald der Server erreichbar ist, startet der Browser und der Bootstrapprozess
endet. Die Weboberfläche wiederholt auch fehlgeschlagene Kopplungs- und initiale
Inhaltsanfragen. Bereits geladene Inhalte verwenden weiterhin den bestehenden
Offline-Cache; ein vollständiger Offline-Start ohne erreichbare Startseite ist
mit diesem browserbasierten Launcher nicht garantiert.

## Autostart ausschalten oder wieder einschalten

In PowerShell:

```powershell
& "$env:LOCALAPPDATA\Kiosky\Player\KioskyPlayer.exe" --remove-autostart
```

Entfernt nur den eigenen Autostarteintrag und merkt die Abwahl im Playerordner.
Das Browserprofil und die Kopplung bleiben erhalten. Ein späterer manueller Start
aktiviert Autostart nicht erneut. Zum Wiedereinschalten:

```powershell
& "$env:LOCALAPPDATA\Kiosky\Player\KioskyPlayer.exe" --enable-autostart
```

Zur vollständigen Deinstallation zuerst Autostart entfernen, den Playerbrowser
beenden und danach den Ordner `%LOCALAPPDATA%\Kiosky\Player` löschen.
`--install-only` richtet die Benutzerinstallation ohne Browserstart ein.

## Unterstützte Downloads

Windows 10/11 mit 64 Bit, Linux 64 Bit und macOS bleiben verfügbar. Windows 32 Bit
wird einschließlich der bisherigen `.url`-Alternative nicht mehr angeboten;
alte 32-Bit-Downloadrouten antworten mit 404. Die aktuellen Pakete enthalten keine
32-Bit-Vorlage. Historische Releases werden nicht überschrieben.

## Build und Nachweise

Die native Vorlage wird aus `assets/launchers/player-launcher-windows.c` mit
MinGW-w64 neu gebaut:

```sh
pnpm build:player:windows
```

`KIOSKY_WINDOWS_CC` und `KIOSKY_WINDOWS_WINDRES` können die Pfade für GCC und windres
setzen. Der Build verwendet statisches Linken, Unicode, ASLR/NX, ein Manifest ohne
Rechteerhöhung und lesbare Versionsinformationen. Es werden weder Packer noch
Virenschutz-Ausnahmen eingesetzt. Die Ausgabe ist weiterhin nicht Authenticode-
signiert. `windows-player-build.json` dokumentiert Compiler sowie SHA-256 von
Quellen, Buildskript und ausgelieferter Vorlage; die Tests erkennen veraltete Builds.
Die individuelle Serveradresse wird nach dem Build in einen reservierten Slot
gepatcht, deshalb beziehen sich diese Prüfsummen auf die Vorlage.

`tests/windows-player-native.ps1` führt die echte ausgelieferte Windows-EXE auf
einem wegwerfbaren Windows-CI-Runner aus. Eine Browserfixture protokolliert die
übergebenen Argumente. Der Test prüft stabile Kopie, Benutzerautostart, spätere
Serververfügbarkeit, Profilwiederverwendung, Vollbildargument, Entfernen und
Wiedereinschalten. Er ist Teil des obligatorischen CI-Gates.

Nach `pnpm build` prüft `python tests/player-startup-browser-smoke.py` mit echtem
Chromium die Kopplung nach vollständigem Schließen und Wiederöffnen desselben
Plattenprofils sowie automatische Wiederholung fehlgeschlagener Kopplungs- und
Inhaltsanfragen. Die drei Transportvarianten teilen die Oberfläche; native CMS-
Routen und Installationen werden zusätzlich in der CMS-CI geprüft. Ein physischer
Windows-Neustart und die sichtbare Vollbilddarstellung auf einem Kundengerät
sind gesonderte Abnahmeprüfungen.

## Fernsteuerung ab Launcher 2.1.0 / Kiosky 3.16.0

Die neue EXE enthält die Browsersteuerung für Vollbildanforderungen auf der eigenen
Installation. Sie nutzt weiterhin denselben Profilpfad. Grenzen durch Browser und
Richtlinien sowie API und Updateverfahren: [Displaysteuerung](player-control.md).
