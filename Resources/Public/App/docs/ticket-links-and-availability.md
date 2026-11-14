# Mehrere Ticketanbieter und Restkarten

Ab 3.40.6 lassen sich in einer Veranstaltung über **＋ Weiteren Ticket-Link hinzufügen**
bis zu zehn Anbieter mit Name und URL pflegen. Der erste Eintrag ist der Hauptlink.
**Als Hauptlink** wechselt das Ziel bestehender Ticket-QR-Codes und Ticket-Platzhalter;
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
