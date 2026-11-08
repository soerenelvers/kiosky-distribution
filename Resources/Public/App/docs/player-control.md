# Displaysteuerung und Standardinhalt ab 3.16.0

Die gemeinsame Oberfläche und API stehen in Standalone, WordPress und TYPO3 bereit.

## Standardkanal oder Standardslide

Unter **Displays → Bearbeiten → Standardinhalt** zwischen **Standardkanal** und
**Standardslide** umschalten und den gewünschten Inhalt auswählen. Bestehende
Displays bleiben auf Standardkanal eingestellt. Die neue Auswahl wird gespeichert
und beim Export/Import mit neuen Inhalts-IDs korrekt übertragen.

Ein zeitlich zugewiesener Kanal wird nur ausgespielt, wenn er aktuell abspielbare
Slides enthält. Leere Kanäle, gelöschte/archivierte Slides, abgelaufene Laufzeiten
und noch nicht begonnene Laufzeiten lösen den Rückfall auf den Standardinhalt aus.
Standortbindungen bleiben wirksam. Bei einer Matrix gilt zuerst der verfügbare
Matrix-Standardkanal, danach der gewählte Displaystandard. Ist auch dieser leer
oder nicht verfügbar, bleibt die bisherige Anzeige für fehlenden Inhalt bestehen.
Es gibt keine rekursive Rückfallkette.

Der Zeitplan und die gespeicherte Zuweisung bleiben erhalten. Sobald der zugewiesene
Kanal wieder gültige Inhalte enthält, übernimmt er beim nächsten Abruf (regulär
alle fünf Sekunden) automatisch. Aktive Warnungen werden weiter überlagert.

## Fernbefehle

In der Displayverwaltung stehen **Player neu laden (F5)**, **Vollbild (F11)** und
**Display identifizieren** bereit. Über die externe API werden sie mit dem
API-Benutzerschlüssel an ein einzelnes Display gesendet:

```http
POST /api/v1/displays/DISPLAY-ID/commands
Authorization: Bearer API-SCHLUESSEL
Content-Type: application/json

{"command":"fullscreen"}
```

Weitere Werte sind `reload`, `identify` und `sync`. Unter WordPress lautet die
Basis `/wp-json/kiosky/v1/control`, unter TYPO3 `/kiosky-api/v1`. Antwort 201 bedeutet
„in der Warteschlange“; der Player fragt regulär alle drei Sekunden ab. Ein
Offline-Player verarbeitet den Befehl erst bei seiner nächsten Verbindung.

Vollbild wird eingeschaltet, nicht umgeschaltet. Wiederholte Zustellung kann es
somit nicht versehentlich ausschalten. Eine blockierte Vollbildanforderung bleibt
unbestätigt. Andere Befehle wie Identifizieren und Neu laden funktionieren weiter.
Die Auswahl wird keinem frei übergebenen Betriebssystembefehl zugeordnet.

## Vollbild und heruntergeladener Windows-Player

Der Windows-Launcher **2.1.0** enthält eine kleine Browsersteuerung und versucht,
sie in seinem eigenen Profil zu laden. Nach dem Kiosky-Update die Windowsdatei
neu herunterladen; bestehende Playerdateien ersetzen sich nicht selbst. Die
Erweiterung akzeptiert nur Nachrichten der eingebetteten Kiosky-Installation,
prüft deren Origin einschließlich Port und schaltet ausschließlich das Fenster
des sendenden Hauptdokuments ins Vollbild. Eingebettete Fremdseiten, andere
Installationen und Frames können keine Fenster steuern. Sie besitzt keine
Berechtigung zum Lesen beliebiger Tabs oder Webseiten.

Microsoft Edge wird bevorzugt. Browserhersteller oder verwaltete Richtlinien
können das Laden lokaler Erweiterungen verhindern. In diesem Fall greift die
Browserfunktion: Manche Browser erlauben die Anforderung direkt; andernfalls
zeigt der Player **Vollbild aktivieren** zur lokalen Bestätigung. Es wird kein
F11-Tastendruck in andere Programme gesendet und keine Browserrichtlinie geändert.
Linux-/macOS-Launcher erhalten ebenfalls diese Browserfunktion; eine native
Fenstersteuerung für diese Betriebssysteme ist hier nicht enthalten.

Companion **1.2.0** ergänzt drei Aktionen für Neu laden, Identifizieren und Vollbild.
Bestehende Verbindungen und Aktionen bleiben erhalten. Das neue Importpaket laden
und seine Version an der Verbindung auswählen.

## Was aktualisiert sich automatisch?

Inhalte und Zeitzuweisungen werden laufend vom Server abgefragt. Die Websoftware
wird beim Start beziehungsweise F5 mit versionsgebundenen Dateien neu geladen.
Die heruntergeladenen Launcher aktualisieren ihre EXE/App selbst noch nicht.

Für einen nativen automatischen Updater sind getrennte Konfiguration und signierte
Programmdateien erforderlich: Die bisher in die Datei gepatchte Serveradresse
muss außerhalb des signierten Programms gespeichert werden. Ein Updater prüft dann
Version, Signatur und Plattform, lädt ein vollständiges Paket, erhält Kopplung und
Profil und kann bei einem fehlgeschlagenen Start auf die vorherige Version
zurückrollen. Veröffentlichungs-Schlüssel beziehungsweise Codesignierung und ihre
sichere Einbindung in den Releaseprozess sind dafür noch einzurichten. Dieses
Update implementiert keinen unbeaufsichtigten Austausch der nativen Programme.

## Prüfung und Rücknahme

Node-/PHP-Verträge prüfen leere, abgelaufene, zukünftige und gelöschte Inhalte,
Standardwechsel, Wiederaufnahme des Zeitplans und unveränderte Zuweisungen.
`tests/player-remote-browser-smoke.py` prüft die gemeinsame Oberfläche inklusive
lokaler Vollbildbestätigung, API-Befehlen und Smartphone-/Tablet-Dialogen.
`tests/player-fullscreen-extension-browser.py` prüft auf dem Windows-CI-Runner mit
realem Edge den Fensterzustand ohne DOM-Vollbildfreigabe und lehnt fremde Origins
sowie Frames ab. Verwaltete Browserrichtlinien bleiben eine Betriebsgrenze.

Die Standalone-Migrationen 038/039 ergänzen die Standardauswahl und den neuen
Befehl und erhalten bestehende Befehle. Vor Rücknahme auf eine ältere Version
betroffene Displays auf Standardkanal zurückstellen; ältere Server kennen
Standardslides und `fullscreen` nicht. Vorhandene Datenbank-Backups beibehalten.

## Slide-Archiv und Wegweisungsbilder

Unter **Slides & Kanäle → Archiv öffnen** können bearbeitungsberechtigte Nutzer archivierte Slides suchen und wiederherstellen. Die Wiederherstellung erhält Verknüpfungen, Status und Gültigkeitszeiträume. Abgelaufene Slides benötigen weiterhin eine gültige Endzeit; Werbe-Slides erscheinen im Werbemodul. Endgültig gelöschte Slides sind nicht wiederherstellbar.

Bilder und Icons in Wegweisungszeilen können über **Zoom** zwischen 10 % und 250 % skaliert werden. 100 % ist die bisherige Ausgangsgröße; kleinere Werte verkleinern das Motiv in der bestehenden Zelle. Vorschau, gespeicherte Slide und Player verwenden denselben Faktor.

## Presets und Ausspielreihenfolge ab 3.22.4

Echte Warnungen behalten immer Vorrang. Ein Preset löst eine vordefinierte Zuspielung
sofort aus und ersetzt zunächst eine bereits laufende Zeitzuweisung. Es sperrt die
Displays jedoch nicht: Eine spätere manuelle Direktzuweisung, eine Änderung des
Display-Standards oder eine neu wirksam werdende bzw. aktiv bearbeitete Zeitzuweisung
löst das Preset für die betroffenen Displays ab. Dies gilt auch für Preset-Hinweise,
die technisch als Warnungsdesign gespeichert sind; echte Warnungen bleiben erhalten.

Die Ablösung wird pro Display gespeichert. Eine einzelne Änderung beendet daher
nicht das Gruppen-Preset auf anderen Displays. Nach Ende oder Entfernen der neuen
Zuweisung greift der reguläre Zeitplan bzw. Display-Standard; das alte Preset kehrt
nicht zurück. Ein erneutes Auslösen desselben Presets startet eine neue Zuspielung.
Auch ein während einer Offline-Phase vollständig verpasster Zeitslot wird beim
Wiederverbinden berücksichtigt. Künftige Slots lösen das Preset erst beim Start ab.

Ohne Ablösung gilt zunächst Warnung → Preset/Direktzuweisung → aktive Zeitzuweisung
→ Standardkanal oder Standardslide (bei einer Matrix zunächst deren Standardkanal).
Bereits vorhandene Preset-Ausführungen werden anhand ihrer gespeicherten
Ausführungssnapshots erkannt. Standalone ergänzt dafür eine additive Tabelle;
bestehende Inhalte und Kopplungen bleiben erhalten. Ältere Versionen ignorieren
die Ablösemarkierungen und verwenden beim Rollback wieder ihr bisheriges Verhalten.

Ein nicht mehr ausspielbarer Inhalt blockiert die folgende Ebene nicht: abgelaufene,
zukünftige, archivierte, gelöschte oder am Standort nicht verfügbare Einzel-Slides
sowie Kanäle ohne aktuell spielbare Slides werden übersprungen. Ein leeres Preset
fällt auf die Zeitzuweisung zurück; ein leerer oberster Zeitplaneintrag auf den Standard.
Die gespeicherten Zuweisungen und Verknüpfungen werden dabei erhalten.

ISO-Zeitangaben mit Offset (`+01:00`, `+02:00`) werden zeitlich mit UTC verglichen.
Vorhandene CMS-Zeitplaneinträge werden anhand von `contentType`/`contentId` gelesen;
beim Speichern werden `channelId`/`slideId` passend abgeglichen. Bei vollständig
identischer Stapelzeit, Erstellungszeit und Priorität gilt Display vor Matrix vor Gruppe.

Die CMS-Ausgaben halten Gruppenmitgliedschaften bei Änderungen am Display und an der Gruppe in beiden Richtungen synchron; reine Gruppen-Umbenennungen behalten die Mitglieder.

API-Reset-Presets und `/api/v1/presets/stop` erhalten aktive Warnungen standardmäßig. Ein ausdrückliches `endEmergency: true` bzw. `endWarnings: true` oder die separate Warnung-beenden-Aktion beendet sie weiterhin.

Siehe [Vollbild und Vordergrund der Player](player-kiosk.md) für Taskleisten, Menüleisten und Wartungszugriff.
