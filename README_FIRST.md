# CashERP Developer Handover — Final Cumulative V21 Overlay

## Use this package only

This ZIP is the single simplified developer handover for the CashERP work completed through 4 September 2026. It supersedes every earlier CashERP overlay (V1 through V20). Do not apply an older ZIP before or after this package.

This is a **cumulative source overlay**, not a standalone copy of the whole live website. Apply it to the compatible authoritative CashERP Laravel 9 / PHP 8 application root. It intentionally excludes `.env`, passwords and API keys, databases, customer data, uploads, logs, caches, sessions, `vendor`, `node_modules`, and Git metadata.

## What is included

- Six-industry onboarding, industry subtypes, default feature profiles, and Super Admin feature control.
- Isolated multi-company, multi-location, company switching, role permissions, and HRM access for all six industries.
- Separate Hotel Management and Human Resource Management modules.
- Restaurant, hotel/hospitality, property/real-estate, sales, accounting, procurement, CRM, Company Hub/intranet, imports, notifications, SMTP, pricing/subscriptions, DPO Pay integration, and account-closure foundations.
- Industry-specific Smart Documents with quotations, invoices, receipts, contracts, print/download/preview/share controls, hospitality accommodation/event/POS formats, and role governance.
- Distinct payment deposits and refundable security deposits, including damage deductions, refunds, alerts, property/unit/room/event source binding, and accounting separation.
- React dashboards and commercial workspaces while retaining necessary legacy Blade/PDF fallback surfaces.
- Final V21 event-document, event-deposit, location-authorization, scheduler, and deployment-readiness corrections.
- Google sign-in/sign-up through Laravel Socialite, with account-linking safeguards, an exact HTTPS callback, a secret-preserving Super Admin configuration panel, and no credentials in the package.
- A governed notification and recovery system with 34 stable event contracts, tenant policy controls, user channel preferences, active-company isolation, permission-aware recipients, in-app and email delivery auditing, bounded retries/backoff, automatic resolution, consent-based abandoned-registration reminders, and scheduled checks for the six industry profiles.

The full staging and acceptance checklist is in `docs/RELEASE_READINESS_V21.md`. Notification operations and the event matrix are in `docs/NOTIFICATION_GOVERNANCE.md`. Automated source-check evidence is in `VERIFICATION_REPORT.md`. `DELETE_MANIFEST.txt` confirms that this cumulative package requests no deletion of application files or user data. `FILE_MANIFEST.csv` provides the size and SHA-256 hash of every packaged file except the manifest itself.

## Safe installation order

1. **Do not touch `app.casherp.com`.** Keep it and its database intact as the working fallback.
2. Create a separate staging copy of the compatible CashERP installation and database.
3. Make full file and database backups and test that they can be restored.
4. Extract this ZIP into the staging Laravel application root, preserving the included paths and replacing matching source files.
5. Review `DELETE_MANIFEST.txt`. No source or data deletion is requested.
6. Install locked PHP dependencies:

   ```bash
   composer install --no-dev --optimize-autoloader
   ```

7. Run the core migrations and the installation's normal module migrations:

   ```bash
   php artisan migrate --force
   php artisan module:migrate Hms --force
   ```

   Confirm that all outstanding module migrations—not only HMS—are applied by the deployment's established Nwidart Modules procedure. The HMS migration named `2026_09_04_000008_unify_event_document_security_deposits.php` is a deliberate forward-only data repair and has a no-op rollback.

8. Build the React assets:

   ```bash
   npm ci
   npm run build
   ```

9. Clear and rebuild Laravel caches:

   ```bash
   php artisan optimize:clear
   php artisan config:cache
   php artisan view:cache
   ```

   Do **not** run `php artisan route:cache` in this baseline yet. The inherited application contains duplicate route names (booking resources, coupons and `logout`), and Laravel correctly refuses to serialize them. Resolve that compatibility debt and rerun route/link regression tests before enabling route caching.

10. Run the release checks:

    ```bash
    php artisan casherp:release-audit
    php artisan migrate:status
    php artisan schedule:list
    ```

    Do not promote the release while `casherp:release-audit` reports blockers.

11. Configure the cPanel cron to run `php artisan schedule:run` every minute. Configure a supervised queue worker whenever `QUEUE_CONNECTION` is not `sync`.
12. Configure tenant SMTP, then run the notification auditor manually once and verify its schedule:

    ```bash
    php artisan casherp:audit-notifications --dry-run
    php artisan casherp:audit-notifications
    php artisan schedule:list
    ```

    Keep the 15-minute scheduled auditor protected by Laravel's `withoutOverlapping()` and `onOneServer()` behavior. See `docs/NOTIFICATION_GOVERNANCE.md` for worker, policy and delivery acceptance.
13. Perform every authenticated, role-based and cross-company acceptance test in `docs/RELEASE_READINESS_V21.md` before promoting staging.

## Main-domain configuration

The intended final application origin is `https://www.casherp.com`. Configure secrets only in the server environment, never in source:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.casherp.com
ASSET_URL=https://www.casherp.com
CANONICAL_URL=https://www.casherp.com
CORS_ALLOWED_ORIGINS=https://www.casherp.com
SESSION_DOMAIN=www.casherp.com
SESSION_SECURE_COOKIE=true
GOOGLE_SOCIAL_LOGIN_ENABLED=false
GOOGLE_OAUTH_CLIENT_ID=
GOOGLE_OAUTH_CLIENT_SECRET=
GOOGLE_OAUTH_REDIRECT_URI=https://www.casherp.com/auth/google/callback
```

Remove legacy redirect rules only from the new `www.casherp.com` staging/production target after acceptance. Do not delete or overwrite the existing `app.casherp.com` installation.

For Google login, register `https://www.casherp.com/auth/google/callback` as an authorised redirect URI in the Google Cloud OAuth client, add the credentials through the protected server environment or Super Admin settings, clear/cache configuration, and enable the feature only after staging callback and account-linking tests pass. A blank secret in Super Admin does not erase the existing secret unless the explicit clear option is selected.

## Mandatory acceptance before production

At minimum, verify:

- registration and login for all six industries, subtypes, default features, HRM, multi-company switching, and multiple locations;
- owner, restricted staff, department, location, finance, manager, and Super Admin authorization—including cross-company denial;
- quotations, invoices, receipts, contracts, preview, A4/POS print, download, share, draft editing, issuing, payments, reversals, and voiding;
- hotel stays, events, restaurant POS, property leases/units, payment deposits, refundable security deposits, damages, refunds, and unsettled-deposit alerts;
- imports, payroll/HRM, CRM/Company Hub, procurement approvals, accounting entries, reports, and notifications;
- notification permissions and active-company isolation for owner, department, location and restricted staff roles; personal preferences; tenant policies; critical in-app lock; retry/backoff; automatic resolution; registration-reminder opt-out; and real SMTP/queued delivery using server credentials;
- Google login, Google-assisted sign-up, verified-email account linking, disabled/missing-credential behavior, cancellation/error handling, and cross-account safety;
- DPO `CreateToken`, hosted test payment, callback/return, and `verifyToken` using protected test credentials;
- current desktop/mobile browsers, storage permissions, PDF fonts, HTTPS/TLS, logs, failed jobs, scheduler, queues, backups, and rollback.

## Honest release boundary

The package is source-verified and ready for developer integration/staging. It is **not yet production-certified** because local tests cannot prove the target cPanel environment, live database migrations, DNS/TLS, cron and workers, real SMTP delivery, Google/DPO network callbacks, printers/PDF fonts, or authenticated production behavior. Composer's locked dependency tree also currently reports security advisories inherited from the compatible Laravel 9 baseline, and route caching is blocked by inherited duplicate names; those issues must be corrected and regression-tested before production approval. See `VERIFICATION_REPORT.md`.

CashERP is also not yet a literal zero-Blade application. Major commercial dashboards/workspaces are React, but controlled Blade views and server-generated PDF/receipt fallbacks remain. A complete page-by-page Blade-to-React migration is separate architectural work and must not be claimed as finished by this overlay.
