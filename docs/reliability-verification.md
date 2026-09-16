# Reliability verification

## Safety and running tests

No production database was accessed during this verification. No fresh migration,
seeder, or live restore was run. Application migrations only initialized empty,
disposable SQLite databases containing synthetic records.

- `composer test`: feature tests force SQLite `:memory:` before application boot,
  ignore cached production configuration, and run ordinary migrations only.
- `npm run test:offline`: queue persistence, failed retries, dependencies, account
  isolation, exact-filter cache lookup, and friendly error messages.
- `npm run build` then `npm run test:browser`: Playwright launches installed Edge
  against a loopback PHP server and a uniquely named temporary SQLite database.
  Port 8137 must be free. The test removes its database and stops its server.
  Test login routes exist only in the test server router, not application routes.

Browser coverage includes receipt creation, expense submission/approval,
disbursement creation/submission/approval/posting, XLSX download, pagination,
filters, import preview/confirmation, cached and uncached offline navigation,
and report access for every role. Backend coverage additionally checks denied
routes for every role, calculations, full report rows, Excel formula totals,
offline replay/conflicts, restore fidelity, and exception logging.

## Backup format

New ZIP backups use format version 2: table schema, JSON-lines records, row counts,
and SHA-256 checksums. This preserves nulls, line breaks, identifiers, password
hashes, and relationships. Export reads tables in one transaction. Runtime
sessions, password reset tokens, caches, and job queues are excluded.
Temporary export files are removed after delivery. Store downloaded backups in
restricted storage: they contain account credentials in hashed form and private
financial information. Application files, environment secrets, and uploaded
files are not included; retain those separately.

The automated restore drill rebuilds an empty in-memory database from the archive
and compares every exported row, verifies relationships, and checks account
password hashes. It refuses persistent targets. It does not restore live data.
Older CSV-only archives are not supported by this verifier.

## Verified limits and remaining operational checks

SQLite tests do not establish MySQL locking or concurrent transaction behavior.
A MySQL backup restore drill on a separate empty MySQL instance remains necessary
before declaring production disaster recovery proven. The archive includes MySQL
table definitions, but the in-memory verifier intentionally rejects MySQL archives.
No production restore endpoint or automatic restore command is exposed.

Production log delivery in Laravel Cloud was not inspected. Local tests confirm
exceptions still reach Laravel's logger while users receive safe messages.
Request IDs correlate responses and logs; OTP codes are no longer logged. Verify
the deployed logging channel, log retention and alert delivery in Cloud.

Generated reports and exports share budget filters for disbursement details and
no longer truncate generated detail tables at 25 records. Cash summary values
remain fiscal-period totals, while date-filtered transaction tables show the
selected dates. Reports named Income vs Receipts, Fund Balance and Closing still
share the existing report sections; these tests do not certify accounting policy
or an immutable year-end reporting snapshot.

Offline cached navigation is available within an authenticated, open app. A cold
offline reload shows the offline landing page instead of replaying another user's
cached authenticated HTML. Caches are separated by account and complete URL;
missing filtered pages do not substitute unfiltered results.

Offline action IDs are stored with their completed results in the existing audit
trail within the same transaction as the mutation. Do not purge `offline_synced`
audit entries while clients may still retry those actions. MySQL concurrent
replays should additionally be exercised in the isolated staging environment.
