# DWD-Warnungen: Gemeindegenauigkeit, Freigabe und Einblendung

Ab 3.48.0 verwenden Standalone, WordPress und TYPO3 dasselbe Verhalten.
Unter **Presets & Warnhinweise → DWD** einen Standort mit amtlicher WarnCellID
wählen. Die Kennung steht im Standortdialog; die Suche verwendet die offizielle
DWD-Warngebietsliste. **806532023** bezeichnet die **Stadt Wetzlar**.

## Quellen und bestätigter Fehler

Der bisherige JSONP-Abruf
`https://www.dwd.de/DWD/warnungen/warnapp/json/warnings.json` liefert insbesondere
Kreiswarnungen. Er enthielt im Live-Test Warnungen für **106532000 (Lahn-Dill-Kreis)**,
aber keinen Eintrag für **806532023**. Die bisherige Präfixsuche fand deshalb keine
Wetzlarer Gemeindewarnungen.

Gemeindekennungen mit führender 8 werden jetzt über den offiziellen DWD-WFS
`https://maps.dwd.de/geoserver/dwd/ows` und `dwd:Warnungen_Gemeinden` abgefragt,
mit exaktem Filter `WARNCELLID=806532023`. Nur benötigte Text-/Zeitfelder werden
angefordert; keine landesweiten Geometrien. Es gibt keinen pauschalen Rückfall auf
alle Kreiswarnungen. Andere bestehende neunstellige WarnCellIDs und historische
zwölfstellige Kreiskennungen verwenden weiterhin den bisherigen DWD-Dienst.

Der öffentliche Live-Abruf am **17. November 2026** wurde sowohl am DWD-Endpunkt
als auch über den tatsächlichen neuen Node-Client geprüft. Er lieferte zwei
Gemeindewarnungen: Windböen (Stufe 1) und angekündigte Sturmböen (Stufe 2).
Dies ist eine Momentaufnahme, keine Zusage über später vorhandene Warnungen.
Es wurden keine Meldungen an produktive Displays veröffentlicht.

## Vorschau und Übernahme

- **DWD-Warnungen abrufen** zeigt die Vorschau aller vom Dienst gelieferten
  Warnungen für genau dieses Gebiet. Sie erzeugt oder veröffentlicht keine Warnung.
- **Mindestwarnstufe** und **Warnarten** bestimmen die Übernahme. Warnarten sind
  Teilbegriffe ohne Beachtung der Groß-/Kleinschreibung; `Sturm` passt etwa auf
  `STURMBÖEN`. Die Vorschau kennzeichnet nicht passende Einträge weiterhin sichtbar.
- DWD-Stufen: **1 Wetterwarnung**, **2 markante Warnung**, **3 Unwetterwarnung**,
  **4 extremes Unwetter**. Die bisherige Mindeststufe 2 bleibt erhalten; für
  Windböen der Stufe 1 „Alle Warnstufen“ oder Stufe 1 wählen. Die vorhandene
  Sonderstufe 5 bleibt für alte Konfigurationen erreichbar, ist kein DWD-Standard.
- **Abrufen und übernehmen** speichert passende, noch nicht abgelaufene Meldungen.
  Neue Meldungen warten standardmäßig auf ausdrückliche Bestätigung. Die Option
  **Beim Übernehmen ohne zusätzliche Bestätigung veröffentlichen** gibt neue
  passende Meldungen beim Übernehmen sofort frei.
- Angekündigte Warnungen dürfen bereits übernommen werden, erscheinen auf Displays
  aber erst zu ihrem amtlichen Beginn. Vorschauzeiten werden für Deutschland
  (Europe/Berlin, MEZ/MESZ) dargestellt.
- **Import für diesen Standort aktiviert** sperrt beziehungsweise erlaubt den
  Abgleich. Deaktivieren verhindert neue Übernahmen; eine bereits bestätigte
  Warnung wird dadurch nicht stillschweigend zurückgenommen.

**Neu: Übersicht → DWD-Warnungen auslösen.** Einen Standort wählen und die
gewünschte Warnung anklicken. Im Dialog lassen sich weitere Warnungen desselben
Standorts auswählen, anschließend Ziele und Anzeigeregel. Erst **Auswahl bestätigen
und Warnung auslösen** startet die Ausspielung. Das Öffnen der Übersicht und der
Vorschau veröffentlicht nichts. Verfügbare Warnungen werden beim Öffnen geprüft;
ein kurzzeitig gehaltener Stand lässt sich mit **Aktualisieren** erneuern.

Die manuelle Auswahl ist unabhängig von Mindestwarnstufe und Warnarten der
Sammelübernahme. Vor jeder Auslösung prüft der Server alle ausgewählten Kennungen
nochmals beim DWD. Fehlt eine, wird die gesamte Auswahl abgewiesen. Amtliche Texte,
Beginn und Ende werden nicht aus dem Browser übernommen.

**Ausspielwege:** Standort, alle Displays oder eigene Auswahl. Die eigene Auswahl
bietet Suche nach Namen und einen Zieltypfilter. Die Vorschau zählt tatsächlich
betroffene Displays ohne Doppelzählung bei überlappenden Gruppen/Standorten.
Offline-Displays bleiben zugewiesen; gültige Meldungen erhalten sie nach ihrer
Verbindung. Nicht mehr vorhandene Ziele werden nicht stillschweigend ersetzt.


## Häufigkeit und Dauer je Standort

| Einstellung | Verhalten |
|---|---|
| Durchgehend bis zum DWD-Ende | Bisheriges Verhalten; vorhandene Konfigurationen werden nicht automatisch umgestellt. |
| Nur einmalig | Eine Einblendung je Warnung und Display, für die eingestellte Dauer. |
| Wiederholen alle X Minuten | Beginn der Einblendung alle X Minuten, jeweils für die eingestellte Dauer; dazwischen regulärer Inhalt. |

Die Dauer wird getrennt als **Minuten : Sekunden** eingegeben, etwa **00:30**.
Beispiel: **Intervall 10 Minuten**, **Dauer 00:30** ergibt 30 Sekunden Warnung,
9 Minuten 30 Sekunden regulären Inhalt und danach die nächste Einblendung.
Die Dauer darf das Intervall nicht überschreiten; zulässig sind 1 Sekunde bis
24 Stunden Dauer und 1 bis 10.080 Minuten Intervall. Das amtliche Warnungsende
begrenzt jede Einblendung unabhängig von diesen Einstellungen.

**Einstellungen speichern** übernimmt die Regel; sie gilt beim nächsten
Player-Stand auch für bereits bestätigte DWD-Meldungen dieses Standorts.
Erneutes Speichern derselben Regel startet keinen neuen Durchlauf.
Eine ausdrücklich geänderte Anzeigeregel startet einen neuen Durchlauf, auch
beim Wechsel zurück auf eine zuvor verwendete Regel.
Im neuen Auslöse-Dialog gilt die Regel **nur für die ausgewählten Warnungen**;
Standortvorgaben und andere aktive Warnungen werden dadurch nicht verändert.
Erneutes Auslösen startet bewusst einen neuen Durchlauf. Ein späterer Abgleich
behält individuelle Ziele und Regeln bei.
Manuelle Warnungen, Notfallmeldungen und Presets behalten ihr bisheriges Verhalten.

Die Einmal-/Intervallzeit beginnt mit dem ersten berechtigten Player-State-Abruf,
bei dem die Warnung tatsächlich als vorrangige Meldung ausgewählt wird. Die
Zuordnung wird serverseitig je Display und Warnung gespeichert. Ein Player-Neustart
setzt sie nicht zurück: Innerhalb einer laufenden Einblendung bleibt nur deren
Restzeit, danach erscheint dieselbe einmalige Meldung nicht nochmals. Eine neue
amtliche DWD-Meldung mit neuer Kennung wird eigenständig behandelt.

Eine vorrangige andere Warnung verbraucht noch nicht gestartete Einmalanzeigen
nicht. Verwaltungs-/Displayvorschauen und Schriftabrufe verbrauchen ebenfalls
keine Einmalanzeige. Vor dem ersten echten Abruf kann die Vorschau nur eine
mögliche erste Einblendung darstellen; sie bestätigt keine tatsächliche Sichtbarkeit.
Mehrere Displays am selben PC haben unabhängig voneinander ihren eigenen Zähler.

Der Web-Player erhält die nächste Zeitgrenze vom Server und blendet lokal am
Dauerende aus, ohne auf den normalen Fünf-Sekunden-Abgleich zu warten. Auch bei
Verbindungsverlust wird eine abgelaufene zwischengespeicherte Einblendung nicht
endlos gezeigt. Weitere Wiederholungen benötigen einen aktuellen Player-Stand.
Eine bestätigte physische Bildschirmdarstellung kann das HTTP-Protokoll nicht
beweisen; der Zeitbeginn ist der genannte Player-Abruf.

## Mehrere Warnungen gemeinsam

Bis zu 20 Warnungen desselben Quellstandorts können gemeinsam aktiviert werden.
Die Ausgabe zeigt je Warnung Überschrift, Warnstufe, Gebiet und Handlungshinweise
in getrennten Karten. Querformat, Hochformat und UHD werden berücksichtigt;
Hintergrund-/Textfarbe und Lauftext stammen weiterhin aus der Warnvorlage. Die
Sammelausgabe verwendet ihre eigene Kartenanordnung statt des Layouts einer
Einzelwarnung.

Jede Warnung behält ihre amtliche Gültigkeit. Angekündigte Warnungen werden erst
ab Beginn sichtbar. Ablauf oder Rücknahme eines Eintrags beendet die übrigen
nicht. Eine Entfernung startet eine einmalige Gruppe nicht erneut. Kommt später
eine zuvor noch nicht sichtbare Warnung hinzu, erhält die aktualisierte Gruppe
ein neues gemeinsames Zeitfenster. Ausgaben anderer Displays bleiben unabhängig.

Beim Bearbeiten einer aktiven Gruppe werden abgewählte Mitglieder beendet.
Zwischenzeitlich ersetzte Gruppen können keine neuere Auslösung überschreiben.
Einmalige Gruppen und bereits gesehene Mitglieder bleiben serverseitig und beim
Systemumzug gespeichert. Offline blendet der Player bekannte Einzelabläufe aus;
eine noch unbekannte Rücknahme benötigt weiterhin einen erfolgreichen Abgleich.

## Hintergrundabgleich

Nur bereits aktive oder zur Bestätigung übernommene DWD-Meldungen werden regelmäßig
auf Änderungen, Ablauf und Rücknahmen geprüft. Es werden **keine neuen Warnungen
ungefragt aktiviert**. Je Tick werden höchstens drei fällige Standorte bearbeitet,
frühestens 60 Sekunden nach dem letzten erfolgreichen beziehungsweise fehlgeschlagenen
Versuch. Bei mehreren Standorten oder langsamer Quelle kann eine Rücknahme später
bekannt werden; das bereits bekannte amtliche Ende gilt unabhängig davon.

- **Standalone:** eigener Timer, keine DWD-Netzwerkabfrage in Player-Anfragen.
- **WordPress:** die Aufgabe wird automatisch für WP-Cron eingetragen. WP-Cron muss
  laufen; bei deaktiviertem WP-Cron muss der vorhandene Server-Cron ihn aufrufen.
- **TYPO3:** Scheduler-Aufgabe **Kiosky: DWD-Warnungen abgleichen** jede Minute
  ausführen. Alternativ bei Composer-Installationen `vendor/bin/typo3 kiosky:dwd:refresh`
  regelmäßig über den vorhandenen Server-Cron starten. Die Erweiterung registriert
  die Aufgabe, richtet aber keinen produktiven Server-Cron eigenständig ein.

Ein fehlender Scheduler oder ein ausgefallener DWD-Dienst kann eine vorzeitige
Rücknahme verzögern. Bei zeitkritischer Rücknahme die Warnung im Backend beenden.

## Wiederholter Abruf, Rücknahme und Fehler

Bereits bestätigte Warnungen bleiben beim erneuten Abgleich bestätigt. Manuell
beendete Warnungen werden nicht erneut aktiviert. Zwei Standorte mit derselben
WarnCellID erhalten getrennte Datensätze und Zielzuweisungen.

Nach einem erfolgreichen, vollständig validierten Snapshot werden abgelaufene
oder vom DWD nicht mehr gelieferte Meldungen **nur dieses Standortproviders**
beendet. Manuelle Meldungen und andere Standorte bleiben erhalten. Bis zum nächsten erfolgreichen
Abgleich kann eine Rücknahme noch unbekannt sein; das bekannte amtliche Ende
wird trotzdem eingehalten.

Timeouts, HTTP-Fehler, falsche Gebiete, ungültige Pflichtfelder und unvollständige
Antworten führen zu einem Fehler. Sie werden nicht als „keine Warnungen“ behandelt
und löschen/beenden keine zuvor gespeicherten Meldungen. Die Oberfläche kennzeichnet
eine erhaltene Vorschau nach Fehler ausdrücklich als nicht aktualisiert.

Wird die Konfiguration während des Abrufs geändert, muss neu abgerufen werden.
Node übernimmt einen Warnungsbatch atomar. Native CMS-Snapshots lesen die aktuelle
Konfiguration ohne den ursprünglichen Speicher-/Konfliktvergleich zurückzusetzen;
WordPress behandelt Anzeigezähler als Statusdaten und behält die bestehenden
Schutzregeln für parallele Konfigurationsänderungen bei.

## Betrieb und Update

HTTPS-Ausgang zu `maps.dwd.de` und `www.dwd.de` muss erlaubt sein. Es werden weder
DWD-Zugangsdaten noch eine kostenpflichtige WarnWetter-App-Anmeldung benötigt.
TLS-Prüfung und bestehende CMS-Berechtigungen bleiben aktiv. Der Warnungsabruf
ist auf 12 Sekunden und 8 MiB begrenzt; unvollständige 100-Treffer-Antworten
werden konservativ abgewiesen. Node-Installationen mit ausdrücklich notwendigem
Umgebungsproxy müssen diesen auch für Node-Fetch konfigurieren, beispielsweise
mit `NODE_USE_ENV_PROXY=1` unter Node 24. Zertifikatsprüfung nicht deaktivieren.

Alle drei Varianten auf 3.48.0 aktualisieren und die Player einmal neu laden,
damit sie den sekundengenauen lokalen Timer übernehmen. Eine neue Desktop-EXE
oder Kopplung ist für diese Web-Player-Erweiterung nicht erforderlich.
Die Anzeigezähler werden im bestehenden Zustandsspeicher gespeichert und beim
vollständigen Systemumzug zwischen den Varianten übernommen. Keine neue SQL-Tabelle,
keine Änderung an Zugangsdaten und keine automatische Umstellung alter Anzeigeregeln.

## Nachweise und Grenzen

- Node-/PHP-Vertragstests verwenden denselben öffentlichen Wetzlarer Snapshot.
- Native CMS-Tests prüfen Bestätigung, Teilwortfilter, Standorte, einmalige und
  wiederholte Zeitfenster, Neustart, deaktivierten Import, Rücknahme und Fehlererhalt.
- Native Snapshot-/Merge-Tests prüfen den Erhalt des Schreibvergleichs und
  paralleler Display-/Inhaltsänderungen. Einmalzustände werden beim Umzug erhalten.
- `tests/dwd-browser.py` prüft die drei gemeinsamen Plattformtransporte in
  Chromium/WebKit: Handy/Tablet, Vorschau, Freigabe, Zwei-Sekunden-Anzeige,
  Offline-Ausblenden, erneutes Laden, Wiederholung und Konfigurationsänderung
  während eines gehaltenen Abrufs. Externe HTTP-/Uhr-Adapter liegen nur unter
  `tests/fixtures`; keine entsprechenden Produktionsschalter.

Ein Browser-/Serverabruf ist keine Hardwareabnahme oder Garantie eines
unterbrechungsfreien DWD-Dienstes. Kiosky ersetzt keine behördliche Alarmierung
oder Sicherheits-/Brandmeldeanlage.

Beim Rückrollen auf eine Version vor 3.47.0 werden die neuen Häufigkeits-/Dauerregeln
nicht ausgewertet; dort gilt wieder die bisherige durchgehende Warnungsanzeige.
Ein Rückrollplan muss deshalb auch die aktuell bestätigten DWD-Meldungen
berücksichtigen. Inhalte und vorhandene Metadaten benötigen keine Rückmigration.

## Verzögerungen und WordPress-Status

Player-, DWD-, Status-, Zielkatalog- und Ausgabevorschau-Anfragen enumerieren keine
komplette CMS-Mediathek mehr. Nur in der autorisierten Ausgabe referenzierte native
Medien werden über exakte IDs nachgeladen. Dateiänderungen ändern weiterhin die
Inhaltsrevision. Native Leserechte und FAL-Speicherfreigaben bleiben aktiv.

WordPress führt unabhängige Laufzeitrevisionen pro Display zusammen. Änderungen
an Konfiguration, Rechten oder demselben Ausspielweg erzwingen weiterhin eine
Neuberechnung. Ein Speicherkonflikt wiederholt innerhalb derselben DWD-Anfrage
keinen schon erfolgreichen Providerabruf. Während HTTP wird keine Zustands-
Schreibsperre gehalten. Hintergrundläufe rechnen nach HTTP mit frischem Zustand,
sodass zwischenzeitliche Heartbeats erhalten bleiben.

Die Player-Uhr verwendet zusätzlich den Versandzeitpunkt und die gemessene
Serververarbeitung. Eine langsame Verarbeitung wird damit nicht als einseitige
Netzwerklatenz auf die Uhr aufgeschlagen. Die vorhandenen Online-Grenzen und
HTTP-Zeitlimits werden nicht erhöht; Erfolgsmeldungen ersetzen keine Heartbeats.
Der normale Player-Abgleich kann nach bestätigter Auslösung noch bis zu ungefähr
fünf Sekunden benötigen. Ein DWD-Abruf selbst bleibt auf zwölf Sekunden begrenzt.

Nachgewiesen sind native API-/Speicher- und Browserprüfungen in isolierten
Testsystemen. Ohne produktive Serverlogs ist damit nicht bewiesen, dass jede auf
Plesk beobachtete Unterbrechung dieselbe Ursache hatte.

Beim Rückrollen auf 3.47.0 gelten wieder die Standortregeln und die bisherige
Einzelwarnungs-Priorität; Gruppen und individuelle Regeln werden dort nicht
ausgewertet. Vor einem Rollback aktive Ausgaben prüfen. Eine SQL-Rückmigration
ist nicht erforderlich.
