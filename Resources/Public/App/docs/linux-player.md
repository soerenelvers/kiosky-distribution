# Linux- und Linux-Mint-Player

Unter **`/player` → Linux Mint Player** steht ein eigener Mint-Download bereit.
Der Download **Linux Player** für Ubuntu/Debian verwendet denselben Starter.
Beide Varianten werden in Standalone, WordPress und TYPO3 mit der öffentlichen
Browser-Player-Adresse der jeweiligen Installation ausgeliefert, auch bei Sites
in Unterverzeichnissen. Es wird keine Serveradresse von Hand eingetragen.

## Voraussetzungen

- 64-Bit-PC mit Linux Mint 21/22 oder LMDE 6/7; entsprechend Ubuntu ab 22.04
  beziehungsweise Debian ab 12. Erforderlich sind glibc ab 2.34 und Python 3 ab 3.8.
- Eine angemeldete grafische Desktop-Sitzung. Der XDG-Autostart wird unter
  Cinnamon, MATE und Xfce verwendet.
- Firefox, Chromium oder Google Chrome. Auch die genannten Flatpak-Browser und
  Chromium/Firefox als Snap werden erkannt. Browser und Python werden nicht
  automatisch heruntergeladen oder mit Administratorrechten installiert.

## Einrichten

1. In der gewünschten Kiosky-Installation `/player` öffnen und **Linux Mint Player**
   herunterladen. Für eine feste Vorabzuordnung kann in der Standalone-
   Displayverwaltung auch der Mint-Download des betreffenden Displays verwendet werden.
2. Die Datei lokal speichern und ausführbar machen: im Dateimanager über
   **Eigenschaften → Zugriffsrechte → Ausführen erlauben**, oder im Terminal:

   ```sh
   chmod +x ./Kiosky-Universal-Player-linux-mint-x64
   ./Kiosky-Universal-Player-linux-mint-x64
   ```

3. Als normaler Desktop-Benutzer starten, ohne `sudo`. Der Starter installiert
   sich im Benutzerprofil und legt seinen Autostarteintrag an. Dafür sind keine
   Administratorrechte erforderlich.
4. Beim ersten Verbinden erscheint der sechsstellige Kopplungscode. Diesen unter
   **Displays** zuordnen. Nach erfolgreicher Kopplung wird der Displayinhalt geladen.
5. Nach Abmelden/Anmelden oder einem Neustart öffnet sich derselbe Player im
   Vollbild mit dem gespeicherten Display. Die ursprüngliche Datei im Download-
   Ordner darf nach dem ersten erfolgreichen Start entfernt werden.

Autostart bedeutet **nach der grafischen Benutzeranmeldung**. Eine automatische
Linux-Anmeldung oder Änderung am Anmeldebildschirm richtet der Starter nicht ein.
Bei noch fehlender Verbindung erscheint eine lokale Warteseite mit wiederholtem
Verbindungsversuch. Nach dem Laden übernimmt die bestehende Kiosky-Wiederverbindung.

## Dauerhafte Kopplung und Browser

Pro Downloadadresse wird eine stabile Installation unter
`${XDG_DATA_HOME:-~/.local/share}/kiosky/players/<Kennung>/` angelegt. Hier liegen
`Kiosky-Player`, die Browserkonfiguration und das dauerhafte Browserprofil. Es wird
kein privates oder temporäres Profil verwendet. Derselbe erneut heruntergeladene
Player verwendet diese Installation weiter, ohne die Kopplung zu löschen.

Chrome/Chromium werden bevorzugt; sonst wird Firefox genutzt. Ein einmal gewählter
Browser bleibt gespeichert, damit eine später zusätzlich installierte Alternative
keine neue Kopplung auslöst. Bei entferntem Browser denselben Browser erneut
installieren. Gewöhnliche Updates dieses Browsers ändern die Profilzuordnung nicht.
Flatpak- und Snap-Profile liegen innerhalb des jeweiligen zulässigen App-
Speicherbereichs. Persönliche Standard-Browserprofile bleiben getrennt.

Eine andere Serveradresse oder ein anderer zugeordneter Display-Download verwendet
ein eigenes Kopplungsprofil und kann eine neue Kopplung benötigen. Ab 3.30.1
löst ein solcher neuer Standarddownload den vorherigen Standardplayer ab. Ein neuer Linux-
Benutzer hat ebenfalls ein eigenes Profil. Das Löschen des Playerprofils entfernt
die gespeicherte Kopplung.

## Autostart verwalten

Der Eintrag liegt unter
`${XDG_CONFIG_HOME:-~/.config}/autostart/kiosky-player-<Kennung>.desktop` und ist
in Mints **Startprogramme** sichtbar. Er startet die installierte Kopie, enthält
keine Display-Schlüssel und funktioniert auch bei Leerzeichen/Sonderzeichen im
Benutzerpfad. Ein bereits laufender Player wird nicht doppelt geöffnet.

Autostart lässt sich dort deaktivieren. Alternativ mit derselben heruntergeladenen
oder installierten Datei:

```sh
./Kiosky-Universal-Player-linux-mint-x64 --disable-autostart
./Kiosky-Universal-Player-linux-mint-x64 --enable-autostart
```

Deaktivieren löscht keine Kopplung. Ein gewöhnlicher manueller Start aktiviert
einen absichtlich deaktivierten Autostart nicht erneut.

## Aktualisieren

Nach dem Serverupdate auf 3.19.0 den Linux-/Mint-Player neu herunterladen und
starten. Alte Linux-Downloads erhalten Autostart nicht allein durch das Serverupdate.
Der bisherige Linux-Starter verwendete das allgemeine Browserprofil; beim Wechsel
auf das neue isolierte Profil einmal neu koppeln. Anschließend bleibt die Kopplung
bei Neustarts und erneutem Download derselben Variante/Adresse erhalten.

Die Weboberfläche und Displayinhalte werden vom Kiosky-Server geladen. Ab 3.21.0 erhält auch der native Starter automatische
Updates vom verbundenen Server; Details stehen unter [Player-Updates](player-updates.md).
Erneutes Herunterladen und Ausführen bleibt für die manuelle Aktualisierung möglich.

## Build und Prüfung

Kanonische Linux-Quellen sind `assets/launchers/player-launcher-linux.c` und
`assets/launchers/linux-player.py`. `linux-player-script.h` wird daraus erzeugt;
`linux-player-build.json` dokumentiert Compiler, Flags sowie Eingabe-/Ausgabehashes.
Der alte generische `player-launcher.c` bleibt der Windows-/macOS-Einstieg.
Für Linux die kanonische Quelle verwenden, damit Autostart/Profil erhalten bleiben.

Header nach einer Änderung erzeugen:

```sh
python3 - <<'PY'
from pathlib import Path
import json
root=Path('assets/launchers')
source=(root/'linux-player.py').read_text()
(root/'linux-player-script.h').write_text(
    '/* Generated from linux-player.py; see docs/linux-player.md. */\n'
    'static const char linux_player_script[] =\n'
    + ''.join(json.dumps(line,ensure_ascii=False)+'\n'
              for line in source.splitlines(keepends=True)) + ';\n')
PY
gcc -std=c11 -O2 -s -Wall -Wextra -Werror \
  -Wl,--build-id=none,-z,relro,-z,now,-z,noexecstack \
  assets/launchers/player-launcher-linux.c \
  -o assets/launchers/bin/kiosky-player-linux-x64
```

Danach die Eingabe-/Ausgabehashes im Buildnachweis aktualisieren und die
glibc-Symbolanforderungen mit `readelf --version-info` prüfen. Die Tests erkennen
veraltete Header oder Binärdateien.

`python3 tests/linux-player-test.py` prüft isolierte Installation, Autostart,
Firefox-Argumente, Profilpfade, Browserbindung, Deaktivieren und Verbindungswiederholung.
Nach `pnpm build` prüft `python3 tests/linux-player-browser-smoke.py` den echten
Download mit Chromium: koppeln, Inhalt anzeigen, Browser beenden, Download löschen
und über den echten Gio-Autostarteintrag erneut öffnen. Python Playwright, Chromium,
Gio und `desktop-file-validate` werden benötigt. Die Container-Testhülle benötigt
Headless-/Sandbox-Testflags; diese stehen nicht im ausgelieferten Starter.

Diese Tests ersetzen keinen echten Ab-/Anmeldetest auf allen Mint-Desktops oder
eine physische Mint-Hardwareabnahme. Browserkiosk ist keine vollständige Absicherung
des Betriebssystems. Grundlagen: [XDG-Autostart](https://specifications.freedesktop.org/autostart/latest/),
[Desktop Entry](https://specifications.freedesktop.org/desktop-entry/latest-single/)
und [Firefox-Kioskmodus](https://support.mozilla.org/en-US/kb/firefox-enterprise-kiosk-mode).

## Vorhandene Installation ersetzen (ab 3.30.1)

Die heruntergeladene Datei ausführbar machen und einmal starten. Sie aktualisiert
die vorhandene Standardinstallation, auch während der bisherige Player läuft.
Pro Benutzer bleibt eine aktive Standardinstallation mit einem Eintrag
**Kiosky Player-Instanzen** im Anwendungsmenü. Verwaltete Standardplayer anderer
Downloadadressen und deren Autostarts werden abgelöst. Ihre Kopplungsprofile bleiben
für eine spätere Rückkehr erhalten. Fremde Autostarts werden nicht verändert.
Zusätzliche, über die Instanzverwaltung angelegte Multi-Display-Instanzen behalten
ihre eigenen Profile und Autostarts. Ein älterer Download setzt die bestehende
Installation derselben Adresse nicht zurück. Absichtlich deaktivierter Autostart
bleibt deaktiviert; `--enable-autostart` aktiviert ihn ausdrücklich.

Die aktive Standardadresse steht als Kennung in `kiosky/players/primary.json`;
unter den bisherigen adressgebundenen Verzeichnissen bleiben die Profile erhalten.
Weitere gleichzeitig laufende Ausgaben ausschließlich in der Multi-Display-
Instanzverwaltung anlegen.
