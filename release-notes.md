## [3.4.0] – 2026-10-18

- Standalone erhält eine optionale, exklusive Zugangswerk-Anmeldung über den bestätigten OpenID-Connect-Issuer unter accounts.delevel.de. Einstellungen umfassen Client-Konfiguration, Redirect-URL, echten Verbindungstest und ausdrückliche Kontozuordnungen; Rollen und Standortrechte bleiben erhalten.
- Aktivierung erfordert einen erfolgreich geprüften Administrator, beendet alle bisherigen Sitzungen und sperrt lokale Passwort-/Einladungswege. Kein automatischer Rückfall bei Provider-Ausfall. Maschinenzugänge bleiben auf die externe API begrenzt; Player bleiben nutzbar.
- Verschlüsselte Secrets, Authorization Code Flow mit PKCE S256, State-/Nonce- und signierte Tokenprüfung, Schutz des letzten geprüften SSO-Administrators sowie dokumentierte lokale Wiederherstellung. OIDC-Laufzeit und ihre Lizenztexte werden im Standalone-ZIP gebündelt.
- WordPress und TYPO3 verwenden unverändert ihre CMS-Benutzerverwaltung; die neue Einstellung wird dort nicht angeboten.
