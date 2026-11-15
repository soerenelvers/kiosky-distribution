# Sicherheits- und Resilienzprüfung – 15. November 2026

## Umfang und Grenzen

Quellcode und isolierte lokale Fixtures der Standalone-, WordPress- und TYPO3-Ausgabe,
öffentliche Player-Zugriffe, native CMS-Rechte, Diagnose und Speicherkonflikte.
Die Prüfung ersetzt keinen unabhängigen Penetrationstest. Kein Lastangriff auf
Produktivsysteme, keine Prüfung der Plesk-/Betriebssystem-/WAF-Konfiguration.
Eine vollständige Widerstandsfähigkeit gegen alle externen Angriffe ist damit nicht
nachgewiesen. HTTP-Body-Grenzen schützen die Anwendung; vorgeschaltete Webserver
müssen den Empfang selbst begrenzen.

## Behobene Befunde in 3.43.0

- **Player-Schlüssel in lesbaren Verwaltungsantworten:** Betrachter und native
  TYPO3-Rollen ohne Geräteverwaltung bekommen keine Player-Schlüssel mehr. Aktuelle
  Ausspielung nutzt eine sitzungsgebundene Vorschau. Geräteverwaltung behält ihre
  notwendigen Kopplungs-/Startfunktionen. Gebundene Standalone-Downloads prüfen
  Geräteverwaltung und Standort ebenfalls, damit sie die Trennung nicht umgehen.
- **Schlüsselwiderruf während Standalone-Anfragen:** Nach vollständigem Lesen einer
  Lebensmeldung wird das aktuelle Gerät samt Schlüssel erneut geprüft. Ein inzwischen
  widerrufener Schlüssel schreibt keinen Status. Vergleich erfolgt zeitkonstant.
- **Unbegrenzte öffentliche Player-Payloads:** Kopplung ist auf 8 KiB, Lebensmeldungen
  auf 128 KiB begrenzt; ungültiges JSON wird abgewiesen. PHP begrenzt zusätzlich die
  JSON-Tiefe auf 32 und übernimmt nur erlaubte Metadaten. WordPress prüft die Größe vor
  seiner JSON-Parameterverarbeitung; JSON darf den geprüften URL-Pfad nicht überschreiben.
  TYPO3 prüft vor dem Domainkern.
- **Standortrechte bei Standalone-Steuerung:** Direkte Gerätekommandos, Zielgruppen,
  Matrizen, Zeitzuweisungen, Presets einschließlich Folgetimern, Warnungen und
  Wiederherstellung werden gegen vorhandene Standortfreigaben geprüft. Gemischte
  Gruppen sind für eingeschränkte Benutzer nicht vollständig steuerbar. Globale
  Notfallzustände bleiben Benutzern mit systemweitem Zugriff vorbehalten.
- **Rollenabbildung im CMS:** Mediengestalter werden der Oberfläche wieder als solche
  gemeldet. Diagnose-Wartung respektiert native Displaysteuerung, ohne damit Geräte-
  oder Einstellungsverwaltung zu gewähren.

Regressionen prüfen außerdem Anmeldung, Authentifizierung, Rechte, Nonces,
Credential-Verschlüsselung/-Redaktion, Integrationseingaben, SSRF-Schutz,
Dateizugriffe, private Schriften und Tokenwiderruf über die vorhandenen Testsuiten.
Die neuen Tests prüfen insbesondere die Schlüsselwiderrufs-Race, Fremdstandorte,
überlange/ungültige Bodies, Datenminimierung, Diagnosefristen und native CMS-Transporte.

## Abhängigkeiten und offener Handlungsbedarf

`pnpm audit --json`: Hauptprojekt ohne gemeldete Advisories zum Prüfzeitpunkt
(78 Abhängigkeiten einschließlich Entwicklung).

`npm --prefix update-service audit --json`: separater Update-Dienst mit 18 moderaten,
0 hohen und 0 kritischen Hinweisen bei 717 aufgelösten Abhängigkeiten. Betroffen sind
unter anderem esbuild/Drizzle-Werkzeuge, minimatch/glob, fast-uri sowie
vinext/satori/fflate. Anzahl umfasst transitive Sammelmeldungen, nicht 18 bestätigte
Kiosky-Laufzeit-Exploits. Upstream empfiehlt teilweise rückwärts inkompatible
Versionswechsel. **Diese Hinweise sind offen**; ein eigener Abhängigkeits-/Build-Review
mit Kompatibilitätsprüfung ist nötig. AGENTS.md verlangt dafür ausdrückliche
Eigentümerprüfung; dieses Release ändert keine Abhängigkeiten oder Workflows.

## Betrieb unter Plesk

1. HTTPS erzwingen, aktuelle unterstützte PHP-/Node-/CMS-Versionen verwenden.
2. Webserver-Limits, Verbindungs-/Request-Raten und Timeouts setzen; Login und
   öffentliche Kopplung besonders überwachen. DDoS-Abwehr benötigt Schutz vor Plesk,
   etwa beim Hostinganbieter. Keine unnötigen Verwaltungsports veröffentlichen.
3. Private Schlüssel/Backups außerhalb des Webroots halten; Dateirechte einschränken.
   Player-Schlüssel als Bearer-Zugang behandeln; kompromittierte Geräte neu koppeln.
4. Vorhandene eigene Backups regelmäßig durch echte Wiederherstellung testen:
   Datenbank, Medien und private Schriftdateien sowie getrennt gesicherte
   Verschlüsselungsschlüssel. Ohne passende Schlüssel sind Integrationszugänge nicht
   wiederherstellbar. RPO/RTO mit Betreiber festlegen.
5. Fehlerquoten, Neustarts, Plattenplatz und TLS-Ablauf überwachen. Neue Player-Warnungen
   ergänzen diese Infrastrukturüberwachung und erkennen keine Netzwerkangriffe selbst.
