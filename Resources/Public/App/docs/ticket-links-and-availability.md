# Mehrere Ticketanbieter und Restkarten

Ab 3.40.6 lassen sich in einer Veranstaltung über **＋ Weiteren Ticket-Link hinzufügen**
bis zu zehn Anbieter mit Name und URL pflegen. Der erste Eintrag ist der Hauptlink.
**Als Hauptlink** wechselt das Ziel bestehender Ticket-Platzhalter;
die weiteren Anbieter bleiben erhalten. Manuell gespeicherte Anbieterlisten und ihr
Hauptlink gelten als lokale Angaben; spätere Veranstaltungsimporte überschreiben sie nicht. Kopien, Vorlagen und vollständige Systemumzüge
übernehmen die Liste. Vor einem Umzug beide Installationen aktualisieren.

Der Website-Kalender zeigt die Anbieter als einzelne Ticket-Schaltflächen, auch in der
Detailansicht. Die Einstellung zum Ausblenden der Tickets entfernt alle Links aus dem
öffentlichen Kalenderdatensatz. Abgesagte Veranstaltungen zeigen dort weiterhin keine
Ticket-Schaltflächen. Bestehende Veranstaltungen mit nur einem Ticket-Link bleiben
nutzbar. Die neue optionale API-Eigenschaft lautet `ticketLinks: [{label,url}]`;
`ticketUrl` bleibt der kompatible Hauptlink. Ältere Clients können den Hauptlink ändern,
ohne die zusätzlichen Anbieter zu verlieren. Zulässig sind HTTP-/HTTPS-Links ohne
Zugangsdaten in der URL. Die Liste lädt keine fremden Ticketseiten im Hintergrund.

## Ticket-QR-Code mit Anbieterauswahl (ab 3.40.7)

Bei **einem Anbieter** öffnet der Ticket-QR-Code direkt den Ticketshop. Bei
**mehreren unterschiedlichen Anbietern** öffnet er eine kleine, mobile Auswahlseite
mit Veranstaltungstitel, Anbietername und Anbieter-Domain. Dies gilt auch für
Ticket-QR-Codes in Eventdaten, Event-Tabellen und Veranstaltungsübersichten.
Statische QR-Codes und gewöhnliche Ticket-Link-Platzhalter bleiben unverändert.

Die Auswahl wird beim Öffnen aus der aktuellen Veranstaltung geladen. Änderungen
an Anbietern gelten damit auch für einen zuvor erzeugten Auswahl-QR-Code. Ein
alter Auswahl-Link mit nur noch einem Anbieter bietet diesen weiterhin an.
Es werden weder Player-Schlüssel noch CMS-Nonces oder Anmeldedaten in den QR-Code
aufgenommen. Der anonyme Endpunkt `GET /api/public/tickets/{id}` liefert nur
`data: {title, links: [{label, url}]}`. Er lädt keine Ticketshops im Hintergrund.

Die Veranstaltung muss veröffentlicht, noch nicht beendet und das
Veranstaltungsmodul freigegeben sein. Entwürfe, abgesagte, archivierte und gelöschte
Veranstaltungen geben keine Links frei. Die globale Kalender-Einstellung
**Tickets anzeigen** gilt auch für diese öffentliche Auswahlseite. Ein öffentlicher
Standalone-Kalender muss dafür nicht zusätzlich aktiviert werden.
Bei fehlender Verbindung wird eine verständliche Meldung mit „Erneut versuchen“
gezeigt. Die Serveradresse muss vom Smartphone aus erreichbar sein.

## Automatische Restkarten: Ergebnis der Prüfung

Stand der Recherche: 14. November 2026. Ohne konkrete Buchungsseiten und ohne
Anbieterzugang lässt sich eine zuverlässige Restkartenanzahl nicht zusagen.
Öffentlich sichtbare Verkaufsstatus oder ein Bestelllimit sind keine allgemeine
Bestandsabfrage. Zeitweise nicht verfügbare Kontingente können wieder freigegeben
werden. Bestände verschiedener Anbieter können sich überschneiden und dürfen nicht
ungeprüft addiert werden.

ADticket erklärt ausdrücklich, dass „derzeit nicht verfügbar“ ein erschöpftes
aktuelles Kontingent bezeichnet und nicht zwingend eine ausverkaufte Veranstaltung:
https://service.adticket.de/de/support/solutions/articles/15000050430-was-bedeutet-derzeit-nicht-verf%C3%BCgbar-im-bestellprozess-

Reservix bietet offizielle Schnittstellen und Integrationsmöglichkeiten über seine
Partnerschaften. Welche konkreten Bestandsdaten ein Zugang liefert, muss für den
jeweiligen Zugang geprüft werden:
https://www.reservix.net/ticketing-apis-und-schnittstellen-mit-reichweite/
https://www.reservix.net/affiliate-partnerschaft/

Eine spätere Integration benötigt belegte Daten je Veranstaltung/Anbieter. Ein
öffentlicher Status wie verfügbar, derzeit nicht verfügbar oder ausverkauft muss
von einer exakten Zahl getrennt bleiben. Fehlende/gesperrte Antworten bedeuten
„unbekannt“, niemals automatisch null oder ausverkauft. Die vorhandene manuelle
Ticketstatus-/Restkartenangabe bleibt unverändert nutzbar. Ein automatischer
Restkartenabruf ist in diesem Update nicht enthalten.

# Zeitplan: gespeicherte Daten und Ansicht erhalten

Die gewählte Kalenderwoche, Tages-/Wochenansicht und der Wochentag werden im Browser
gemerkt. Ein Neuladen wechselt die Ansicht damit nicht unbeabsichtigt zur aktuellen
Woche. Ansichts- und Onlinefilter verändern keine gespeicherten Zuweisungen.

Bei gemeinsamen Zeitzuweisungen bleiben bestehende IDs und auch doppelte
Altzuweisungen erhalten. Ein Ziel wird erst nach ausdrücklichem Abwählen entfernt.
Das interne `PUT /api/schedules/block` verlangt für entfallende Datensätze zusätzlich
`removedIds`; ohne diese Angabe wird der gesamte Löschanteil zurückgewiesen.
Eine unvollständige Zielauswahl darf keine Zuweisungen stillschweigend löschen.
Nicht mehr verfügbare bestehende Ziele bleiben im Bearbeitungsdialog sichtbar.
Ältere geöffnete Oberflächen müssen nach dem Update neu geladen werden.

Eine automatische Löschung durch den Updatepfad konnte ohne betroffene Datenbank
nicht reproduziert werden. Regressionen vergleichen feste und wiederkehrende
Zuweisungen vor/nach SQLite-Migration und Neustart sowie vor/nach dem nativen
WordPress-Update. Bereits verlorene Daten werden dadurch nicht wiederhergestellt;
dafür wird eine frühere Datensicherung benötigt. Das gezielte endgültige Löschen
von Inhalten und ausdrücklich bestätigte Systemumzüge behalten ihr dokumentiertes
Verhalten.
