# Speisekarten-Slides

Ab Kiosky 3.5.0 in Standalone, WordPress und TYPO3; seit 3.6.0 als eigenes
**Speisekarten-Modul** in Navigation und Einstellungen. Einstieg:
`https://deine-kiosky-adresse/speisekarten` (bei Unterverzeichnissen entsprechend
unterhalb der Installation). Der Arbeitsbereich **Speisekarten** öffnet den Inhaltseditor direkt in Kiosky.
Die eigenständige Küchenadresse `/speisekarten` bleibt ebenfalls erhalten.

## Einrichtung durch die Administration

1. Mit dem vorhandenen Administrator-Konto **Einstellungen → Module** öffnen.
   Dort den einzigen Schalter **Speisekarten** in der Modulliste ein- oder
   ausschalten und die Moduleinstellungen speichern. Hier lässt sich auch die
   Reihenfolge in der Kiosky-Navigation ändern.
2. Im **Hauptmenü → Speisekarten → Vorgaben für neue Speisekarten**
   Standardvorlage, Format, Titel, Untertitel und Fußzeile festlegen und speichern.
   Diese Vorgaben gelten nur für neue Karten; vorhandene Karten bleiben erhalten.
3. Unter **Hauptmenü → Speisekarten → Neue Speisekarte anlegen** internen Namen,
   Vorlage und Format wählen. Enthalten sind **Blau · Klar**, **Hell · Bistro**
   und **Dunkel · Abendkarte**. Neben Hoch- und Querformat steht **Responsive**
   zur Verfügung; dabei folgt das Layout der tatsächlichen Playerauflösung.
   Auch PDF-Import, eigene Vorlagen und Kartenbearbeitung liegen im Hauptmenü.
4. Neue Karten werden als normale Slides mit dem Tag **Speisekarte** und Status
   **Entwurf** angelegt. Unter **Kartenverwaltung** Namen, Vorlage, Layout und
   Ausrichtung ändern oder die Karte in den Papierkorb verschieben. In der
   normalen Slide-Verwaltung den Veröffentlichungsstatus einstellen, einem Kanal
   hinzufügen und wie gewohnt zeitlich planen.
5. Unter **Hauptmenü → Speisekarten → Speisekarten-Rechte und Zuweisung** ein
   vorhandenes Benutzerkonto auswählen, **Speisekarten bearbeiten erlauben**
   aktivieren und die zulässigen Karten markieren. **Rechte speichern** übernimmt
   die Zuweisung sofort.
6. Externe Zugänge und zusätzlich PDF-Weblink-Abonnements werden unter
   **Einstellungen → Module → Speisekarten: externe Links konfigurieren** verwaltet.

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

Unter **Hauptmenü → Speisekarten →
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

1. **Einstellungen → Module → Speisekarten: externe Links konfigurieren → Inhaltsbearbeitung mit Freigabepasswort** öffnen.
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

**Hauptmenü → Speisekarten → PDF-Speisekarten
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

## PDF-Weblinks abonnieren (ab 3.8.0)

Unter **Hauptmenü → Speisekarten → PDF-Weblinks abonnieren** steht
zusätzlich zum bisherigen lokalen PDF-Import ein wiederkehrender Serverimport zur
Verfügung. Nur angemeldete Administratoren verwalten Abonnements. Küchenbenutzer
und Passwort-Freigaben erhalten dadurch keine Verwaltungsrechte.

### Server vorbereiten

Alle drei Ausgaben benötigen die gepflegten Poppler-Werkzeuge **pdfinfo** und
**pdftoppm** im `PATH` des tatsächlich ausführenden Web-/Cron-Prozesses. Unter
Debian/Ubuntu installiert die Serveradministration beispielsweise `poppler-utils`.
In WordPress/TYPO3 zusätzlich **PHP cURL** und die freigegebene Funktion
**proc_open** bereitstellen. Schreibzugriff auf das temporäre Systemverzeichnis
und ausreichend Speicherplatz für gerenderte Seiten und Slide-Versionen sind
nötig. Bei Hosting ohne Prozessausführung ist dieses Feature nicht verfügbar;
der bestehende lokale PDF-Import funktioniert weiterhin ohne Poppler.
Die Oberfläche prüft diese Voraussetzungen und zeigt fehlende Komponenten an.
Poppler über die Betriebssystemupdates aktuell halten. PHP-/Webserver-Proxies
müssen längere Importanfragen zulassen (bis zu rund zwei Minuten je PDF).

Der Abgleich läuft ohne geöffneten Editor:

- **Standalone:** Der laufende Kiosky-Server prüft alle 30 Sekunden, welche
  Abonnements fällig sind, sowie einmal beim Start. Ein laufender Serverprozess
  ist erforderlich; während eines Stillstands gibt es keinen Abgleich. Eine
  Installation mit einem schreibenden Node-Prozess pro SQLite-Datenbank betreiben.
- **WordPress:** Der bestehende Kiosky-Cron-Hook `kiosky_scheduled_tasks` wird auf
  einen Minutentakt umgestellt. Das bisherige 15-Minuten-Ereignis wird automatisch
  ersetzt; CrewBrain/easyjob behalten ihre eigenen Fälligkeitsregeln.
  **WP-Cron allein wird durch Seitenaufrufe angestoßen.** Für einen verlässlichen
  Hintergrundbetrieb unabhängig von Zugriffen muss die Serveradministration
  System-Cron oder den Hosting-Aufgabenplaner jede Minute einrichten, zum Beispiel:
  `wp cron event run --due-now --path=/absoluter/pfad/zu/wordpress`.
  Bei externem Cron kann WordPress' interner Cron-Start anschließend nach dessen
  Betriebsanleitung deaktiviert werden; Kiosky verändert diese Konfiguration nicht.
- **TYPO3:** Im Scheduler **Kiosky: Speisekarten-PDF-Weblinks** jede Minute ausführen
  und sicherstellen, dass der TYPO3-Scheduler selbst durch System-Cron gestartet
  wird. Alternativ jede Minute im Projektverzeichnis
  `vendor/bin/typo3 kiosky:menus:sync` ausführen. Auch der vorhandene allgemeine
  Kiosky-Importlauf berücksichtigt fällige PDF-Abonnements. Die neue Aufgabe und
  der neue CLI-Befehl starten gezielt nur den Speisekarten-Abgleich.

Das individuelle Intervall ist eine Fälligkeitsgrenze, kein Echtzeit-Timer:
Ausführung erfolgt beim nächsten Scheduler-Takt, gegebenenfalls verzögert durch
einen anderen Import, Download, Serverstillstand oder fehlenden System-Cron.

### Anlegen und verwenden

1. **Neues Weblink-Abonnement** aufklappen und den öffentlich erreichbaren
   **HTTPS-PDF-Weblink** eintragen. Keine Anmeldung, URL-Zugangsdaten oder geheimen
   Downloadlinks verwenden: die Adresse wird für die Verwaltung gespeichert.
2. **Kanalname**, **Tag**, **Slide-Namensvorgabe** und Intervall setzen.
   Unter **Einzelne Seitennamen vorgeben** sind optional bereits vor dem Import individuelle Namen möglich (eine Zeile je PDF-Seite). Ohne Liste gelten Namensvorgabe und Seitennummer.
   Standard sind **15 Minuten**, zulässig sind ganze Minuten von **1 bis 10080**
   (sieben Tage). Der erste Import erfolgt sofort.
3. **Weblink importieren und abonnieren** starten. Bis zu **20 MiB**, **20 Seiten**
   je PDF und **20 unabhängige Abonnements** je Installation sind erlaubt.
   Verschlüsselte, defekte oder übergroße PDFs werden abgewiesen. Gerenderte
   Einzelbilder sind auf 2 MB und zusammen auf 20 MiB begrenzt.
4. Jede Seite wird als Speisekarten-Slide angelegt, in PDF-Reihenfolge in einen
   eigenen Kanal aufgenommen und mit **Speisekarte**, **PDF** und dem gewählten
   Tag versehen. Die Ausgangsnamen lauten beispielsweise `Mittagskarte · 1`.
   Die Seiten bleiben als Bild erhalten, mit proportionaler Darstellung und
   weißem Hintergrund; keine OCR oder Umwandlung in bearbeitbare Speisenlisten.
5. Den automatisch angelegten **Kanalentwurf** in der Kanalverwaltung prüfen,
   veröffentlichen und Displays zuweisen. Die enthaltenen Slides sind bereits
   veröffentlicht; ein neues Abonnement weist sich keinem Display selbst zu.
   Ausgangslaufzeit ist zwölf Sekunden je Seite, danach im Kanal individuell
   änderbar. Bestehende Laufzeiten und Übergänge bleiben bei Folgeimporten erhalten.

Mehrere Weblinks können parallel abonniert werden, beispielsweise Mittagskarte,
Getränkekarte und Tagesangebote. Jeder erhält eigene Slides, einen eigenen Kanal,
Status und Zeitplan. Der Server verarbeitet fällige Quellen nacheinander.

### Abgleich, Namen und Kanalinhalt

Bei jeder Prüfung wird das aktuelle PDF vollständig mit Größen-/Zeitgrenze
heruntergeladen und dessen **SHA-256** mit der zuletzt erfolgreich verarbeiteten
Datei verglichen. Die Prüfung vertraut nicht ausschließlich auf `ETag`,
`Last-Modified` oder einen unveränderten Dateinamen. Unveränderte Bytes bewirken
keine neue Slide-Version, keine Neudarstellung und keinen Rotationsneustart.
Nur Prüfzeitpunkt, nächster Termin und Abonnementrevision ändern sich.

Ein geändertes PDF wird vollständig gerendert, bevor Slides/Kanal übernommen
werden. Bestehende Seiten werden **nach ihrer Position** zugeordnet: Seite 1
behält ihre Slide-ID, Seite 2 ihre eigene usw. Das erhält Displayzuweisungen,
Rechte, individuelle Slide-Namen, Tags, Layout, Veröffentlichungsstatus und
Versionen. Eine Verschiebung innerhalb des PDFs ist keine semantische Erkennung
von Gerichten: individuell benannte Seiten beziehen sich weiterhin auf dieselbe
Seitennummer. Neue Seiten erhalten neue Slides. Weggefallene Seiten verschwinden
aus dem abonnierten Kanal; ihre bisherigen Slides bleiben als Entwürfe erhalten,
damit andere Verweise und Versionshistorien nicht gelöscht werden.

**Der abonnierte Kanal wird inhaltlich durch das PDF verwaltet.** Seine Reihenfolge
entspricht nach einer PDF-Änderung wieder dem PDF; dort manuell ergänzte fremde
Slides werden aus dieser Playlist entfernt, aber nicht gelöscht. Für Mischungen
von PDF-Seiten und anderen Inhalten einen zusätzlichen eigenen Kanal verwenden.
Kanal-ID, Status, Standortzuordnung und Einstellungen bleiben bestehen.

Jedes Abonnement lässt sich aufklappen:

- **Kanalname** ändert bei einer ausdrücklichen Änderung den bestehenden Kanalnamen.
- **Tag** ersetzt bei einer Änderung nur den zuvor verwalteten Tag der aktuellen
  Slides und des Kanals. Weitere manuell vergebene Tags bleiben erhalten.
- Unter **Slide-Namen bearbeiten** die Namen einzelner Seiten vorgeben. Diese sind
  auch weiterhin in der Slide-Bibliothek änderbar und werden nicht bei jedem
  Abgleich aus der Namensvorgabe zurückgesetzt.
- **Namensvorgabe** gilt nachträglich nur für neu hinzukommende Seiten. Beim
  Erstimport ist sie schon vor dem Anlegen editierbar.
- Intervall ändern, automatischen Abgleich pausieren oder **Jetzt abgleichen**
  verwenden. Manuelle Prüfung funktioniert auch bei pausiertem Abonnement.
- Die Quelladresse bleibt pro Abonnement fest. Für eine andere PDF-URL ein neues
  Abonnement anlegen und den bisherigen Kanal nach eigener Prüfung umstellen.
- **Abonnement beenden** stoppt weitere Prüfungen; vorhandener Kanal und Slides
  bleiben unverändert erhalten. Dadurch ist auch ein Wechsel zu manueller Pflege
  möglich.

Die Statusanzeige enthält letzte Prüfung, letzte übernommene PDF-Änderung, nächsten
Termin und Fehler. Veraltete gespeicherte Formulare werden mit Konfliktmeldung
abgewiesen, statt Änderungen eines anderen Administrators zu überschreiben.
Der aktualisierte Kanal wird von aktiven Web-Playern automatisch ohne vollständiges
Neuladen übernommen; siehe [Player-Aktualisierung](player-refresh.md).

### Fehler, Zugriff und Wiederherstellung

Bei Downloadfehlern, Timeout, ungültigem PDF oder einer fehlgeschlagenen
Seitenverarbeitung bleiben die zuletzt vollständig gespeicherten Slides, der
Kanal und der erfolgreiche Fingerabdruck erhalten. Es gibt keinen halb fertigen
Kanal. Der nächste Versuch erfolgt nach dem konfigurierten Intervall; nach einer
Korrektur kann man sofort manuell prüfen. Beim fehlgeschlagenen Erstimport wird
kein unvollständiges Abonnement angelegt. Bei absichtlich gelöschten/archivierten
Slides oder Kanälen erscheint ein Fehler; aus dem Papierkorb wiederherstellen
und erneut prüfen, statt unbemerkt gelöschte Inhalte neu anzulegen.

Die Downloader akzeptieren ausschließlich öffentliche HTTPS-Ziele auf Port 443
mit TLS-Prüfung. Lokale/private/reservierte Netze, Zugangsdaten in URLs und andere
Protokolle werden gesperrt. DNS-Ziele und jede Weiterleitung (höchstens drei)
werden geprüft; Verbindungen werden an geprüfte Adressen gebunden. Ein vorhandener
vertrauenswürdiger Ausgangsproxy muss ebenfalls ausschließlich öffentliche Ziele
zulassen. PDF-Links zu internen Netzen und authentifizierungspflichtige Downloads
sind bewusst nicht unterstützt. Download-URLs werden nicht in Player-Slides
hinterlegt; diese verwenden die serverseitig gespeicherten Seitenbilder.
Temporäre Dateien werden nach der Verarbeitung entfernt. Der PDF-Inhalt wird
nicht als Script ausgeführt, und Benutzertexte werden nicht als HTML interpretiert.

**Modul ausschalten** pausiert auch die automatischen Weblink-Prüfungen, ohne
bestehende Slides oder Kanäle zu löschen. Nach dem Einschalten werden überfällige
Abonnements beim nächsten Scheduler-Lauf nachgeholt. Eine einzelne Quelle über
**Automatischen Abgleich aktivieren** pausieren oder per **Abonnement beenden**
ablösen. Vor einem Versions-Rollback Abonnements pausieren und eine konsistente
Datenbanksicherung erstellen. Die additive Speicherung braucht keine neue
Datenbankmigration; ältere Versionen verwalten die neuen Abonnementdaten nicht.

Die Kartenwahl, einzelne Speiseneinträge, Vorschau, Vorlagen, Rechte und
Abonnementdetails sind einklappbar. Auf kleinen Bildschirmen wechseln Formularzeilen
und Aktionen in eine Spalte; Eingaben und Schaltflächen bleiben per Touch und
Tastatur bedienbar. Die bisherigen Textkarten, Vorlagenbearbeitung, lokalen
PDF-Uploads und Passwort-Freigaben bleiben verfügbar.

## Kartenverwaltung und neue Navigation (3.8.1)

**Einstellungen → Module** enthält den einzigen Ein-/Ausschalter für das Modul
in der Liste der Arbeitsbereiche. Ausschalten blendet **Speisekarten** aus dem
Hauptmenü aus und sperrt Modul/externen Inhaltseditor; Daten und bereits geplante
Ausspielungen bleiben erhalten. Unter **Speisekarten: externe Links konfigurieren**
werden Passwort und Freigabelink des externen Inhaltseditors eingerichtet.
PDF-Weblink-Abonnements sind seit 3.8.2 wieder direkt im Hauptmenü unter
**Speisekarten → PDF-Weblinks abonnieren** erreichbar; der zusätzliche Zugang
über die Moduleinstellungen bleibt erhalten. Der bisherige eigene Speisekarten-Reiter in
den Einstellungen entfällt.

Im **Hauptmenü → Speisekarten** befinden sich alle anderen Funktionen: Vorgaben
für neue Karten, Karten aus Vorlagen anlegen, eigene Vorlagen gestalten, lokale
PDFs importieren, Karten bearbeiten und Benutzerrechte/Kartenzuweisungen verwalten.
Die früheren Verweise auf eine Kartenverwaltung in den Einstellungen sind damit
ersetzt. Die bekannte Inhaltsbearbeitung für zugewiesene Küchenbenutzer und der
separate passwortgeschützte Zugang bleiben bestehen.

Administratoren können an jeder bestehenden Karte **Name, Vorlage, Ausrichtung
und Layout** aufklappen. Umbenennen und Formatwechsel erhalten Slide-ID, Inhalte,
Tags, Status und Zuweisungen. Beim Wechsel einer Textkarten-Vorlage bleiben Titel,
Untertitel, sämtliche Speisen/Preise/Beschreibungen und Fußzeile erhalten. Das alte
Layout wird nach Bestätigung durch die gewählte Vorlage ersetzt. **Im visuellen
Editor gestalten** öffnet den vorhandenen Editor für Hintergründe, Farben,
Schriften und Elementpositionen. Alle 27 Speisekartenfelder beziehungsweise das
PDF-Bildfeld müssen erhalten bleiben; ein unvollständiges Layout wird abgewiesen.
Die eigentlichen Speiseninhalte werden weiterhin im Inhaltsformular geändert.
Im Layout-Modus werden nicht zugehörige Verwaltungsfelder ausgeblendet.

**Karte löschen** verschiebt sie in den Papierkorb. Dort lässt sie sich wie andere
Slides wiederherstellen. Eine Karte aus einem noch bestehenden PDF-Abonnement
zuerst durch **Abonnement beenden** lösen, damit der automatische Abgleich nicht
mit dem Löschen konkurriert. Umbenennungen und Layoutänderungen sind dagegen auch
bei abonnierten Karten möglich und bleiben bei Folgeabgleichen erhalten. Bei
PDF-Karten bleibt die PDF-Seite der Inhalt; eine Textvorlage ersetzt sie nicht.
PDF-Inhalte lassen sich weiterhin über eine andere PDF-Seite austauschen.

**Responsive · Displayauflösung folgen** ist bei neuen und vorhandenen Karten
sowie als Vorgabe verfügbar. Der Player verwendet die tatsächliche Fenster-/
Displayauflösung, passt sich bei deren Änderung ohne Seitennavigation an und
wählt Hoch- oder Querformat. Prozentuale Positionen erhalten die gemeinsamen
Speisen-/Preiszeilen; gebundene Texte nutzen automatische Größenanpassung.
PDF-Seiten werden proportional dargestellt. Ein PDF wird dabei nicht in einzelne
Gerichte umgewandelt. Feste Hoch-/Querformate bleiben weiterhin wählbar.
Die Responsive-Vorschau lässt sich zwischen beiden Ausrichtungen umschalten.
Küchenrechte und Passwort-Links erlauben weiterhin ausschließlich Inhaltsänderungen;
Name, Layout, Vorlage, Ausrichtung und Löschen bleiben administrativ geschützt.

Die Netzprüfung öffentlicher PDF-Adressen unterscheidet nun öffentliche
IPv6-Hostingnetze (beispielsweise `2001:8d8:…`) von reservierten Teilnetzen.
Private, lokale, Dokumentations- und Übergangsnetze bleiben gesperrt; die Prüfung
wird bei jeder Weiterleitung und DNS-Auflösung weiterhin angewendet.


## PDF-Seiten im responsiven Player (ab 3.8.3)

Der Player und die Kanalvorschau passen jede PDF-Seite proportional und zentriert
in ihren Bildrahmen ein. Auch schmale, quadratische oder besonders breite Fenster
zeigen die gesamte Seite. Freie Flächen sind dabei beabsichtigt; die Seite wird
weder beschnitten noch verzerrt. Neue PDF-Weblink-Slides verwenden einen Rahmen
über die gesamte Slide. Vorhandene und selbst gestaltete Rahmen bleiben erhalten.
Diese Darstellung gilt für bestehende PDF-Slides sofort nach dem Player-Update,
ohne ein neues Abonnement oder eine Änderung der PDF-Datei zu verlangen.

Bei weißem Standardhintergrund ermittelt der Browser aus dem lokal importierten
Seitenbild eine passende Randfarbe. Gleichmäßige Seiten übernehmen die dominante
Randfarbe, beispielsweise Creme oder Dunkelblau. Bei sehr unterschiedlichen
Randfarben wird ein gemittelter Farbton verwendet. Eine exakte semantische
Farberkennung ist bei Fotos oder mehrfarbigen Randgestaltungen nicht möglich.
Bewusst gewählte nichtweiße Hintergrundfarben, Bilder und Verläufe bleiben
unverändert. Die Speisekarten-Vorschau verwendet dieselbe Ermittlung.
Es werden ausschließlich die vorhandenen lokalen PDF-Seitenbilder ausgewertet;
keine Übertragung an einen externen Bilddienst. Ist die Auswertung nicht möglich,
bleibt der vorhandene Hintergrund erhalten.
