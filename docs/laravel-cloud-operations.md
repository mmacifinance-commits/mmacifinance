# Existing Laravel Cloud deployment

These changes are additive. Deploy them to the existing application; do not seed or rebuild its database.

## Before deploying

1. Take a Cloud database snapshot/backup and confirm the recovery options for your database plan.
2. Attach a **private** Laravel Object Storage bucket. Backups contain financial records and password hashes: never use a public bucket. The application includes the S3 filesystem adapter. The `s3` disk reads Cloud's injected AWS variables; if the app already uses a public bucket, attach a separate private bucket and configure a separate disk for backups instead of reusing it.
3. Set these environment variables in Cloud:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-EXISTING-DOMAIN
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
BACKUP_DISK=s3
BACKUP_RETENTION_DAYS=30
```

Keep the current APP_KEY and database/mail credentials. `CACHE_STORE=redis` is also suitable when an attached shared Redis/Valkey service is configured. Do not use an instance-local or array cache for rate limits or scheduler locks across replicas.

4. Enable **Scheduler** on the environment's App cluster and save. The schedule runs daily at **02:00 application time** (UTC unless configured otherwise), with overlap prevention and a shared single-server lock. Redeploy after changing environment variables or scheduler settings.
5. Build with `composer install --no-dev --prefer-dist --optimize-autoloader`, `npm ci`, `npm run build`, and `php artisan optimize`. Keep `php artisan migrate --force` in Deploy Commands. Do **not** run `migrate:fresh` or `db:seed`: the demo seeder intentionally refuses production.

## After deploying

- Run `php artisan system:backup` from Cloud Commands. It verifies the archive, uploads it privately, downloads the stored bytes for checksum comparison, and only then applies retention. Check that the ZIP appears in `scheduled-backups/` in the private bucket. Failed runs return a nonzero exit code and scheduled failures are logged; configure Cloud monitoring/log alerts for this message.
- Check `php artisan schedule:list` includes `system:backup`, and confirm a scheduled run actually completes. Local archives are temporary and are removed after upload; Cloud's instance filesystem is not the backup destination.
- Confirm HTTPS on the existing domain and secure session cookies. Cloud manages TLS certificates and edge DDoS protection; no Nginx configuration is needed in this repository. Keep Cloud's network protections enabled. Application login/OTP rate limits are additional protection, not a replacement for the edge service.
- Existing users with the seeded password `password` must use password reset. Demo account lists and development OTP displays are limited to local development. Production ignores APP_DEBUG=true. OTP values and session IDs are no longer written by the authentication controller.

## Restore drill and recovery

The ZIP contains database tables, their schema, row counts and SHA-256 hashes. It excludes transient cache, sessions, queues and reset tokens. It does not include application source, APP_KEY, mail secrets or external uploaded files; preserve these separately through source control, Cloud secrets and object-storage recovery policies.

1. Download a trusted archive from the private bucket to a secure machine with this application and matching dependencies.
2. Run `php artisan system:backup-verify /absolute/path/to/archive.zip` to validate checksums and row counts.
3. For MySQL, provision a disposable **empty** database with a name ending in `_restore_drill`, on the same MySQL version as production. Configure `RESTORE_DB_HOST`, `RESTORE_DB_PORT`, `RESTORE_DB_DATABASE`, `RESTORE_DB_USERNAME`, and `RESTORE_DB_PASSWORD` locally. Use a user restricted to that disposable database. Never use the live database as the restore target.
4. Run `php artisan system:backup-verify /absolute/path/to/archive.zip --restore-connection=backup_restore`. It creates tables and restores rows, then checks counts and foreign keys. Existing/nonempty targets and the application's database name are refused. MySQL DDL cannot be rolled back: if the drill fails, discard the disposable database and investigate before retrying. Only restore trusted archives because their schema is executable SQL.
5. In a real recovery, validate the restored database in a separate Cloud environment, compare financial totals and audit history, invalidate restored sessions/OTP credentials, and then plan the live database cutover. Prefer Cloud's native database restore for emergency recovery. This command deliberately does not overwrite production.

The automated suite exercises a full SQLite restore into an isolated in-memory database, corruption detection and private-storage upload/read-back. Run the MySQL drill against the deployed database version before relying on it for production recovery.

## Payment controls and reconciliation

- New/edited/imported payments and posting recheck the linked expense's balance, including other pending commitments. Matching expense/payee/date/method/amount payments without a reference are blocked. A unique payment reference distinguishes legitimate equal installments; reusing a reference is blocked by a database unique constraint. Existing historical duplicates are not silently deleted.
- The Reconciliation page records a dated comparison of all actual receipts and posted payments against counted cash and combined bank statements. Forecast income is excluded. Enter the opening balance from before the first recorded transaction, deposits in transit and outstanding payments. Explain any difference. Entries retain their original totals and the acting user's name/role. They do not automatically alter the ledger or prove transaction-by-transaction bank matching.

References: [Cloud deployments and environment variables](https://laravel.com/cloud/docs/environments), [Cloud scheduler](https://laravel.com/cloud/docs/scheduled-tasks), [private object storage](https://laravel.com/cloud/docs/resources/object-storage), [Cloud security and TLS](https://marketing.cloud.laravel.com/).

Dependency check: production Composer advisories were resolved with compatible updates, and Axios was updated. npm still reports a low-severity esbuild Windows development-server advisory in Vite's supported dependency range; use the compiled production assets on Cloud, never the Vite development server. A forced out-of-range esbuild override was not introduced.
