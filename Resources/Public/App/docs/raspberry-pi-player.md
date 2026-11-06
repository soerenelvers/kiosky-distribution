# Raspberry Pi als Kiosky-Player

Die Images werden mit dem offiziellen [rpi-image-gen](https://github.com/raspberrypi/rpi-image-gen)
aus der schlanken Debian-Trixie-/Raspberry-Pi-Paketbasis gebaut. Installiert werden
Chromium, der kleine Wayland-Compositor Cage und NetworkManager; eine Desktopumgebung,
SSH-Server oder Kiosky-Server sind nicht enthalten. Der Player verbindet sich mit einer
vorhandenen Standalone-, WordPress- oder TYPO3-Installation.

## Hardware

Je ein 64-Bit-Image ist für **Zero 2 W, Pi 3, Pi 4 und Pi 5** vorgesehen. Das zum
Modell passende Image verwenden. Zero 2 W hat nur 512 MB RAM: für einfache Bild- und
Textslides vorgesehen; komplexe Webseiten, mehrere Videos oder hohe Auflösungen
benötigen leistungsfähigere Hardware. Pi 4/5 sind die bevorzugte Basis für Videos.
Der ursprüngliche Zero und Zero W (ARMv6) werden von diesen Images **nicht** unterstützt.

Physische HDMI-, WLAN-, Neustart- und Videotests auf den vier Modellen gehören zur
Hardwareabnahme; ein erfolgreich gebautes Image ersetzt diese Prüfung nicht.

## SD-Karte einrichten

1. Unter `/player` das passende `kiosky-player-<modell>.img.xz` herunterladen.
   **Anleitung und SHA-256-Prüfsummen** öffnet die dauerhafte
   [Image-Vorabversion](https://github.com/soerenelvers/kiosky/releases/tag/raspberry-pi-v1.0.0-beta.1).
   Für das private Repository ist eine GitHub-Anmeldung mit Zugriffsberechtigung nötig.
   `SHA256SUMS.txt` herunterladen und die Prüfsumme vergleichen. Diese Images sind
   ausdrücklich Beta-Versionen; die Hardwareabnahme steht noch aus.
2. Raspberry Pi Imager öffnen, **eigenes Image** wählen und auf eine SD-Karte mit
   mindestens 8 GB schreiben. Vorhandene Daten auf der gewählten Karte werden gelöscht.
   Die Imager-Optionen für SSH, Benutzer und WLAN werden hier nicht verwendet.
3. Boot-Partition anschließend erneut am Computer öffnen. Die Datei
   **Konfiguration für diese Installation herunterladen** unter `/player` liefert
   eine `kiosky-player.json` mit der richtigen Browser-Player-Adresse (auch bei
   WordPress, TYPO3 und Unterverzeichnissen). Die Datei auf die Boot-Partition
   kopieren. Bei Ethernet ist sie bereits vollständig; für WLAN den `wifi`-Block
   aus dem folgenden Beispiel ergänzen. Alternativ die mitgelieferte
   `kiosky-player.example.json` kopieren, umbenennen und bearbeiten:

   ```json
   {
     "player_url": "https://kiosky.example.org/kiosky-player-browser/",
     "wifi": {
       "ssid": "Mein WLAN",
       "password": "Mein WLAN-Passwort",
       "country": "DE",
       "hidden": false
     }
   }
   ```

   Die **Browser-Player-Adresse** aus `/player` der jeweiligen Kiosky-Installation
   übernehmen. WordPress verwendet üblicherweise `/kiosky-player-browser/`, Standalone
   `/player/browser.html`. Bei TYPO3 die von der Player-Seite angebotene Adresse nutzen.
   Installationen in Unterverzeichnissen benötigen den vollständigen Pfad.
   Für Ethernet kann der gesamte `wifi`-Block entfallen. WLAN unterstützt WPA-Personal
   (Passphrase), einschließlich versteckter SSIDs; Unternehmens-WLAN mit 802.1X und
   Anmeldeportale sind nicht Bestandteil dieser einfachen Einrichtung.
4. SD-Karte sicher auswerfen, einlegen, HDMI anschließen und den Pi einschalten.
   Der Vollbild-Player startet selbstständig, wartet auf den erreichbaren Server und
   zeigt bei der ersten Verbindung den bekannten Kiosky-Kopplungscode.
5. Unter **Displays** koppeln. Danach bleiben Kopplung und Browserprofil über Neustarts
   erhalten. Keine neue Kopplung bei gewöhnlichen Neustarts erforderlich.

Die Konfigurationsdatei wird bei jedem Start nur dann importiert, wenn sie vorhanden
und gültig ist. Nach erfolgreichem Import wird sie von der Boot-Partition entfernt.
Das WLAN-Passwort liegt anschließend in einer nur für root lesbaren NetworkManager-Datei.
Die Boot-Partition ist unverschlüsselt: Karte vor dem ersten Start entsprechend behandeln.
Zum Ändern von WLAN oder Serveradresse eine neue `kiosky-player.json` auf die Boot-Partition
legen und neu starten. Der Wechsel auf eine andere Serveradresse kann eine neue Kopplung
erfordern, weil Browserdaten an den Serverursprung gebunden sind.

## Betrieb und Updates

Systemd startet den Browser automatisch und startet ihn nach einem Absturz neu.
Das dauerhafte Profil liegt unter `/var/lib/kiosky/chromium`; privates Surfen und
flüchtige Profile werden nicht verwendet. Der Browsercache ist auf 32 MB begrenzt,
Systemprotokolle liegen im RAM (maximal 16 MB). ZRAM stellt komprimierten Arbeitsspeicher
als Swap bereit, ohne dafür auf die SD-Karte zu schreiben. Der Pi benötigt keine Anmeldung und
enthält kein voreingestelltes Benutzerpasswort oder offenen SSH-Zugang.

Beim Start erscheint zunächst eine lokale Warteseite; sie prüft den Server alle
zehn Sekunden und wechselt bei Erreichbarkeit zum Browser-Player. Nach erfolgreicher
Verbindung verwendet der Player die vorhandene Kiosky-Wiederverbindung. Bereits
geladene Inhalte können bei einem Ausfall weiterlaufen; ein vollständiger kalter
Offline-Start aller Inhalte wird nicht garantiert.

Debian-Sicherheitsupdates und Aktualisierungen aus der offiziellen Raspberry-Pi-
Paketquelle (einschließlich Browser) werden täglich automatisch installiert; automatische
Neustarts sind ausgeschaltet. Ein neuer Browser wird beim nächsten Browser-/Pi-Neustart
verwendet. Dies ist kein atomarer Image-Updater: Kernel-/Basisimagewechsel benötigen
ein neues Image. Eine neu beschriebene Karte hat ein neues Profil und muss neu gekoppelt
werden. Karten mit bereits gekoppeltem Profil niemals für andere Displays klonen.
Die generischen Images enthalten keine vorgefertigte Linux-Maschinen-ID oder
NetworkManager-Identität; diese entstehen beim ersten Start je Gerät neu.
Die Weboberfläche und Displayinhalte kommen direkt vom jeweiligen Kiosky-Server.

## Images bauen

Der Workflow `.github/workflows/raspberry-pi.yml` baut auf nativen ARM64-Runnern alle
vier Modellvarianten und legt komprimierte Images mit Prüfsummen als Artefakte ab.
Der offizielle Builder ist auf einen vollständigen Commit festgelegt. Auf einem
Debian-/Raspberry-Pi-OS-ARM64-Buildrechner:

```sh
git clone https://github.com/raspberrypi/rpi-image-gen.git
git -C rpi-image-gen checkout ff5fb451fac47debf441a5a2b39aa3ade7988bd6
sudo rpi-image-gen/install_deps.sh
bash scripts/build-raspberry-pi.sh zero2w ./rpi-image-gen
```

Ergebnis: `artifacts/raspberry-pi/zero2w/`. Andere Ziele: `pi3`, `pi4`, `pi5`.
`build.txt` dokumentiert Builder-, Kiosky-Commit und Modell. Die Paketquellen erhalten
Sicherheitsaktualisierungen; der feste Builder-Commit bedeutet keine bitidentischen
APT-Paketstände. Die Software-Lizenztexte der eingebauten Pakete bleiben im Image.
Images dürfen erst nach erfolgreichen Modelltests als stabile Hardwaredownloads
veröffentlicht werden.


## Dauerhafte Beta-Downloads veröffentlichen

Der manuell gestartete Workflow **Raspberry Pi player images** baut alle vier Images
auf ARM64-Runnern. Auf Pull Requests laufen nur die Konfigurations-/Paketprüfungen.
Zum Veröffentlichen auf `main` muss der Repository-Owner `publish=true`, die separate
Image-Version `1.0.0-beta.1` und `confirmation=PUBLISH KIOSKY PI IMAGES` angeben.
Die neueste CI des exakten Main-Commits einschließlich `CI gate` muss erfolgreich sein.
Der Publisher prüft alle vier Artefakte, Quellcommit, Builder und SHA-256-Prüfsummen.
Er erstellt eine unveränderliche **Vorabversion**, ohne den neuesten Kiosky-Stable-Release
zu ersetzen. `images.json` dokumentiert Herkunft, Größen und Prüfsummen. Bereits
existierende Image-Versionen werden nicht überschrieben. Neue Versionen benötigen
neue Versionsnummern und entsprechend aktualisierte Links im Player Center.
