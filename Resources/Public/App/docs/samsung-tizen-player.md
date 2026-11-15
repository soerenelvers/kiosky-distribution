# Samsung Tizen TV Player

Unter `/player` steht ab 3.42.0 das Angebot **Samsung Tizen TV** bereit: ein servergebundenes ZIP mit dem TV-App-Projekt, Installationsskripten und Anleitung. Standalone, WordPress und TYPO3 verwenden dieselbe TV-Hülle und denselben Wiedergaberenderer. Die App benötigt Tizen 6.0+ (Samsung Modelljahr 2021+). Das fotografierte GU50AU9089UXZG gehört zur entsprechenden AU-Serie; die tatsächliche Firmware und Medienwiedergabe müssen auf dem Gerät abgenommen werden.

## Installation

Die vollständige Anleitung liegt im Download als README.md. Sie ist auch im Quellprojekt unter `player/tizen/README.md` enthalten. Erforderlich sind Tizen Studio, Samsung TV Extension, Samsung Certificate Extension, ein Samsung-Entwicklerkonto, die DUID des TVs und ein lokales Zertifikatsprofil. Am Fernseher wird der Entwicklermodus eingerichtet; das signierte WGT wird vom PC über den Device Manager installiert. Das Download-ZIP selbst ist nicht signiert und lässt sich nicht direkt vom USB-Stick oder TV-Browser installieren. Ein Samsung-App-Store-Eintrag ist kein Bestandteil dieses Releases.

Das Projekt hält stabile IDs (`KioskyTv01.Player`) und speichert die Kopplung im privaten App-Speicher. Ein App-Update muss mit demselben Autorzertifikat erfolgen. Die Skripte deinstallieren nicht zuerst. Die Web-Wiedergabe prüft alle fünf Minuten die Serverversion und lädt nach einem Update neu, ohne die Kopplung zu löschen. Updates der TV-Hülle müssen erneut signiert werden. Ein garantierter Einschalt-Autostart ist bei diesem Consumer-TV nicht zugesichert. Home-Taste und Systemeinblendungen bleiben unter Kontrolle der TV-Firmware.

## Bedienung

Beim ersten App-Start erscheint der sechsstellige Code. Unter Displays → Player einrichten → Code koppeln dem gewünschten Display zuordnen. Danach startet die Ausgabe unmittelbar und beim erneuten App-Start mit der gespeicherten Zuordnung. Zurück öffnet das TV-Menü; mit Pfeilen und OK fortsetzen, ausdrücklich neu koppeln oder beenden. Der Player arbeitet bildfüllend, ohne Browseradressleiste. Der Bildschirmschoner wird über Samsungs öffentliche API während des App-Betriebs deaktiviert, soweit erlaubt.

Der bestehende Player übernimmt Inhalte, Warnungen, Presets, Zeitplanung, Countdown-Serverzeit, Anzeigeorientierung, Befehle und Werbe-Ausspielnachweise. Die TV-Hülle überwacht ein Lebenszeichen des geladenen Renderers und lädt bei einem Hänger nach. Bei bloßem Netzausfall bleibt die Kopplung erhalten. Nach einem kompletten Offline-Neustart kann die TV-Hülle den Web-Renderer ohne Server nicht garantiert laden; Medien und Fremdseiten unterliegen den Codec-, Zertifikats- und Speichergrenzen des TVs.

## Architektur und Sicherheit

Die TV-App hat Zugriff auf genau den beim Download gebundenen Serverursprung. Das ZIP enthält keine Admin-Anmeldung, keinen API-Maschinenschlüssel und keine Kopplungsgeheimnisse. Nach dem Koppeln verbleibt der Display-Schlüssel im privaten TV-App-Speicher. Nachrichten zwischen Hülle und Player dürfen nur Menü/Lebenszeichen/Fortsetzen transportieren; die Hülle prüft Ursprung und sendendes Frame. Die normale Administration behält ihre bisherige Einbettungspolitik.

Nur die mit `tizen=1`, Display und Schlüssel angeforderte Player-Ansicht enthält das kompatible Rendererpaket und keine Administrationsoberfläche. Die Player-API prüft den Display-Schlüssel weiterhin. Öffentliche Kopplungsanfragen erhalten eng auf diese Endpunkte begrenzte CORS-Unterstützung; privilegierte CMS-APIs werden nicht geöffnet. Externe Server werden beim Download weder aufgerufen noch heruntergeladen. Zertifikatsdateien verbleiben am Installations-PC.

## Kompatibilitätspflege und Tests

Samsung dokumentiert Tizen 6.0 mit Chromium 76. `player/tizen/compat.js` ist ein aus den aktuellen gemeinsamen Skripten abgeleitetes Paket mit Syntaxziel Chrome 76 und eng begrenzten API-Ergänzungen. Die Node-Regression berechnet das Paket erneut und verhindert veraltete TV-Wiedergabe nach Änderungen am gemeinsamen Renderer. Nach einem solchen Edit: erst `pnpm test` zum Kompilieren (der Vergleich schlägt erwartbar fehl), dann `KIOSKY_REGENERATE_TV=1 node --test dist/tests/tizen-player.test.js`, anschließend reguläre Tests erneut ausführen und die abgeleitete Datei mit einchecken. `compat.css` wird ebenfalls aus der gemeinsamen Gestaltung abgeleitet. Eine auf die TV-Ansicht begrenzte Umrechnung ersetzt fehlende Container-Einheiten anhand der tatsächlichen Größe des nächsten Slide-, Ticker- oder Übersichtscontainers; ResizeObserver und gebündelte Renderzyklen vermeiden dauerndes Polling. Die normale Desktop-Ausgabe verwendet weiterhin ihre bisherigen Skripte und Styles.

Browserprüfungen verwenden eine isolierte App-Hülle und echte lokale Kiosky-Fixtures. Sie ersetzen weder die Signierung noch den Test auf dem Samsung-TV. Vor Dauerbetrieb Kopplung, App-/TV-Neustart, Netzwerkunterbrechung, Fernbedienung und einen längeren Medienprobelauf durchführen.

## Offizielle Grundlagen

- [Samsung Webengine-Versionen](https://developer.samsung.com/smarttv/develop/specifications/web-engine-specifications.html)
- [Zertifikate und TV-DUID](https://developer.samsung.com/smarttv/develop/getting-started/setting-up-sdk/creating-certificates.html)
- [TV-Verbindung und Entwicklermodus](https://developer.samsung.com/smarttv/develop/getting-started/using-sdk/tv-device.html)
- [Installationsregeln und USB-/Autostart-Grenzen](https://developer.samsung.com/smarttv/develop/faq/application-installation.html)
