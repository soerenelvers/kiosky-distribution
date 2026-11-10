# Vorschlag: Kiosky-Kundenverwaltung und Monetarisierung

## Empfehlung

Kiosky zunächst als betreute Software für Veranstaltungshäuser, Stadthallen und
kommunale Betreiber verkaufen: wiederkehrende Jahresverträge, einmalige
Einrichtung, optionale Schnittstellen und Partnerbetrieb. Standalone, WordPress
und TYPO3 sind technische Installationsformen desselben Produkts. Der Preis
richtet sich nach Nutzen und aktiven Display-Ausgaben, nicht nach dem CMS.

Der Hauptserver kann Kunden und Verträge zur vorhandenen Lizenzverwaltung
ergänzen. Ein vollständiges CRM, eine eigene Zahlungsabwicklung und Zugriff auf
Kundenpasswörter sind dafür nicht erforderlich. Das ist ein Umsetzungsvorschlag;
dieses Update legt noch keine Kundenkonten, Rechnungen oder Zahlungen an.

## Startpakete und Preishypothese

Alle Beträge netto, monatlicher Gegenwert bei jährlicher Vorauszahlung. Preise
sind ein Vorschlag zur Validierung mit Pilotkunden, keine Marktrecherche oder
Rentabilitätszusage. Hosting und Hardware sind gesondert kalkuliert.

| Paket | Preis | Enthalten |
|---|---:|---|
| Signage | 49 €/Monat | 1 produktive Installation, 1 Standort, 3 aktive Display-Ausgaben, Slides/Kanäle, Zeitplanung, Player, Updates |
| Venue | 129 €/Monat | 1 Installation, 1 Standort, 10 Ausgaben, zusätzlich Veranstaltungen, Website-Kalender, Werbung, Presets/Warnungen, API/Companion |
| Organisation | ab 299 €/Monat | Bis 3 Installationen, 3 Standorte, 25 Ausgaben, zentraler Kundenüberblick, vereinbarter Support; darüber individuelles Angebot |

- Zusätzliche aktive Ausgabe: 9 €/Monat; pro logischer Multi-Display-Ausgabe zählen,
  nicht pro EXE, Installation, Browserfenster oder Download.
- easyjob/CrewBrain: ab 29 €/Schnittstelle/Monat, plus Einrichtung und Prüfung der
  tatsächlichen Providerlizenz/API. Individuelle Mappings als Projekt abrechnen.
- Speisekarten: 19 €/Monat als Zusatzmodul, sofern nicht individuell enthalten.
- Einrichtung: 490 € einfach, 990 € mit Kalender/Schulung, Schnittstellen ab
  1.490 € nach Aufwand. Hardware und Vor-Ort-Leistung separat ausweisen.
- Hosting-Paket erst nach Betriebskalkulation anbieten, zum Beispiel ab
  29 €/Monat mit klaren Speicher-, Backup-, Restore- und Zuständigkeitsgrenzen.
- 30 Tage Pilot mit 2 Ausgaben, anschließend ausdrückliche Vertragsannahme.
  Keine automatische Bezahlverlängerung ohne transparente Zustimmung.
- Monatliche Kündbarkeit optional mit 20 % Aufschlag; Pilotpreise nicht mit
  dauerhaftem unbegrenztem Support kombinieren.

Als Startrechnung: 20 Venue-Kunden ergeben 2.580 € monatlichen wiederkehrenden
Softwareumsatz vor Steuer, Zahlungsgebühren, Hosting, Support, Entwicklung und
Vertrieb. Bei 30 Minuten Support je Kunde entstehen bereits 10 Stunden/Monat.
Mit einem internen Vollkostensatz von 70 €/Stunde wären das 700 €; eine
zusätzliche Stunde pro Kunde würde weitere 1.400 € verbrauchen. Deshalb
Einrichtung, Sonderentwicklung und erweiterten Support getrennt verkaufen.

## Kundenmodell auf dem Hauptserver

| Objekt | Wesentliche Felder und Beziehungen |
|---|---|
| Kunde/Organisation | Kundennummer, Firmenname, Rechnungsadresse, Land/USt-ID, Status, zuständiger Partner |
| Ansprechpartner | Name, E-Mail, technische/kaufmännische Funktion, Kunde; keine externen Login-Passwörter |
| Portal-Mitgliedschaft | Benutzer-ID, Kunden-ID, Rolle, Einladungs-/Sperrstatus |
| Vertrag | Kunde, Tarifversion, Laufzeit, Abrechnung, Menge, Preis, Währung, Kündigung, Rechnungsanbieter-ID |
| Lizenz | Bestehende Lizenz-ID, Kunde, Vertrag, erlaubte Module und Installationen |
| Registrierung | Bestehende Instanz-ID, Plattform, Version, letzter Lizenzabgleich, Sperrstatus |
| Mengenanspruch | Max. aktive Ausgaben/Standorte, Verbrauch, letzte bestätigte Meldung |
| Vorgang/Audit | Vertrag/Lizenz/Kunde, Änderung, Akteur, Zeitpunkt, Supportvorgang |

Die bestehenden signierten Modulfreigaben und Instanzregistrierungen werden
weiterverwendet. Kunden-/Vertragsbeziehungen werden additiv ergänzt. Benutzerrechte
innerhalb einer Kiosky-Installation bleiben von kaufmännischen Portalrechten
getrennt. Bestehende Lizenz-„Seats“ bezeichnen Installationen; Display-Mengen sind
ein zusätzlicher Anspruch und aktuell noch kein implementiertes Lizenzlimit.
Der Ablauf einer Freigabe, ihre Offlinegeltung und bestätigte Sperren bleiben
am bestehenden Lizenzvertrag ausgerichtet.

## Portal und Berechtigungen

- Hauptadministrator: alle Kunden, Verträge, Module, Lizenzen und Abrechnung.
- Vertrieb/Abrechnung: Angebote und Verträge, ohne private Signierschlüssel oder
  beliebige technische Freigaben.
- Support: technische Metadaten; zeitlich begrenzte, protokollierte Freigabe für
  eine konkrete Installation. Kein stilles Übernehmen eines Kundenkontos.
- Kundeninhaber: eigene Organisation, Mitglieder, Rechnungen und Lizenzen.
- Kundentechniker: eigene Installationen und Aktivierungen, keine Rechnungs- oder
  Tarifänderungen.
- Partner: nur ausdrücklich zugewiesene Kunden. Partnerzugriff und Vergütung
  getrennt vom Eigentum der Kunden und der Software dokumentieren.

Jede API-Abfrage prüft die Kundenmitgliedschaft serverseitig; eine übergebene
customerId allein gewährt keine Rechte. Passwort-Hashes, Sitzungen und
Integrationsschlüssel der Kundeninstanzen werden nicht in das CRM kopiert.
Administrative Konten mit MFA, kurze Sitzungen, manipulationsnachvollziehbare
Auditdaten und verschlüsselte Backups vor einem zahlenden Pilotbetrieb prüfen.

## Abrechnung und Lizenzänderungen

1. Zunächst Angebote/Rechnungen über vorhandene Buchhaltung, Zahlung per Rechnung
   und manuelle bestätigte Freigabe. Zahlungsanbieter später anbinden.
2. Preis- und Tarifversion beim Vertragsabschluss einfrieren; spätere Tarifpflege
   darf alte Verträge nicht still ändern. Mengen/Leistung explizit vereinbaren.
3. Spätere Zahlungs-Webhooks signaturprüfen, mit eindeutiger Event-ID speichern
   und idempotent verarbeiten. Rückfragen an den Anbieter über eine Queue mit
   begrenzten Wiederholungen; Browser-Rückleitungen sind kein Zahlungsnachweis.
4. Kündigung zum Vertragsende, Zahlungsfehler und bewusste Sicherheitssperre sind
   unterschiedliche Zustände. Mahn-/Kulanzfristen transparent vereinbaren.
5. Bei Provider-Ausfall keine spontane Sperrung bezahlter Kunden. Ein abgestimmtes
   Modell für Kulanz, letzten signierten Stand und Bestandsbetrieb entwickeln;
   die derzeitige Offline-Lizenzlogik nicht ungeprüft ersetzen.

Notfallmeldungen nicht als zertifizierte Alarmierung verkaufen. Reaktionszeiten,
Vertretung, Freigabe neuer Releases und Verantwortlichkeit für den Displaybetrieb
schriftlich festhalten. 24/7 oder SLA erst anbieten, wenn Monitoring und personelle
Bereitschaft tatsächlich bestehen.

## Lizenzierung und Partnervermarktung

LICENSE.md unterscheidet proprietäres Standalone und GPL-2.0-or-later-Ausgaben
für WordPress/TYPO3. Empfänger der GPL-Pakete behalten ihre GPL-Nutzungs-,
Änderungs- und Weitergaberechte. Das Geschäftsmodell für CMS-Ausgaben deshalb
auf laufende Dienste, betreute Updates, Hosting, Integration, Support und Marke
stützen; keine widersprüchlichen Verbote als Zusatzbedingung für GPL-Code
versprechen. Einzelheiten zu Warenzeichen, Vertrieb, Haftung und vertraglichen
Leistungen vor Vertragsabschluss rechtlich prüfen lassen.

Für Integrationspartner zunächst 20 % des tatsächlich bezahlten Softwareumsatzes
im ersten Vertragsjahr als Verhandlungsvorschlag; Provision, Kündigung und
Supportaufteilung schriftlich festhalten. Quellzugriff erfolgt über zugewiesene
Repository-Rechte und einen geregelten PR-/CI-Prozess, nicht über gemeinsame
GitHub-Konten. Kundendaten und Produktions-Secrets gehören nicht ins Repository.

## Einführung und Erfolgskriterien

1. Phase 1: Kundenliste, Kontakte und manuelle Verknüpfung bestehender Lizenzen;
   3–5 Pilotkunden mit schriftlichem Leistungsumfang. Noch kein Zahlungsverhalten
   automatisieren. Datenexport und vollständigen Restore praktisch prüfen.
2. Phase 2: Kundenportal, Einladungen, Rechnungsansicht und kontrollierte
   Aktivierungen; Mandantentrennung und Rechte mit Negativtests abnehmen.
3. Phase 3: Abrechnung/Webhooks, Display-Mengenlimits, Mahn-/Kulanzprozess und
   Partnerübersicht nach stabilen Pilotabläufen implementieren.

Monatlich messen: bezahlter wiederkehrender Umsatz, Kündigungen, aktivierte
Ausgaben je Kunde, Pilot-zu-Kauf-Quote, Supportminuten, direkte Kosten je Kunde,
Fehlerquote von Aktivierungen und erfolgreiche Updates. Nach drei Monaten Tarife
an tatsächlichem Betreuungsaufwand ausrichten. Keine unlimitierte „Lifetime“-Lizenz
mit unbegrenzten künftigen Updates und Support zum Start anbieten.
