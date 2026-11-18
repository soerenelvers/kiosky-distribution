# Videos im Slide- und Vorlagen-Editor (3.49.0)

Über **Video per URL** ein Video-Element anlegen. Alternativ ein vorhandenes
Video auswählen und rechts unter **Video-URL** den Link eintragen.
**URL übernehmen** prüft den Link, übernimmt ihn und speichert die Slide über die
bestehende Editor-Speicherung. Vorlagen behalten ihren bestehenden Speicherablauf.
Die native Medienauswahl bleibt daneben verfügbar: WordPress-Mediathek, TYPO3-FAL
beziehungsweise die Standalone-Bibliothek.

Unterstützt werden direkt erreichbare HTTP-/HTTPS-Videodateien (vorzugsweise MP4
mit H.264) und YouTube-Video-Links (Watch, youtu.be, Shorts, Live, Embed). Beliebige
Video-Webseiten anderer Anbieter sind keine direkt abspielbaren Videodateien.
Links mit eingebetteten Benutzernamen/Passwörtern, Skript-/Datei-/Daten-URLs sowie
YouTube-Kanal-/Playlist-Links werden hier abgewiesen. Signierte Dateilinks mit
Query-Parametern werden unverändert übernommen; deren Ablauf liegt beim Anbieter.

Die Datei wird vom Browser/Player geladen und nicht auf den Kiosky-Server kopiert.
Jeder Player benötigt Zugriff darauf. HTTPS-Seiten können unsichere HTTP-Quellen
blockieren. Ein reiner Weblink ist keine zugesicherte Offline-Datei.

Beim Wechsel von der Mediathek auf einen Link entfallen die alte Medien-ID,
Schnittgrenzen, Quelldauer, ursprünglichen Medienabmessungen und das alte Poster.
Position, Größe, Zoom, Drehung und die Videooptionen bleiben erhalten. So kann die
Medienauflösung den Link beim nächsten Abruf nicht wieder überschreiben. Die
native Videolänge wird nach erfolgreichem Metadatenabruf wie bisher gespeichert.
YouTube-Längen werden nicht ohne Anbieter-API aus Webseiten ausgelesen; die
gewöhnliche Kanal-Laufzeit bleibt maßgeblich.

## Ton, Untertitel und Bedienelemente

- **Ohne Ton abspielen**: standardmäßig aktiv. Ausschalten erlaubt Ton bei
  direkter Datei und YouTube. Browser/TV können automatischen Start mit Ton
  blockieren oder eine lokale Benutzeraktion verlangen.
- **Untertitel ausblenden**: standardmäßig aktiv. Native Untertitel-/Caption-Tracks
  werden deaktiviert. YouTube erhält die Untertitelvorgabe und über seinen
  iframe-Steuerkanal die Anforderung, das Caption-Modul zu entladen. Die
  Rückmeldungen werden nur vom konkreten iframe mit passendem Ursprung und
  Kanalbezeichner akzeptiert. Es wird kein YouTube-JavaScript im Kiosky-Fenster
  geladen; Player-Schlüssel werden nicht an den Anbieter übergeben.
- **Bedienelemente anzeigen**: standardmäßig aus für die Ausspielung. Einschalten
  zeigt native Video- beziehungsweise YouTube-Controls. Die Bearbeitungsfläche
  behält ihre bestehenden Zieh-/Größenänderungsfunktionen.

Vorgaben werden mit dem Video gespeichert und in Vorschau und Wiedergabe genutzt.
Fest ins Videobild eingebrannte Texte lassen sich nicht entfernen. Anbieterlogos,
Werbung, Einbettungssperren und abweichendes Anbieterverhalten kann Kiosky nicht
aufheben. Caption-Steuerung ist von der Unterstützung des Anbieters abhängig;
Untertitelverhalten des konkreten Videos bei Bedarf am Zielgerät prüfen.

## Prüfung und Update

Node-Tests prüfen Quellenwechsel, URL-Validierung, Rendereroptionen und den
Steuerkanal einschließlich falscher Ursprünge/Absender/Kanäle. PHP-Tests prüfen
Speicherung und Ausgabe in WordPress und TYPO3. Browserprüfungen verwenden eine
lokal erzeugte MP4-Datei sowie eine isolierte YouTube-Fixture: keine Live-Abnahme
eines konkreten YouTube-Videos. Sie prüfen den Editor, Vorschau, Player,
Metadaten, Track-Deaktivierung und Smartphone-/Tablet-Layouts über alle drei
Oberflächentransporte. Native CMS-Installation und Upgrade laufen zusätzlich in CI.

Keine Datenbankmigration oder erneute Kopplung. Verwaltung und Player nach dem
Systemupdate neu laden. Älterer Code kann gespeicherte Videoquellen weiterhin
verwenden, wertet die neuen Subtitle-/Control-Optionen aber nicht aus.
