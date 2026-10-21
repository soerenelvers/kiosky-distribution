# Speisekarten-Slides

Ab Kiosky 3.5.0 in Standalone, WordPress und TYPO3; seit 3.6.0 als eigenes
**Speisekarten-Modul** in Navigation und Einstellungen. Einstieg:
`https://deine-kiosky-adresse/speisekarten` (bei Unterverzeichnissen entsprechend
unterhalb der Installation). Der Arbeitsbereich **Speisekarten** öffnet den Inhaltseditor direkt in Kiosky.
Die eigenständige Küchenadresse `/speisekarten` bleibt ebenfalls erhalten.

## Einrichtung durch die Administration

1. Mit dem vorhandenen Administrator-Konto **Einstellungen → Speisekarten** öffnen.
   **Speisekarten-Modul aktivieren** ein- oder ausschalten. Der gleiche Schalter
   steht unter **Einstellungen → Module**; dort lässt sich auch die Reihenfolge
   in der Kiosky-Navigation ändern.
2. Standardvorlage, Standardformat, Titel, Untertitel und Fußzeile festlegen und
   **Speisekarten-Einstellungen speichern** wählen. Diese Vorgaben gelten nur für
   neue Karten; vorhandene Karten werden nicht umgeschrieben.
3. Im selben Einstellungsbereich unter **Karten und Berechtigungen → Neue Speisekarte
   anlegen** internen Namen, Vorlage und Format wählen.
   Enthalten sind **Blau · Klar**, **Hell · Bistro** und **Dunkel · Abendkarte**,
   jeweils für Hoch- und Querformat.
4. Neue Karten werden als normale Slides mit dem Tag **Speisekarte** und Status
   **Entwurf** angelegt. In der normalen Slide-Verwaltung Layout und Status
   einstellen, einem Kanal hinzufügen und wie gewohnt zeitlich planen.
5. Unter **Speisekarten-Rechte und Zuweisung** ein vorhandenes Benutzerkonto
   auswählen, **Speisekarten bearbeiten erlauben** aktivieren und die zulässigen
   Karten markieren. **Rechte speichern** übernimmt die Zuweisung sofort.

Für Küchenkonten empfiehlt sich die bestehende Rolle **Betrachter**. Das eigene
Speisekarten-Recht erweitert diese Rolle ausschließlich um die Bearbeitung der
zugewiesenen Karten im Inhaltseditor. Bestehende allgemeine Benutzerrechte werden
nicht eingeschränkt oder ersetzt. Administratoren verwalten alle Karten und die
Zuweisungen. In Standalone gelten zusätzlich die vorhandenen Standortrechte.
Ein erteiltes Recht ohne ausgewählte Karte erlaubt keine Kartenbearbeitung.

WordPress verwendet vorhandene Benutzer mit `kiosky_access`, TYPO3 vorhandene
Backendbenutzer mit Kiosky-Rolle und Zugriff auf das Modul **System → Kiosky**.
Die Rechteverwaltung legt keine zweite CMS-Benutzerdatenbank an.

## Ein- und Ausschalten

Bei bestehenden Installationen ist das Modul zunächst eingeschaltet. Ausschalten
blendet den Arbeitsbereich aus und sperrt die Inhalts- und Zuweisungs-API sowie
neue Bearbeitung über `/speisekarten` auch für Administratoren. Die Konfiguration
bleibt in den Einstellungen erreichbar. Vorhandene Karten, Vorgaben und
Benutzerzuweisungen bleiben gespeichert; bereits geplante Slides werden weiterhin
abgespielt. Nach Wiederaktivierung sind dieselben Daten und Rechte wieder nutzbar.
Geöffnete Küchen-Editoren melden die Sperre spätestens bei der nächsten Anfrage;
offene lokale Texte werden nicht automatisch gespeichert.

In WordPress erscheint bei aktiviertem Modul und vorhandenem Speisekarten-Recht
zusätzlich der native Untermenüpunkt **Kiosky → Speisekarten**. Die gemeinsamen
Oberflächen verwenden dieselben Einstellungen in allen drei Plattformen.

## Tägliche Bearbeitung

Nach der Anmeldung sieht die Küche ihre zugewiesenen Karten. Änderbar sind
Titel, Untertitel, Fußzeile sowie Namen, Beschreibungen und Preise der Speisen.
Speisen lassen sich hinzufügen, entfernen und verschieben. Eine Vorschau zeigt
den Text und seine Platzierung; die endgültige Darstellung erfolgt im Player.
Bis zu **acht Speisen je Slide** halten die Vorlagen lesbar. Umfangreiche Karten
auf mehrere Slides verteilen und in einem Kanal zusammenstellen. Preise werden
als eingegebener Text angezeigt, beispielsweise `12,50 €`; es gibt keine
Preisberechnung oder automatische Allergenauszeichnung.

**Änderungen speichern** erstellt eine neue Slide-Version. Layout, Format,
Gültigkeitszeitraum, Status, Tags, Standort- und Kanalzuordnung bleiben erhalten.
Aktive Player übernehmen Änderungen bei ihrer nächsten regulären Synchronisierung
(in der Regel innerhalb von 30 Sekunden; ein offline laufender Player erst nach
Wiederherstellung der Verbindung). Entwürfe werden durch die Küche nicht
veröffentlicht.

Bei parallelen Änderungen meldet das Speichern einen Konflikt und behält die
lokalen Eingaben. Diese vor **Neu laden** kopieren, mit dem aktuellen Stand
vergleichen und erneut speichern. Seitenwechsel und Verwerfen fragen bei
ungespeicherten Änderungen nach.

## Anmeldung und Schutz

Standalone verwendet die bestehende Kiosky-Anmeldung. Ist Zugangswerk exklusiv
aktiviert, gilt dies für den kontogebundenen Zugang unter `/speisekarten`; lokale
Benutzerpasswörter und API-Schlüssel eröffnen keinen Verwaltungszugang. Die unten
beschriebene ausdrücklich freigegebene Passwortansicht ist seit 3.7.0 eine separate,
auf ausgewählte Karteninhalte beschränkte Ausnahme. Ein Login-Link führt zur bestehenden
Anmeldung und danach zurück zum Editor. Ein erforderlicher Passwortwechsel muss
vorher abgeschlossen werden.

WordPress leitet unangemeldete Benutzer zur nativen WordPress-Anmeldung.
TYPO3 leitet `/speisekarten` zum geschützten Kiosky-Backendmodul mit dem
Speisekarten-Editor. Dort gelten Backend-Anmeldung, Modulfreigabe und der
vorhandene Schutz der AJAX-Routen. Eine abgelaufene CMS-Sitzung gegebenenfalls
neu anmelden und den Editor erneut öffnen.

Die API prüft das eigene Speisekarten-Recht und die Kartenzuweisung für jede
Anfrage. Sie akzeptiert beim Speichern ausschließlich Inhaltsfelder und die
aktuelle Versionsnummer. Sie verweigert fremde oder gelöschte Karten,
Layoutänderungen, Veröffentlichungsfelder und veraltete Versionen. Texte werden
als Text dargestellt. Maschinenzugänge und öffentliche Player erhalten keinen
Zugang zum Editor.

## Layout, Export und Wiederherstellung

Die Vorlagen erzeugen normale Textelemente mit stabilen `menuField`-Zuordnungen.
Administratoren können diese im Slide-Editor positionieren und gestalten.
**Alle 27 zugeordneten Textfelder erhalten**, auch die leeren Plätze für spätere
Speisen. Fehlende Felder werden beim Inhalts-Speichern als unvollständige Vorlage
abgewiesen. Zusätzliche Designelemente bleiben erhalten. Der Inhaltseditor zeigt
als einfache Vorschau Texte; zusätzliche Bilder und komplexe Designs im normalen
Slide-Editor beziehungsweise Player prüfen. Die Vorlagen werden beim Anlegen ausgewählt; nachträgliche Gestaltung erfolgt
im normalen Slide-Editor. Seit 3.7.0 können eigene Vorlagen gespeichert werden.

Vor Änderungen Datenbank beziehungsweise CMS-Kiosky-Zustand sichern. Standalone
legt mit Migration `036_menu_access.sql` ausschließlich eine neue Tabelle für
Recht und Kartenzuweisungen an; CMS-Ausgaben ergänzen ihren bestehenden Zustand
um `menuAccess`. Seit 3.6.0 liegen die Modulvorgaben in Standalone unter dem
Integrationseinstellungs-Schlüssel `menu_settings`, im CMS-Zustand unter
`menuSettings`; der gemeinsame Modulschalter heißt `menus`. Es ist dafür keine
zusätzliche Datenbankmigration nötig. Bestehende Slides werden nicht konvertiert. Inhaltsänderungen
lassen sich über die vorhandene Slide-Versionsverwaltung zurücksetzen.

Beim Export eines Slides bleiben die Kartendaten und das Layout enthalten.
Benutzerrechte und Zuweisungen werden nicht in Inhalts-Pakete übertragen und
müssen auf der Zielinstallation neu vergeben werden. Vor einem Software-Rollback
Schreibzugriffe stoppen und die Datenbank-/CMS-Sicherung gemeinsam mit der
vorherigen Softwareversion wiederherstellen; neue Migrationen nicht manuell aus
einer laufenden Datenbank entfernen.


## Eigene Vorlagen ab 3.7.0

Unter **Einstellungen → Speisekarten → Karten und Berechtigungen →
Speisekarten-Vorlagen gestalten** Name, Ausgangsvorlage und Format auswählen.
**Vorlage anlegen und gestalten** öffnet den vorhandenen Slide-Editor. Dort Farben,
Schrift, Bilder, Hintergründe und Positionen bearbeiten und **Vorlage aktualisieren**
wählen. Alle 27 `menuField`-Textfelder müssen erhalten bleiben; unvollständige
Vorlagen werden beim Speichern abgewiesen. Der Editor zeigt Beispieldaten erst
beim Anlegen einer Karte; leere gebundene Textfelder nicht löschen.

Eigene Vorlagen stehen bei der Kartenanlage und als Modulstandard zur Auswahl.
Das Format wird bei der Auswahl übernommen. Vorlagenänderungen betreffen neue
Karten. Bereits vorhandene Karten behalten ihr bisheriges Layout und ihre Inhalte.
Archivieren entfernt die Vorlage aus der Auswahl; bestehende Slides bleiben
unverändert. Wenn die Standardvorlage archiviert wurde, eine andere Vorgabe wählen.

## Passwort-Link ohne Benutzerkonto ab 3.7.0

1. **Einstellungen → Speisekarten → Inhaltsbearbeitung mit Freigabepasswort** öffnen.
2. Die ausdrücklich freizugebenden Karten auswählen, ein eigenes Passwort mit
   **12 bis 256 Zeichen** setzen und den Inhaltszugang aktivieren.
3. Speichern. Den angezeigten Link über **Link kopieren** an die Küche geben;
   das Passwort getrennt mitteilen. Das Passwort steht niemals im Link.
4. Die Küche öffnet `/speisekarten?freigabe=…`, gibt nur dieses Passwort ein
   und sieht ausschließlich die freigegebenen Karten. Kein Kiosky-/CMS-Konto
   erforderlich; es wird auch kein Benutzerkonto angelegt.

Diese Freigabe funktioniert auf ausdrücklichen Betreiberwunsch auch bei exklusivem
Zugangswerk in Standalone. Sie erlaubt ausschließlich Text-/Preisänderungen bzw.
den Ersatz einer PDF-Seite an den ausgewählten Karten. Sie erlaubt weder neue
Slides noch Vorlagen, Veröffentlichungen, Kanäle, Einstellungen, Benutzerrechte
oder Verwaltungszugang. Standortbeschränkte Standalone-Administratoren können
nur ihre verfügbaren Karten freigeben. Normale Benutzeranmeldung und CMS-Rechte
bleiben getrennt und unverändert.

Das Passwort wird serverseitig gesalzen gehasht gespeichert; es ist später nicht
wieder auslesbar. Die API gibt nur `hasPassword` zurück. Die Freigabesitzung gilt
**acht Stunden**; ihr Token liegt im `sessionStorage` des Browser-Tabs und wird nur
an die gesonderte Inhalts-API gesendet. **Zugang beenden** löscht diesen lokalen
Token. Nach fünf falschen Versuchen wird die anfragende IP für den Rest des
fünfminütigen Zeitfensters gesperrt. Hinter Proxys muss die Client-IP-Verarbeitung
zur Installation passen; Standalone verwendet bewusst die direkte Socket-IP und
vertraut beliebigen Forwarded-Headern nicht.

Jedes Speichern der Freigabeeinstellungen beendet alle bisherigen Freigabesitzungen.
Ein leeres Passwortfeld beim Speichern behält den vorhandenen Passwort-Hash bei.
**Neuen Link erzeugen** wird mit dem nächsten Speichern wirksam und ersetzt auch
die Freigabeadresse. Ausschalten der Freigabe oder des Moduls sperrt weitere
Anfragen sofort; Ausschalten des Moduls widerruft bestehende Freigabesitzungen.
Archivierte oder gelöschte Karten sind nicht mehr erreichbar. Ein geteiltes
Passwort identifiziert die Küche als gemeinsamen Freigabezugang, nicht als einzelne
Person. HTTPS verwenden und das Passwort bei Personalwechsel erneuern.

Technisch: Standalone `integration_settings.menu_share`, CMS `menuShare`;
Fehlversuchszähler `menu_share_attempts` bzw. `menuShareAttempts`. Hash, Salz und
zufälliger Signaturschlüssel bleiben serverseitig. Diese Geheimnisse und die
Freigaben werden nicht über die übertragbaren Inhalts-/Systempakete exportiert;
auf einer Zielinstallation neue Freigaben einrichten. Keine neue DB-Migration.

## PDF-Import und Austausch ab 3.7.0

**Einstellungen → Speisekarten → Karten und Berechtigungen → PDF-Speisekarten
importieren**: PDF und internen Namen wählen, dann **PDF-Seiten als Slides importieren**.
Maximal **20 MiB und 20 Seiten** pro Datei. Der Browser rendert jede Seite mit lokal
mitgeliefertem PDF.js; keine Übertragung an externe Konverter und kein CDN.
Passwortgeschützte PDFs vorher entsperren. Beschädigte Dateien werden abgewiesen.

Jede Seite wird ein eigener Entwurf mit Tag **Speisekarte / PDF**. Das Seitenlayout
bleibt erhalten und wird vollständig in einen weißen 1920×1080- oder
1080×1920-Rahmen eingepasst. Dies ist ein Rasterbild, **keine OCR**, keine editierbare
Speisenliste und keine Übernahme eingebetteter Links, Skripte, Anhänge oder Formulare.
Die ursprüngliche PDF wird nicht gespeichert. Wie bei Textkarten Status und
Kanalzuordnung anschließend in der Verwaltung festlegen.

Im Inhaltseditor kann eine freigegebene PDF-Karte über **Neue PDF-Datei**, **Seite**
und **PDF-Seite ersetzen** aktualisiert werden. Das bestehende Slide-Format und
Layout bleiben erhalten. Auch der Passwortzugang darf ausschließlich zugewiesene
PDF-Karten ersetzen. Versionskonflikte werden wie bei Textkarten abgewiesen.

Der mehrseitige Import erfolgt nacheinander. Bei einem Fehler bleiben bereits
importierte Seiten als Entwürfe erhalten; die Meldung nennt deren Anzahl.
Nicht benötigte Entwürfe in der Verwaltung entfernen und verbleibende Seiten
gegebenenfalls einzeln erneut importieren. PDF-Seitenbilder liegen im Slide-Dokument
und sind in Inhalts-/Versions-Exporten enthalten; umfangreiche PDFs vergrößern
Datenbank, Exporte und Backups entsprechend. Server-Upload-/Requestlimits müssen
für einen Seitenaustausch mindestens 3 MB JSON zulassen. Der Server akzeptiert
nur JPEG-Seitenbilder im festen Slide-Format bis 2,8 MB Data-URL-Größe.
