# Kundeninhalte und Freigaben

Die Funktion steht unter **Slides & Kanäle → Kundeninhalte und Freigaben** in
Standalone, WordPress und TYPO3 zur Verfügung. Administratoren und Redakteure
verwalten die Links und Freigaben. In TYPO3 sind zusätzlich native Inhalts-,
Veranstaltungs- und Displaysteuerungsrechte erforderlich. Mediengestalter,
Veranstaltungsredakteure, reine Displayoperatoren und Betrachter erhalten damit
keine zusätzlichen Freigaberechte. Die Module Inhalte/Kanäle, Veranstaltungen,
Displays, Zeitplanung und Medien müssen freigeschaltet und aktiviert sein.

## Mitarbeiter: Link erstellen

1. Kundenname und optional einen eigenen Tag angeben. Das System ergänzt immer
   „Kunde:“ vor dem Tag; ein Kundentag aktiviert somit keine automatische Werbung.
2. Eine Veranstaltung und die erlaubten Displays auswählen. Suchfelder helfen bei
   großen Listen. Die Displayauswahl scrollt unabhängig vom übrigen Formular.
3. Den frühesten Beginn, das späteste Ende und die Gültigkeit des Kundenlinks
   festlegen. Die Eingaben gelten für **Europe/Berlin**, unabhängig von der
   Zeitzone des Kundenrechners. Zeiten in der ausgelassenen Stunde bei der
   Sommerzeitumstellung werden abgewiesen.
4. Den erzeugten Link kopieren und vertraulich weitergeben. Der vollständige Link
   wird nur bei seiner Erstellung angezeigt. Verlorene Links widerrufen und neu
   erstellen; das System speichert keinen wieder auslesbaren Zugangsschlüssel.

Ein Link ersetzt kein Benutzerkonto und erlaubt keine direkte Veröffentlichung.
Der Mitarbeiter gibt eine begrenzte Ausspielzeitspanne von höchstens einem Jahr
vor. Höchstens 200 gleichzeitig laufende Kundenfreigaben sind vorgesehen.

## Kunde: Dateien und Kanalvorschläge

Der Kunde benötigt keinen Kiosky-/CMS-Login. Er öffnet den Link, lädt Bilder oder
Videos hoch, wählt daraus Slides für einen Kanal und bestimmt Reihenfolge,
Anzeigedauer, Displays sowie Beginn und Ende innerhalb der Mitarbeiterfreigabe.
Die Reihenfolge lässt sich mit Pfeiltasten ändern. Eine Anzeigedauer von 3 bis
600 Sekunden ist möglich; bei Videos wird die erkennbare Videodauer vorbelegt.

Erlaubt sind PNG, JPEG, GIF, WebP, MP4, MOV und WebM. Bilder sind auf 16 Megapixel
und 8192 Pixel je Seite begrenzt. Pro Link sind 20 Dateien, insgesamt 100 MB und
20 Kanalvorschläge möglich. Einzeldateien sind höchstens 20 MB groß; das Portal
zeigt bei CMS-Installationen gegebenenfalls eine kleinere Servergrenze an.
Unterschiedliche Video-Codecs werden nicht automatisch umgewandelt. Für möglichst
breite Player-Unterstützung MP4 mit H.264 verwenden und die Vorschau prüfen.

Jede Datei erscheint sofort als **Slide-Entwurf** mit „Kundenupload“, Kundentag und
Kennzeichnung **„Wartet auf Freigabe“**. Die eigentliche Datei liegt bis zur Freigabe
im privaten Zwischenspeicher. Es wird noch kein öffentliches CMS-Medium angelegt.
Ein eingereichter Kanal erscheint als Entwurf. Zeitzuweisungen entstehen erst
nach der Mitarbeiterfreigabe.

Kunden können noch offene Vorschläge bearbeiten. Bereits bearbeitete Vorschläge
lassen sich als neuer Vorschlag verwenden. Eine Änderung überschreibt keine
laufende, bereits freigegebene Ausspielung. Bilder und Videos können über die
Vorschau geprüft werden; bei freigegebenen Dateien wird nur das unveränderte eigene
Original bereitgestellt, sofern es in der Mediathek noch verfügbar ist.

## Mitarbeiter: prüfen, freigeben, ablehnen

Die Freigabeansicht zeigt Kunde, Veranstaltung, Kanal, Dateireihenfolge, Dauer,
Displays und Zeitraum. Dateien lassen sich direkt ansehen. Suche und Statusfilter
grenzen die Anzeige auf die letzten 50 passenden Einträge ein.

**Freigeben und Zeitzuweisungen anlegen** veröffentlicht die beteiligten Slides,
den Kanal und je eine gewöhnliche Zeitzuweisung pro ausgewähltem Display.
Nach Ende des Zeitfensters greift wieder die normale Ausspiellogik. Warnungen und
andere Prioritäten bleiben erhalten. Bereits laufende Zeitzuweisungen werden nicht
entfernt. Ein wiederholter Freigabeklick erzeugt keine doppelten Einträge.

Wurde der Vorschlag inzwischen vom Kunden geändert, wird die alte Freigabe
abgewiesen. Die Vorschläge neu laden und nochmals prüfen. Eine Ablehnung kann eine
Begründung enthalten. Auch nach Ende der Kunden-Bearbeitungsfrist kann ein
Mitarbeiter einen rechtzeitig eingereichten Vorschlag freigeben, solange die
Ausspielzeit noch gültig ist. Ein Beginn in der Vergangenheit wird ausdrücklich
angezeigt: Die Freigabe kann dann sofort auf dem Display wirksam werden.

**Link widerrufen und Entwürfe verwerfen** sperrt den Kundenzugang, verwirft offene
Vorschläge, archiviert ungeprüfte Entwürfe und entfernt deren private Dateikopien.
Bereits freigegebene Slides, Kanäle und Zeitzuweisungen bleiben erhalten und werden
weiterhin über die gewöhnliche Verwaltung bearbeitet.

## Sicherheit, Speicher und Betrieb

- Zufällige 256-Bit-Zugangsschlüssel stehen im URL-Fragment, nicht in HTTP-URLs oder
  Referrer-Headern. Gespeichert wird nur der SHA-256-Prüfwert. Die Dateivorschau
  prüft Link, Dateieigentum und bei freigegebenen Originalen zusätzlich Dateigröße
  und SHA-256; andere CMS-Dateien werden damit nicht zugänglich.
- Jeder Zugriff prüft Gültigkeit, Veranstaltung und aktive Displays. Eine geänderte
  Standortzuordnung macht den bisherigen Kundenlink ungültig. Standalone prüft die
  Standortrechte des Mitarbeiters auch bei Erstellung, Vorschau und Freigabe.
- Upload-Inhalt, Dateiformat, Mengen und Zeitfenster werden serverseitig geprüft.
  SVG, HTML, PDF, Skripte und beliebige externe URLs gehören nicht zum Kundenupload.
  Dateinamen werden bereinigt; eingebettete ausführbare Dateiendungen bleiben nicht
  im Dateinamen erhalten. Formatprüfung ersetzt keinen Virenscanner oder eine
  fachliche Inhaltsprüfung durch den Mitarbeiter.
- Anfragen sind begrenzt: 28 MB für Upload-JSON, 50 KB für Vorschläge. Node prüft
  Kundenzugang vor und nach dem Lesen. WordPress prüft deklarierte Größen vor seiner
  REST-Verarbeitung und den Zugang vor der JSON-Verarbeitung; TYPO3 liest begrenzt.
  PHP prüft zusätzlich Speicherreserven. Nach ungültigen Node-Anfragen wird eine
  Verbindung mit eventuell ungelesenem Body geschlossen.
- Die öffentliche Schnittstelle ist auf 120 Zugriffe je Minute und IP-Adresse
  begrenzt. CMS-Vorprüfungen können dabei zusätzlich zählen. Das ergänzt die
  vorgeschalteten Webserverlimits und ersetzt keinen DDoS-Schutz.
- Der private Zwischenspeicher ist auf insgesamt 250 MB begrenzt. Nach erfolgreicher
  Freigabe werden Dateien in die normale Medienverwaltung übernommen und die
  doppelten privaten Kopien entfernt. Metadatenlisten enthalten keine Binärdateien.
  Verwaiste, nicht mehr referenzierte Zwischendateien können nach 24 Stunden bei der
  nächsten Bereinigung entfernt werden. Höchstens 2000 ungeprüfte Uploads/Vorschläge
  sind gleichzeitig vorgesehen.
- WordPress/TYPO3 verwenden einen Unterordner des geschützten Schriftdateispeichers;
  Standalone verwendet `customer-uploads` neben der Datenbank. Dieses Verzeichnis
  in Backups aufnehmen und außerhalb eines direkt veröffentlichten Webroots halten.
  Bei großen Dateien sind passende Plesk-/PHP-Request- und Speichergrenzen nötig.
- Freigegebene Medien, Slides, Kanäle und Zeitzuweisungen gehören zum normalen
  Systemumzug. Kundenlinks und ungeprüfte Quarantänedateien bleiben dagegen an die
  Installation gebunden; hierfür werden keine Zugangsschlüssel oder Dateien
  automatisch auf eine andere Instanz übertragen.

## Smartphone und Tablet

Gemeinsame Formulare verwenden auf Touch-Geräten größere Bedienflächen und mindestens
16-Pixel-Eingabeschrift. Dialoge passen sich an den tatsächlich sichtbaren Bereich
und Tastaturänderungen an. Der WordPress-App-Frame hat keine feste 760-Pixel-
Mindesthöhe mehr. Kanalslides können zusätzlich zu Drag-and-drop per Pfeiltaste
verschoben werden. Desktop-Bedienung und Player-Vollbild bleiben erhalten.

## Prüfung

Die Regressionen prüfen private Entwürfe, Datei-/Displayfremdzugriff,
Zeitfenster, alte Freigabestände, Rollback bei fehlgeschlagener Mehrfachzuweisung,
Idempotenz, Linkwiderruf während einer Anfrage und CMS-Rollen. Gemeinsame Szenarien
laufen auch über native WordPress-/TYPO3-Transporte. Browsertests decken alle drei
UI-Transporte, Bild-/Videovorschau, Ausspielung auf zwei Displays, mobile Reihenfolge
und einen simulierten WordPress-Tastaturviewport ab. Das ersetzt keine Abnahme auf
jedem physischen Endgerät und mit jedem Video-Codec.
