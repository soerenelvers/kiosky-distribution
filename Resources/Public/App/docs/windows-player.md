# Windows-Player ab Kiosky 3.20.0

Der Windows-64-Bit-Player verwendet ab Version 3.0.0 ein eigenes Win32-Fenster mit
eingebetteter Microsoft-WebView2-Engine. Er startet direkt im Vollbild. Die
Runtime-Prozesse heißen `msedgewebview2.exe`; Edge/Chrome werden nicht als externe
Browserfenster gestartet. Standalone, WordPress und TYPO3 liefern dieselbe native
Vorlage mit der Adresse der jeweiligen Installation aus.

## Installieren oder vom bisherigen Player wechseln

1. Kiosky aktualisieren und den **Windows-64-Bit-Player** im Player-Center neu
   herunterladen und einmal starten. Bisherige EXEs enthalten noch keinen
   Updater. Der neue Player ab Kiosky 3.21.0 übernimmt danach neue Player-Versionen
   automatisch vom verbundenen HTTPS-Server. [Ablauf und Rücknahme](player-updates.md).
2. Die neue EXE einmal starten. Ab 3.30.1 beendet sie gezielt die laufende installierte Standardkopie und ersetzt diese.
   Sie installiert sich benutzerbezogen nach
   `%LOCALAPPDATA%\Kiosky\Player\KioskyPlayer.exe`.
3. Fehlt eine geeignete stabile WebView2-Laufzeit, deren einmalige Einrichtung
   bestätigen. Der mitgelieferte offizielle Microsoft-Bootstrapper benötigt dazu
   Internetzugriff. Ein automatischer Windows-Start installiert keine Runtime
   ohne Bestätigung. Kiosky fordert keine Administratorrechte an.
4. Ein neues Display über den Kopplungscode zuweisen. Bei unveränderter
   Server-/Startadresse werden die LocalStorage-Daten des bisherigen dedizierten
   Kiosky-Edge-Profils übernommen. Dessen Profil muss dabei geschlossen sein.
   Originaldaten werden nicht gelöscht und können für einen Rückwechsel dienen.
5. Nach Windows-Abmeldung und erneuter Anmeldung startet die installierte Kopie
   wieder mit demselben Profil. Die Downloadkopie wird nicht mehr benötigt.

Ohne zusätzliche Multi-Display-Instanzen gibt es ein Playerfenster und einen Autostarteintrag pro Windows-Benutzer. Ein
weiterer Start öffnet keine zweite speicherverbrauchende Wiedergabe. Ein Download
für eine andere Kiosky-Installation ersetzt die Startadresse und verwendet den
zu dieser Adresse gehörenden Profilordner.

Windows muss einen Benutzer anmelden. Windows-Autologin und systemweite
Energieoptionen werden nicht eingerichtet. Ein durch Windows deaktivierter
Autostart wird nicht an der Windows-Freigabeliste vorbei aktiviert.

## Daten und Wiederherstellung

Die adressgebundenen Profile bleiben unter
`%LOCALAPPDATA%\Kiosky\Player\Profiles`. Die eingebettete Engine verwendet darin
`WebView2`; das ursprüngliche Edge-Profil bleibt unverändert erhalten. Es werden
nur LocalStorage-Daten des dedizierten Kiosky-Profils migriert, keine privaten
Browserprofile oder deren Anmeldungen. Eine geänderte Serveradresse/Origin kann
wie bisher eine neue Kopplung erfordern.

Bei Navigation-/Renderer-/Browser-Prozessfehlern stellt der native Host seine
Wiedergabe mit Pausen von 1 bis maximal 30 Sekunden wieder her. Das dauerhafte
Profil wird dabei nicht zurückgesetzt. Netzwerkfehler und HTTP-Serverfehler
werden wiederholt; ist die Weboberfläche bereits geladen, verwendet sie ihre
vorhandenen Wiederholungs- und Cachefunktionen. Ein vollständiger Offline-Start
ist ohne eine zuvor erreichbare Startseite nicht zugesichert.

Beim Slidewechsel werden Video-/Audio-Decoder und Iframes ausdrücklich entladen.
Der HTTP-Diskcache der Engine ist auf 64 MiB begrenzt; das ist keine RAM-Obergrenze.
Große Videos, Webseiten und Grafiken sowie Treiber und das systemweite Commit-
Limit bestimmen den tatsächlichen Speicherbedarf. `0xe0000008` ist ein Chromium-
Out-of-Memory-Code; der Enginewechsel allein beweist keine Behebung jeder
möglichen Ursache auf einem konkreten Windows-10-PC.

## Vollbild und Fernsteuerung

F11 schaltet zwischen eigenem Vollbildfenster und Fenstermodus um. Der vorhandene
Kiosky-Vollbildbefehl setzt das eigene Fenster wieder auf Vollbild, ohne eine
Browsererweiterung. Die native Vollbildbrücke akzeptiert nur die konfigurierte
Origin; externe Webseiten können weiterhin als Slide-Inhalt in Frames laufen.
Alt+F4 schließt den Player. Autostart und Kopplungsdaten bleiben dabei erhalten.

TLS-Prüfung und Browser-Sandbox bleiben aktiv. Es werden keine Virenschutz-
Ausnahmen eingerichtet. Die äußere, individuell mit einer URL gebundene Kiosky-
EXE ist weiterhin nicht Authenticode-signiert. Die unverändert eingebetteten
Microsoft-Komponenten tragen Microsoft-Signaturen; die Windows-CI prüft deren
Vertrauensketten. Lizenztexte: [Microsoft-Komponenten](windows-webview2-third-party-license.txt).

## Autostart und Grafikmodus

In PowerShell:

```powershell
& "$env:LOCALAPPDATA\Kiosky\Player\KioskyPlayer.exe" --remove-autostart
& "$env:LOCALAPPDATA\Kiosky\Player\KioskyPlayer.exe" --enable-autostart
```

Die Abwahl wird im Playerordner gespeichert und durch manuelle Starts nicht
rückgängig gemacht. `--install-only` richtet die Kopie und den Autostart ohne
Browserstart ein. Die eigene Run-Zeichenfolge steht unter
`HKEY_CURRENT_USER\Software\Microsoft\Windows\CurrentVersion\Run` als
`Kiosky Display Player` und verweist auf die installierte Kopie, nicht den Download.

Bei einem gezielt untersuchten Grafiktreiberproblem kann der Grafikmodus für
diesen Player umgestellt werden. Zuerst das laufende Playerfenster schließen:

```powershell
& "$env:LOCALAPPDATA\Kiosky\Player\KioskyPlayer.exe" --software-rendering
& "$env:LOCALAPPDATA\Kiosky\Player\KioskyPlayer.exe" --hardware-rendering
```

Der Standard bleibt Hardwarebeschleunigung; gesunde Player werden nicht pauschal
auf Software-Decodierung umgestellt. Der gewählte Modus bleibt für Autostart
bestehen. Software-Darstellung kann bei hochauflösenden Videos mehr CPU benötigen.

## Diagnose und Abnahme

`player.log` und höchstens eine rotierte `player.previous.log` liegen im
Installationsordner. Sie enthalten Fehlerart, Zeitpunkt, freien RAM/Commit und
Browser-Privatspeicher. URLs und Kopplungsschlüssel werden nicht protokolliert.

`pnpm build:player:windows` erstellt die native Vorlage mit MinGW-w64. Quell-,
Komponenten- und Ausgabe-Hashes stehen in `windows-player-build.json`, SDK- und
Downloadnachweise in `vendor/webview2/provenance.json`. Alle Komponenten sind
gepinnt; der Build benötigt keinen dynamischen SDK-Download.

Die verpflichtende Windows-CI verwendet die echte EXE und Runtime auf einem
frischen Wegwerf-Runner: Profilübernahme aus echtem Edge, Wiederanmeldung mit
bestehender Kopplung, Autostart, F11/Vollbild, Herkunftsgrenzen, kontrollierte
Renderer-/Runtimeausfälle und Medienlast. Debugging und Browserrichtlinien-Änderungen sind dafür nicht erforderlich. Die frühere Browsererweiterung wird für ältere installierte
Player weiterhin unterstützt und separat geprüft.

Zusätzlich prüft `python3 tests/player-media-memory-browser-smoke.py` generierte
Video-/Iframe-Wechsel in lokalem Chromium. Privater Prozessspeicher ist je nach
Sandbox nicht auslesbar; ein JavaScript-Heap-Test ersetzt keine Windows-RAM-
Langzeitmessung. Ein Langzeittest auf dem betroffenen Windows-10-Gerät bleibt
für die konkrete Fehlerursache erforderlich.

Zur Deinstallation Autostart entfernen, das eigene Playerfenster schließen und
den eigenen Installationsordner löschen. Andere Browserprofile werden nicht
verändert. Windows-32-Bit-Downloads bleiben eingestellt.

Ab 3.30.1 aktualisiert auch ein erneuter Download die laufende Installation. Die
Datei zuerst ausführen; der reine Browserdownload verändert noch keine Installation.
Der Austausch verwendet den festen Installationspfad und schließt nur das dazu
gehörende Playerfenster mit überprüftem Programmpfad. Ein absichtlich deaktivierter
Autostart und die adressgebundenen Kopplungsprofile bleiben erhalten. Ausdrücklich
angelegte Multi-Display-Instanzen werden dabei nicht beendet oder neu gekoppelt.
Ein älterer Download derselben Serverbindung setzt eine neuere Version nicht zurück.
