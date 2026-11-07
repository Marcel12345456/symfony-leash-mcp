# 024 — Inbox auth, signed expiring item URLs, mailer in tests

- Date: 2026-11-07
- Status: accepted (plaintext-hasher lookup confirmed by `InboxTest` login)
- PLAN reference: §3.3 HumanInput inbox + email; M2 acceptance

## Question

Symfony 8.1: UriSigner with expiry, in-memory `approver` behind form_login while `/mcp/` stays stateless, signed item routes without login, and synchronous mailer assertions.

## Answer

- **UriSigner** (`http-foundation` 8.1.11, `UriSigner.php:30-122`): service `uri_signer` / autowired `UriSigner`, secret `%kernel.secret%`, clock service. `sign(string, \DateTimeInterface|\DateInterval|int|null $expiration)`. `check()`/`checkRequest()` return bool only; `verify(Request|string)` throws `UnsignedUriException`, `UnverifiedSignedUriException`, `ExpiredSignedUriException` (all `SignedUriException`). Hash is checked before expiry, so an edited `_expiration` is "unverified". Sign absolute URLs.
- **Security**: `algorithm: plaintext` hasher exists (`SecurityExtension.php:755-761`); `auto` never picks plaintext, so the hasher is keyed on `InMemoryUser`. `form_login` `enable_csrf` default false; CSRF needs only `security-csrf` + session + `csrf_token('authenticate')` in Twig. Firewalls and access_control are first-match.
- **Mailer tests**: `MailerAssertionsTrait` (`assertEmailCount`, `getMailerMessage`, `assertEmailHtmlBodyContains`, …) reads the message logger; `SendEmailMessage` is not routed to `async`, so mail sent from a handler counts as sent. `message_bus: false` in test keeps it in-process.

## Decision

- Item URL: `/inbox/item/{id}`, signed absolute with `PT24H` expiry (`LEASH_INBOX_LINK_TTL`), `PUBLIC_ACCESS` in access_control; the controller skips the check for `ROLE_APPROVER`, else `verify()` → expired 410, unsigned/tampered 403.
- `/inbox` list and `/login` via form_login on `main` (provider `approvers`, user `approver`, `APPROVER_PASSWORD` from `.env.local`, written by `make install`). `/mcp/` firewall unchanged.
- symfony/form **is** used, for the dynamic elicitation form (decisions/023), not for login.
- Mailer: `MAILER_DSN=null://null` default, Mailpit DSN documented; `message_bus: false` in test.

## Consequences

- Tests: anonymous `/inbox` → 302 /login; login works; valid signed item 200, expired 410, tampered 403; submit dispatches `ResumePendingInput`; email contains the signed link.
- No library bug.
