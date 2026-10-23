## [3.11.2] – 2026-10-23

- WordPress-Untermenü „Kiosky → Speisekarten“ öffnet die vollständige gemeinsame Verwaltung statt des vereinfachten Kücheneditors: Vorgaben, Kartenerstellung, Vorlagen-/Layoutgestaltung, Ausrichtung, PDF-Import, Weblink-Abonnements und Benutzerzuweisungen sind wieder am vorgesehenen Einstieg erreichbar.
- Öffentliche Küchen- und Passwortfreigaben unter `/speisekarten` behalten ihren getrennten Inhaltseditor und die bestehenden Rechte. WordPress-Anmeldung, REST-Nonce, Lizenzfreigaben und Modulschalter bleiben verbindlich; bestehende Karten und Zuweisungen benötigen keine Migration.
- Regressionen führen die tatsächlichen WordPress-Einstiegsfunktionen aus, prüfen vollständige Verwaltungsdokumente und getrennte Küchen-/Freigaberouten einschließlich Unterverzeichnis-URLs. Native WordPress-Installations-/Upgrade-Prüfungen kontrollieren zusätzlich Menü-Hooks und die ausgelieferten Dokumente. Funktionsabgleich für das Speisekarten-Modul dokumentiert.
