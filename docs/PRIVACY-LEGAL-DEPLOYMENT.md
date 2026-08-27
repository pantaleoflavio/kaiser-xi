# Privacy and legal deployment checklist

> **Production blocker:** Kaiser XI MUST NOT be deployed publicly until every mandatory
> `VITE_LEGAL_*` value in `frontend/.env.example` has been verified and configured.
> Provider locations, processing agreements, subprocessors, international-transfer
> safeguards, log retention, and the competent supervisory authority must also be
> reviewed against the selected production infrastructure. The in-app “being finalized”
> text is development-safe presentation, not production-ready legal identification.

## Repository data and storage audit (25 August 2026)

Kaiser XI persists account display name, email, hashed password, email verification time,
optional theme, roles, Privacy Policy acknowledgement timestamp, account timestamps, password-reset token records, and hashed Sanctum access
tokens with token metadata. Gameplay persistence includes League membership/roles and
invitations, Fantasy Team ownership/names/rosters/budgets, formations, trades, scores,
standings, results, and competition history. The backend session schema can store user ID,
IP address, user agent, payload, and last activity when session-backed interfaces are used.
Application/server logs may contain normal request and security metadata according to the
runtime and host configuration.

The browser uses `localStorage` for the bearer authentication token and selected language.
It uses `sessionStorage` only for the temporary newly-created League ID. No IndexedDB use
was found. Laravel supports a necessary session cookie for session/admin operation; the
SPA authenticates API calls with the bearer token. No analytics, telemetry, advertising,
tracking pixel, behavioral tracking, or marketing storage was found. Accordingly no
cookie-consent banner or CMP was added. The UI uses system/local fonts and repository
assets; it does not fetch external fonts. The game-instructions FAQ contains a user-clicked
external forum link, but no third-party script or embedded resource is loaded.

Backend integrations are the configured database, mail transport, filesystem/cache/queue,
and deployment host; their production providers are not yet fixed. Transactional emails
are email verification and password reset. No payment or donation integration exists.

## Security and minimization review

Public/game resources were reviewed for account secrets. `UserResource` returns email only
for the authenticated account endpoints; League member resources expose display identity,
not email or credential fields. Eloquent hides password and remember token. No API exposure
of password hashes, remember tokens, bearer tokens belonging to other users, reset tokens,
verification secrets, or internal security metadata was found. Privacy Policy acknowledgement
is stored as a timestamp so legacy accounts can be restricted until they acknowledge the
current policy; it is deliberately not represented as general GDPR or marketing consent.

Account deletion keeps the existing anonymization model: it deletes reset and access tokens,
detaches roles, clears verification/privacy acknowledgement/theme/remember token, replaces name/email/password, and
retains anonymized relationships needed for historical competition integrity.

No Terms/AGB page exists. A concise, non-commercial Terms of Use page could be considered
as a separate milestone after operator rules are decided; this milestone does not fabricate
one. Donations likewise remain a separate future decision.