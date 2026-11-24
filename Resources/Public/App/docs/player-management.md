# Playerverwaltung: Namen, Bildschirmfotos, Offline-Start und Updatefreigabe

Im Displaydialog unter **Gekoppelte Player** hat jede Kopplung einen eigenen Namen,
eine Kennung und eine einzelne Identifizieren-Schaltfläche. Der Name ist unabhängig
vom logischen Display; mehrere Player können weiterhin dasselbe Display wiedergeben.
Identifikation zeigt Name und Kennung auf genau diesem Player. Die bisherige
Display-Identifikation spricht weiterhin alle zugehörigen Player an.

**Bildschirmfoto** fordert einmalig die tatsächlich vom Browser gerenderte Ausgabe
an. Windows verwendet WebView2 CapturePreview, die nativen Chromium-Player unter
Linux/Mint, Raspberry Pi und macOS eine private Prozess-Pipe ohne Debugging-Port.
Die Aufnahme umfasst das Playerfenster, keine anderen Programme des Desktops.
Browser ohne diese native Anbindung, Firefox und Tizen melden fehlende Unterstützung;
eine Konfigurationsvorschau wird nicht als Bildschirmfoto ausgegeben.

Eine Anforderung gilt 60 Sekunden, ist an die einzelne Geräteberechtigung gebunden
und kann nur einmal beantwortet werden. Fotos sind bis zu zehn Minuten abrufbar,
werden beim nächsten Foto ersetzt und gehören nicht in Inhalts-/Systemexporte.
Pro Player bleibt höchstens ein Foto gespeichert, maximal 2 MB und 1920 Pixel pro
Kante; der Client verkleinert auf höchstens 1280 Pixel. CMS-Fotos liegen im privaten
Speicher neben den geschützten Schriften, nicht in der öffentlichen Mediathek.
Abruf und Anforderung benötigen Displaysteuerungsrechte; anonyme Nutzer und
andere Player können die Fotos nicht abrufen.

## Offline-Kaltstart

Nach einem erfolgreichen Online-Start bereitet der HTTPS-Player einen dauerhaften
Service-Worker-Cache vor. App, eingebundene Skripte, CSS, lokale Schriften sowie
Bilder/Videos derselben Installation werden gespeichert. Erst ein vollständig
vorbereiteter neuer Cache ersetzt den vorherigen Cache. Die zuletzt erhaltene
Ausspielkonfiguration und die Kopplung bleiben im Browserprofil erhalten.
Der Launcher navigiert auch ohne erreichbaren Server zu seiner bisherigen Adresse,
damit der lokale Cache beim Neustart übernehmen kann. Das gilt sowohl für den
allgemeinen Starter über die Kopplungsseite als auch für zuvor direkt zugewiesene
Display-Starter. Für ältere Startadressen werden nur begrenzte SHA-256-Aliase
gespeichert; die gemeinsame Displayberechtigung wird dafür nicht zusätzlich
im Browser abgelegt. Auch die Skripte und Styles der Kopplungsseite gehören zum Cache. Das erste Einrichten erfordert
eine Serververbindung. Der Aufbau hat ein Budget von 64 MB; einzelne Medien dürfen
höchstens 32 MB haben. Externe Webseiten, YouTube-Streams und andere fremde Server
können offline weiterhin nicht geladen werden. HTTP im LAN, Browser ohne
Service Worker, gelöschte Profile und von Betriebssystemen entfernte Browserdaten
sind keine zugesicherte Offlinebasis. Ein abgeschalteter/nie erreichter Player
kann neue Inhalte, Warnungen oder eine Entkopplung erst bei erneuter Verbindung erfahren.

## Gestaffelte automatische Updates

Im Displaydialog können Administratoren die **globale** Updatefreigabe der Installation
auf pausiert, 5 %, 25 %, 50 % oder alle Player setzen. Die dauerhafte, zufällige
Gruppenzuordnung liegt nur im jeweiligen Playerprofil bzw. im Komponenten-Updater.
Bei einer Erhöhung bleiben die bereits freigegebenen Gruppen enthalten.
Bei wenigen Geräten kann eine kleine Prozentstufe auch keine Geräte enthalten.

Zuerst die aktualisierten Updater einmal mit **Alle Player** verteilen. Ältere
Updater senden noch keine Gruppenzuordnung und erhalten bei einer Teilfreigabe
keine neuen Dateien. Alternativ den aktualisierten Player einmal neu installieren.
Nach dem Grundupdate können zukünftige Versionen stufenweise freigegeben werden.
Bereits begonnene Downloads/Installationen werden beim Pausieren nicht rückgängig
gemacht. Ein Rollback erfolgt weiterhin nur über die bestehenden lokalen
Wiederherstellungsmechanismen, nicht durch ungeprüfte Versions-Downgrades.

Die Freigabe steuert native Programmupdates und das automatische Neuladen von Browser-Playern. Ein ausdrücklich ausgelöster Neustart oder Seitenaufruf lädt die Weboberfläche des installierten Serverstands; ältere Weboberflächen werden nicht parallel auf dem Server vorgehalten.

Die Freigabe steuert keine Inhalte; Inhaltsänderungen und Warnungen werden
weiterhin an alle berechtigten Player ausgeliefert. Bestehende automatische
Prüfintervalle, HTTPS-, Hash-, Versions- und Rückrollprüfungen bleiben wirksam.
