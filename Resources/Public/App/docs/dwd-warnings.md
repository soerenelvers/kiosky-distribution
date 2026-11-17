# DWD-Warnungen: Gemeindegenauigkeit, Freigabe und Einblendung

Ab 3.47.0 verwenden Standalone, WordPress und TYPO3 dasselbe Verhalten.
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

Diese Schaltflächen richten keinen periodischen DWD-Hintergrundabruf ein. Die
unten beschriebene Wiederholung steuert die Ausspielung bereits bestätigter
Warnungen und löst keine zusätzlichen DWD-Anfragen pro Display oder Einblendung aus.

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

## Wiederholter Abruf, Rücknahme und Fehler

Bereits bestätigte Warnungen bleiben beim erneuten Abgleich bestätigt. Manuell
beendete Warnungen werden nicht erneut aktiviert. Zwei Standorte mit derselben
WarnCellID erhalten getrennte Datensätze und Zielzuweisungen.

Nach einem erfolgreichen, vollständig validierten Snapshot werden abgelaufene
oder vom DWD nicht mehr gelieferte Meldungen **nur dieses Standortproviders**
beendet. Manuelle Meldungen und andere Standorte bleiben erhalten. Ohne erneuten
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

Alle drei Varianten auf 3.47.0 aktualisieren und die Player einmal neu laden,
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

Beim Rückrollen auf eine ältere Version werden die neuen Häufigkeits-/Dauerregeln
nicht ausgewertet; dort gilt wieder die bisherige durchgehende Warnungsanzeige.
Ein Rückrollplan muss deshalb auch die aktuell bestätigten DWD-Meldungen
berücksichtigen. Inhalte und vorhandene Metadaten benötigen keine Rückmigration.
