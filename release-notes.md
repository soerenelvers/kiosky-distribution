## [3.3.1] – 2026-10-14

- Externe API-Zugänge lassen sich jetzt auch im WordPress-Plugin und in der TYPO3-Extension unter Schnittstellen → Externe API anlegen, auflisten und sofort widerrufen. Schlüssel werden einmal ausgegeben und ausschließlich gehasht gespeichert; die letzte Nutzung wird angezeigt.
- Gebäudeautomation und externe Integrationen nutzen in allen Varianten Bearer-Authentifizierung oder X-API-Key ohne CMS-Anmeldung. Die CMS-Zugänge sind auf die External Control API beschränkt; die Zugangsverwaltung bleibt Administratoren vorbehalten.
- WordPress erhält den öffentlichen Einstieg `/wp-json/kiosky/v1/control`, TYPO3 `/kiosky-api/v1`. Die Oberfläche zeigt die vollständige Basisadresse und passende Dokumentationslinks. Bestehende Query-Endpunkte, WordPress-Anwendungspasswörter und der bisherige TYPO3-Konfigurationsschlüssel bleiben kompatibel.
- Widerruf und Registrierung von API-Zugängen bleiben auch bei parallelen Zugriffen erhalten. Veraltete CMS-Schreibstände werden abgewiesen; externe Kontrollanfragen werden bei solchen Konflikten mit erneuter Authentifizierung wiederholt.
- Externe CMS-Anfragen erhalten Request-IDs, begrenzte JSON-Payloads und nachvollziehbare API-Akteure im Audit-Log. Kontrollanfragen müssen keine vollständige CMS-Medienbibliothek oder Benutzerliste laden.
- CMS-Statusfilter, Zeitfenster bei Preset-Zuweisungen und das Ablösen vorheriger externer Warnungen entsprechen der gemeinsamen API. Zeitlich begrenzte Webhook-Warnungen werden im CMS-Player nur während ihres Gültigkeitsfensters angezeigt.
- Neue HTTP-, Berechtigungs- und Transporttests prüfen Schlüsselverwaltung, Ablehnung ungültiger/widerrufener Schlüssel, Ressourcenabfragen, Kanal-/Slide-Zuweisungen, Presets, Webhooks und Warnungen bis zur Player-Ausgabe in Standalone, WordPress und TYPO3 13/14.

### Einrichtungs- und Dokumentationsnachtrag

- Die eigenständige öffentliche HACS-Integration 1.0.0 ist unter https://github.com/soerenelvers/hacs-kiosky verfügbar; sie benötigt Kiosky ab 3.3.1 und Home Assistant ab 2026.3.0.
- [Einrichtung](https://github.com/soerenelvers/hacs-kiosky/blob/main/docs/kiosky-setup.md) dokumentiert Fine-grained-Update-Tokens, manuelle Updateprüfungen, transparente Lauftexte, native CMS-Medien, externe API-Zugänge und Home-Assistant-Automationen.
- Veraltete CMS-Authentifizierungsangaben und die Beschreibung der WordPress-Prüfsummenprüfung wurden berichtigt. Die veröffentlichten 3.3.1-Pakete und Versions-Tags bleiben unverändert.

## Einrichtung und weiterführende Anleitungen

- [Durchgehende Einrichtung: Updates, Medien, transparente Lauftexte, externe API und HACS](https://github.com/soerenelvers/hacs-kiosky/blob/main/docs/kiosky-setup.md)
- [HACS-Installation und alle Automationsaktionen](https://github.com/soerenelvers/hacs-kiosky#installation-über-hacs)
- [Eigenständiger Home-Assistant-Release 1.0.0](https://github.com/soerenelvers/hacs-kiosky/releases/tag/v1.0.0)

Kiosky 3.3.1 benötigt für externe Steuerung einen in Kiosky erzeugten API-Schlüssel. Der GitHub-Token unter WordPress → Einstellungen → Kiosky Updates dient ausschließlich dem Updatezugang.
