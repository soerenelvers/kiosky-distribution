# Eigentümerfreigabe und Übergang zur vorgeschlagenen Automatisierung

Der nachfolgende manuelle Ablauf wurde durch PR #17 eingeführt. Der neue
[CI/CD-Entwurf](ci-cd.md) schlägt dessen Ablösung durch geprüfte App-Workflows vor,
aktiviert sie aber nicht. Codex darf diesen Draft weder zusammenführen noch
Releases auslösen oder Repositoryeinstellungen ändern.

Bis zur ausdrücklichen dauerhaften Freigabe bleibt die neue Automatisierung mit
`KIOSKY_CICD_ENABLED=false` gesperrt. Das Environment `release` sollte für die
Erstfreigabe einen Eigentümer-Reviewer verlangen. Der bisherige manuelle
`workflow_dispatch`-Publishpfad wird vom Entwurf entfernt, damit nach einem späteren
Merge kein zweiter, großzügiger berechtigter Veröffentlichungsweg parallel besteht.
Die historischen Befehle unten sind danach keine aktuellen Bedienanweisungen.

Vollautomatischer Betrieb setzt eine separate Eigentümerentscheidung über die in
`docs/ci-cd.md` dokumentierten Branchregeln, App-Rechte, Secrets und die spätere
Entfernung des einzelnen Deployment-Reviewers voraus. Ein grüner CI-Lauf oder ein
Label allein stellt diese Entscheidung nicht dar. Fehlen technisch erzwingbare
Branch-/Environment-Regeln im verwendeten GitHub-Plan, nicht aktivieren.

---

## Historische manuelle Richtlinie aus PR #17

# Kiosky manual approvals

## Pull requests
Every change starts in a feature branch and draft pull request. Codex must never merge, approve on the owner's behalf, or push directly to `main`.

Repository owner must configure **Settings → Rules → Rulesets** (or branch protection) for `main`:
- Require pull request before merging.
- Require at least one approval from an eligible reviewer, if supported by the repository plan.
- Require CI and Security checks to pass.
- Block force pushes and deletions.
- Disable auto-merge and prevent bypass by automation credentials.

**Important:** Repository rulesets and required reviewers are GitHub settings and are not configured by this PR. For private repositories, availability depends on GitHub plan. If protection is unavailable, the owner must enforce merge approval manually; do not claim it is technically enforced.

## Releases
The release workflow is manually dispatched from `main` with modes `dry-run`, `build-only`, and `publish`.
- `dry-run` / `build-only`: run validations and build artifacts, never publish.
- `publish`: only the GitHub account `soerenelvers` may start publication and must type exactly `PUBLISH KIOSKY` in the confirmation field.
- A push of a `v*.*.*` tag does not trigger the release workflow.
- Publication must never be initiated by Codex or another bot using the owner's identity or credentials.
- The workflow creates the version tag only after successful release/upgrade tests; it then publishes the private distribution, update service and GitHub release.
- An existing release tag blocks the manual publish attempt; investigate before retrying.

## Operational verification before merging
1. Review the workflow diff and verify GitHub Actions YAML parses.
2. Run CI and confirm all checks pass.
3. Run a manual `dry-run` from a test branch and confirm no tags/releases/distribution changes.
4. After merging, confirm the workflow is available on `main`.
5. Confirm the GitHub owner has set branch protection and, where supported, required reviewers.
6. Verify that only the owner can initiate the `publish` action with confirmation.
7. Keep existing production release tags unchanged.
