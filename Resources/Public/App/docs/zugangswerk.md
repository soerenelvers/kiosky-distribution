# Zugangswerk-Anmeldung (nur Standalone, ab 3.4.0)

Zugangswerk verwaltet die Anmeldedaten unter **accounts.delevel.de**. Der bestätigte
OpenID-Connect-Issuer lautet:

```text
https://accounts.delevel.de/identity/realms/zugangswerk
```

WordPress und TYPO3 verwenden weiterhin ausschließlich ihre CMS-Anmeldung. Dort
gibt es keine Zugangswerk-Einstellung und keine Standalone-Authentifizierungsrouten.

## Einrichtung

1. Lokal als Kiosky-Administrator anmelden. Zuvor Datenbank und den bestehenden
   `KIOSKY_ENCRYPTION_KEY` sichern. Der Schlüssel muss mindestens 32 Zeichen lang
   sein und bei Neustarts erhalten bleiben.
2. In Zugangswerk einen OpenID-Connect-Client für diese Installation anlegen:
   **Client-Authentifizierung aktiv**, **Standard Flow / Authorization Code aktiv**,
   PKCE **S256**. Die Client-ID und das Client-Secret werden anschließend in Kiosky
   eingetragen; keine Passwort- oder Implicit-Grants erforderlich.
3. Unter **Einstellungen → Zugangswerk** die öffentliche HTTPS-Adresse der
   Kiosky-Installation ohne Unterpfad, Client-ID und Client-Secret speichern.
   Der Issuer ist fest auf Zugangswerk begrenzt. HTTP ist ausschließlich für
   lokale Entwicklung auf `localhost`, `127.0.0.1` oder `[::1]` erlaubt.
4. Die angezeigte Redirect-URL exakt als gültige Redirect-URI in Zugangswerk
   registrieren, beispielsweise:

   ```text
   https://kiosky.example.org/api/auth/zugangswerk/callback
   ```

   Keine Wildcards verwenden. Falls Zugangswerk Web Origins verlangt, die
   öffentliche Kiosky-Origin eintragen. Der Reverse Proxy muss HTTPS korrekt
   über `X-Forwarded-Proto: https` an Kiosky melden, damit Sitzungscookies als
   Secure gesetzt werden.
5. **Mit meinem Konto prüfen und verknüpfen** wählen. Bei Zugangswerk anmelden.
   Kiosky prüft den vollständigen Code-Flow einschließlich PKCE, State, Nonce,
   Issuer, Audience, Ablaufzeit und JWT-Signatur über JWKS. Das bestätigte
   Zugangswerk-Konto wird dem aktuell angemeldeten Kiosky-Administrator zugeordnet.
   Eine abgelaufene oder zwischenzeitlich widerrufene Administratorsitzung genügt
   dafür nicht. Eine reine Discovery-Prüfung schaltet die Funktion nicht frei.
6. Erst nach erfolgreichem Test **Zugangswerk-Authentifizierung nutzen** aktivieren
   und die Umstellung bestätigen. Alle bisherigen Sitzungen enden. Anschließend
   erneut über Zugangswerk anmelden.

Client-Secrets werden serverseitig AES-256-GCM-verschlüsselt gespeichert und nie
zurück an den Browser geliefert. Ein leeres Secret-Feld behält das gespeicherte
Secret bei. Verbindungseinstellungen lassen sich nur bei deaktivierter Funktion
ändern; Änderungen widerrufen die bisherige Testfreigabe und SSO-Sitzungen.

## Konten, Rollen und Zugriff

- Bestehende Kiosky-Benutzer, Rollen und Standortrechte bleiben erhalten.
- Weitere Benutzer unter **Benutzer & Rollen** anlegen und unter
  **Einstellungen → Zugangswerk → Kontozuordnungen** mit ihrer unveränderlichen
  Zugangswerk-Benutzer-ID (`sub`, bei Keycloak die Benutzer-ID) verbinden.
  Bei aktivem Zugangswerk wird hierfür kein lokales Startpasswort verlangt.
- Nur ausdrücklich zugeordnete und in Kiosky aktive Benutzer können sich anmelden.
  Eine passende E-Mail-Adresse genügt nicht. Es werden weder Konten automatisch
  verbunden noch automatisch Administratorrechte vergeben.
- Passwörter werden in Zugangswerk verwaltet. Lokale Login-, Passwort- und
  Einladungswege sind bei aktiver Integration serverseitig gesperrt. Alte lokale
  Sitzungstoken bleiben auch nach einer späteren Deaktivierung ungültig.
- Entfernen oder Ändern einer Zuordnung beendet die Sitzungen des Benutzers.
  Der letzte erfolgreich geprüfte aktive Zugangswerk-Administrator kann weder
  entfernt, deaktiviert, herabgestuft noch umgebunden werden.
- Kiosky-Sitzungen laufen nach acht Stunden ab. Das lokale Deaktivieren eines
  Benutzers beendet seine Sitzungen sofort. Eine alleinige Deaktivierung in
  Zugangswerk verhindert neue Anmeldungen, beendet bereits bestehende
  Kiosky-Sitzungen jedoch nicht automatisch; kein Backchannel-Logout konfiguriert.
- Externe API-Zugänge und Player funktionieren weiterhin. Bei aktivem Zugangswerk
  ist auch der optionale globale Admin-API-Schlüssel auf `/api/v1` beschränkt;
  er kann die Anmeldung nicht über Verwaltungs- oder Konfigurationsrouten umgehen.

## Ausfall und Wiederherstellung

Ein Provider-Ausfall, falsches Secret oder fehlgeschlagener Login schaltet niemals
automatisch die lokale Anmeldung frei. Mit einer noch gültigen Zugangswerk-
Administratorsitzung kann die Funktion ausdrücklich deaktiviert werden; dabei
enden erneut sämtliche Sitzungen.

Falls kein administrativer Zugang mehr möglich ist, ausschließlich mit Zugriff
auf den Kiosky-Server vorgehen:

1. Anwendung stoppen und Datenbank einschließlich WAL konsistent sichern.
2. Im Anwendungsordner mit derselben Datenbankkonfiguration ausführen:

   ```sh
   node --env-file-if-exists=.env dist/scripts/zugangswerk-recovery.js --disable
   ```

   Falls die Datenbank über Plesk/Prozessmanager statt `.env` konfiguriert wird,
   dort denselben Wert für `KIOSKY_DATABASE_PATH` verwenden.
3. Anwendung starten. Mit dem bisherigen lokalen Administratorkonto anmelden,
   Verbindung korrigieren und den echten Zugangswerk-Login erneut prüfen.

Das lokale Wiederherstellungsskript deaktiviert Zugangswerk, beendet alle Sitzungen
und protokolliert den Vorgang. Es erzeugt kein Konto und kein Passwort; es gibt
keinen öffentlichen Wiederherstellungsendpunkt. Bestehende lokale Passwörter
werden durch die Integration nicht gelöscht. Vor einem manuellen Downgrade auf
eine Version vor 3.4.0 beachten: Diese Versionen können die Zugangswerk-Exklusivität
nicht erzwingen und dürfen daher nicht als unveränderte Sicherheitskonfiguration
betrachtet werden.

## Validierung

Automatisierte Tests verwenden einen kontrollierten OIDC-Provider mit echten
RSA-Signaturen und PKCE-Prüfung. HTTP-Tests prüfen den tatsächlichen Standalone-
Server einschließlich Sperre lokaler Sitzungen, Einladungen, API-Schlüssel und
einer bei Aktivierung noch laufenden lokalen Konfigurationsanfrage.

Die öffentliche Discovery wurde am 18.10.2026 erfolgreich gelesen. Ein Live-Login
mit dem produktiven Zugangswerk-Client setzt dessen Client-ID, Client-Secret und
registrierte Redirect-URI voraus und wird durch den Verbindungstest in der
jeweiligen Installation geprüft. Produktive Zugangsdaten gehören in die
Einstellungen, nicht in Git oder Chat.
