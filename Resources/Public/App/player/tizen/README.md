# Kiosky Samsung TV Player – Vorabversion

Für Samsung Smart TVs mit Tizen 6.0 oder neuer (Modelljahr 2021+). Das fotografierte GU50AU9089UXZG gehört zur AU-Serie von 2021. Samsung dokumentiert für dieses Modelljahr Tizen 6.0 / Chromium 76. Firmware und tatsächliche Hardware müssen vor Dauerbetrieb getestet werden. Keine Zertifizierung durch Samsung und noch kein Test auf dem konkreten Fernseher.

Dieses ZIP enthält ein an Ihre Kiosky-Installation gebundenes App-Projekt. Es enthält keine Anmeldung, keinen Display-Schlüssel und kein Samsung-Zertifikat. Es ist noch kein signiertes, direkt installierbares WGT. Samsung erlaubt auf Consumer-TVs keinen normalen WGT-Import per USB oder Download im TV-Browser.

## Einmalig am PC einrichten

1. Tizen Studio mit Samsung TV Extension und Samsung Certificate Extension installieren: https://developer.samsung.com/smarttv/develop/getting-started/setting-up-sdk/installing-tv-sdk.html
2. PC und Fernseher ins selbe vertrauenswürdige Netz bringen. Am TV unter Apps den Entwicklermodus aktivieren (bei unterstützter Fernbedienung Zahleneingabe 12345), die IP des PCs eintragen und den TV neu starten. Die genaue Bedienung ist firmwareabhängig: https://developer.samsung.com/smarttv/develop/getting-started/using-sdk/tv-device.html
3. Im Device Manager den TV verbinden. Im Certificate Manager ein **Samsung TV**-Zertifikatsprofil erstellen und die DUID jedes Ziel-TVs in das Distributor-Zertifikat aufnehmen. Dazu ist Ihr Samsung-Entwicklerkonto nötig. Zertifikate bleiben auf Ihrem PC. Im Device Manager „Permit to install applications“ ausführen, wenn erforderlich.
4. Das Verzeichnis `app` als Tizen-Web-Projekt importieren. Mit dem Zertifikatsprofil bauen, signieren und „Run As → Tizen Web Application“ auf dem verbundenen TV ausführen. Der Name und die IDs in config.xml bleiben unverändert.
5. Alternativ Tizen CLI in PATH aufnehmen und im entpackten Ordner `./install.sh PROFIL GERAETENAME` (Linux/macOS) oder `./install.ps1 -Profile PROFIL -Target GERAETENAME` (PowerShell) ausführen. Der Gerätename ist der Name im Tizen Device Manager, nicht die IP. Die TV-Verbindung und die Zertifikatsfreigabe vorher dort einrichten. Die Skripte installieren ein Update ohne vorherige Deinstallation.

## Koppeln und nutzen

Die App zeigt einen sechsstelligen Code. Diesen in Kiosky unter Displays → gewünschtes Display → Player einrichten → Code koppeln eingeben. Die App startet die Ausgabe bildfüllend. Die Zuordnung bleibt im privaten App-Speicher erhalten, solange App-Daten nicht gelöscht werden. Zurück öffnet das Player-Menü; mit Pfeilen und OK fortsetzen, neu koppeln oder beenden. Home und Samsung-Systemeinblendungen können Apps nicht unterdrücken.

Die App deaktiviert während ihres Betriebs den Samsung-Bildschirmschoner, soweit die Firmware dies erlaubt. Sie kann einen ausgeschalteten TV nicht selbst einschalten. Automatischer App-Start nach Einschalten ist bei Consumer-TVs nicht verbindlich verfügbar; vorhandene TV-Einstellungen zur letzten App sind modellabhängig. Für garantierten Boot-Autostart einen bisherigen Hardware-Player verwenden.

## Updates, Netzwerk und Betrieb

Inhalte und Web-Player laden die aktuelle Version vom verbundenen Kiosky-Server. Ein Update der installierten TV-Hülle muss neu mit **demselben Autorzertifikat**, denselben IDs und einem gültigen Distributor-Zertifikat signiert und installiert werden. Nicht deinstallieren: dabei löscht Tizen die Kopplung. Die App umgeht die Samsung-Signierung nicht und installiert keine unsignierten Updates.

HTTPS mit einem vom TV vertrauten Zertifikat verwenden. Die Uhrzeit des TVs muss stimmen. Kein öffentlicher Zugriff auf den Entwicklermodus bzw. SDB-Port. Nach Einrichtung den Entwicklungs-PC-Zugriff nach Bedarf beschränken. Die App erlaubt Netzwerkzugriff nur zum gebundenen Kiosky-Ursprung; eingebettete Fremdseiten und Medien unterliegen weiterhin den Sicherheits- und Codecgrenzen des TVs. Bei zusätzlichen globalen Webserver-Headern darf die öffentliche Player-Ansicht `?tizen=1` nicht durch X-Frame-Options oder frame-ancestors gesperrt sein; die Administration bleibt geschützt.

Bei Netzausfall versucht die Kopplung erneut zu verbinden. Ein schon geladener Web-Player kann seinen vorhandenen Cache nutzen; der vollständige Offline-Neustart der TV-App ist ohne erreichbaren Server nicht garantiert. Keine unbegrenzte Kopplungsschleife nach einem kurzen Ausfall; gespeicherte Zuordnungen werden nur nach ausdrücklicher Bestätigung entfernt.

## Abnahme auf Ihrem TV

Nach Signierung und Installation prüfen: Code koppeln; Bilder/Text/Countdown/MP4 wiedergeben; App schließen und starten; TV ausschalten und wieder starten; Netzwerk kurz trennen; Zurück-Menü und Fortsetzen; Display-Neuladen/Identifizieren über Kiosky. Danach einen längeren Probelauf mit Ihren echten Medien durchführen. Browser- und SDK-Validierung ersetzen diesen Hardwaretest nicht.
