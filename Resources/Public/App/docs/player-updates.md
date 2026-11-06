# Automatische Playerupdates ab Kiosky 3.21.0

Ein Kiosky-Serverupdate stellt auch die Playerdateien bereit. Die neuen installierten
Player prüfen ihren **verbundenen Kiosky-Server**, laden eine neuere Version herunter,
prüfen sie und starten anschließend wieder mit derselben Kopplung im Vollbild.
Dafür werden keine GitHub-Zugangsdaten auf Displays benötigt. Standalone, WordPress
und TYPO3 stellen denselben öffentlichen Kanal `/kiosky-player-update/` bereit;
CMS-Unterverzeichnisse bleiben Teil der Adresse.

## Einmaliger Wechsel

Bisherige EXEs, Linux-/macOS-Starter und Pi-Images enthalten noch keinen solchen
Updater. Sie können ihn nicht nachträglich allein durch ein Serverupdate erhalten.

- **Windows:** Kiosky aktualisieren, den neuen 64-Bit-Player herunterladen, das alte
  Fenster schließen und den neuen Download einmal starten. Benutzerprofil,
  Kopplungsübernahme und der vorhandene Autostart bleiben erhalten.
- **Linux/Mint:** neuen Download einmal als denselben Desktop-Benutzer starten.
  Der adressgebundene Ordner und das bisherige Browserprofil werden weiterverwendet.
- **macOS:** neue App einmal als denselben Benutzer öffnen. Sie richtet einen
  eigenen dauerhaften Browserordner und einen LaunchAgent für die Anmeldung ein.
  Chrome, Edge oder Chromium sind erforderlich. Der alte macOS-Starter hatte
  noch kein eigenes Profil; dort kann einmalig eine neue Kopplung nötig sein.
- **Pi:** Images ab **1.0.0-beta.2** enthalten den neuen Timer. Das vorherige Beta-Image
  kann sich ohne diesen Timer nicht selbst umbauen. Für eine vorhandene SD-Karte
  ist eine einmalige Migration durch einen Administrator oder das neue Image nötig.
  Ein vollständiges Neubeschreiben löscht die vorhandenen Kartendaten. Kopplung
  und WLAN lassen sich nur erhalten, wenn die bestehenden Profil-/Konfigurationsdaten
  vorher gesichert und auf demselben Gerät wiederhergestellt werden. Nach dieser
  einmaligen Einrichtung kommen Playerupdates ohne erneutes Flashen.

Die Images bleiben bis zur physischen HDMI-/WLAN-/Videoabnahme Vorabversionen.
Die Downloadseite weist darauf hin. [Pi-Einrichtung](raspberry-pi-player.md).

## Ablauf und Grenzen

Desktop-Player prüfen etwa zwei Minuten nach dem Start, anschließend alle sechs
Stunden. Windows verteilt den ersten Abruf zusätzlich leicht zeitlich. Ein Update
unterbricht die Wiedergabe für den geordneten Neustart. Bei einem Startfehler wird
auf die vorherige Version zurückgewechselt; fehlgeschlagene Versionen lösen keine
sofortige Neustartschleife aus. Das Betriebssystem und dessen Benutzeranmeldung
werden nicht geändert. Profile, Serveradresse, Kopplung, Grafikmodus und die
Autostart-Abwahl liegen außerhalb der ausgetauschten Programmdateien.

Reine Browser-Player prüfen alle fünf Minuten auf eine neuere Webversion und
laden ihre Seite dann neu. Sie behalten den bisherigen Browser-Speicher. Ohne
Netzwerk oder bei einem älteren Server läuft die bestehende Wiedergabe weiter.

Der Pi-Timer prüft nach dem Start (mit bis zu fünf Minuten Zufallsverzögerung) und
alle sechs Stunden. Er ersetzt ausschließlich den festgelegten Satz von
Kiosky-Programm-/Dienstdateien, hält vorherige Dateien vor und prüft nach dem
Neustart beide Dienste. Bei einem Fehler stellt er die vorherigen Dateien wieder
her. `/etc/kiosky/player.json`, NetworkManager-Verbindungen und
`/var/lib/kiosky/chromium` sind nicht Teil dieses Dateisatzes. Ein unabhängiger, netzwerkfreier Wiederherstellungsdienst des Basisimages holt
bei einem abgebrochenen Austausch die gesicherten Komponenten zurück. Dies ist
ein Komponenten-Updater, kein A/B-Betriebssystem- oder SD-Karten-Flasher.

Microsoft aktualisiert die WebView2-Laufzeit über seine vorhandene Updatefunktion.
Desktop-Browser verwenden ihre eigenen beziehungsweise die Betriebssystem-
Updater. Die Pi-Images installieren bereits automatisch Debian-/Raspberry-Pi-
Sicherheitsupdates. Laufende Browser verwenden neue Engine-Dateien nach ihrem
nächsten Neustart. Kernelwechsel können weiterhin einen Betriebssystem-Neustart
benötigen; Kiosky erzwingt weder Windows-/macOS-Neustarts noch SD-Kartenwechsel.

## Vertrauensmodell

Automatische **Programmupdates benötigen HTTPS** mit gültiger Zertifikatskette.
Nur Loopback-Adressen dürfen für lokale Tests HTTP verwenden. Ein über HTTP im LAN
betriebener Player spielt weiterhin Inhalte, übernimmt aber keinen Programmcode
automatisch. Das gilt auch für private IP-Adressen.

Vertrauensquelle ist die bei der Installation gewählte Kiosky-Instanz und deren
Administrator. Wer deren Server-/Plugincode veröffentlichen darf, darf auch
Playercode bereitstellen. SHA-256 schützt die Übertragung/Zuordnung gegen
beschädigte oder vertauschte Dateien; es ist keine unabhängige Herausgebersignatur.
Weiterleitungen, fremde Downloadadressen aus einem Manifest, andere Plattformen,
zu große Dateien und ältere Versionen werden abgewiesen. Zertifikatsprüfung,
Browser-Sandbox und Windows-Ausführungsrichtlinien werden nicht abgeschaltet.

## Prüfen oder pausieren

Windows:

```powershell
& "$env:LOCALAPPDATA\Kiosky\Player\KioskyPlayer.exe" --check-updates
```

Linux/Mint: den installierten Starter im bisherigen Spielerordner mit
`--check-updates` aufrufen. `--disable-updates` und `--enable-updates` pausieren
beziehungsweise aktivieren die automatische Prüfung.

macOS: im eigenen Ordner unter `~/Library/Application Support/Kiosky/players/`
die leere Datei `update.request` anlegen, um beim laufenden Player die Prüfung
anzufordern. `updates.disabled` pausiert sie; Entfernen aktiviert sie wieder.
Windows unterstützt dieselbe `updates.disabled`-Datei im eigenen Playerordner.

Pi: `systemctl start kiosky-update.service` prüft sofort. Eine Datei
`/var/lib/kiosky-updates/disabled` pausiert den Timerinhalt. Der Timer bleibt
sichtbar und kann durch den Administrator mit systemctl verwaltet werden.
Neue Images enthalten keinen offenen SSH-Zugang; Wartung erfordert entsprechend
vorhandenen lokalen Administratorzugriff.
