# CashERP V21 release-readiness handover

## Purpose

V21 is a cumulative source overlay. It includes V1 through V20 and adds the
final source-level release hardening completed on 4 September 2026. It must be
deployed to a staging copy of the authoritative CashERP installation before
promotion to `www.casherp.com`.

This overlay does not contain credentials, a database export, user uploads,
`vendor`, `node_modules`, `.env`, cache files, or live server data.

## V21 corrections

1. A hotel event booking is now an accepted persisted Smart Document source in
   both the controller and document service. The earlier mismatch could allow
   the create screen to open but reject the save operation.
2. A quotation, reservation contract, A4 invoice, and other documents derived
   from the same HMS event now share one `hms_event` refundable security-deposit
   ledger. Standalone property/location event documents continue to use their
   own `event_document` context.
3. Hotel-event documents cannot substitute another venue, company location,
   customer, start time, or end time while retaining the original event source.
4. Event/venue-hire documents require service start and end dates.
5. Draft source and parent-document links are immutable. A correction that
   changes the underlying transaction must be created as a new document.
6. Hotel folio and event sources are checked against the active company and the
   signed-in user's permitted locations.
7. Refund approval, refund payment, entry reversal, closure checks, alerts, and
   document views support both standalone event deposits and HMS event deposits.
8. An idempotent HMS migration consolidates previously created per-document
   event deposits. It moves existing ledger entries, retains the highest
   requirement, recalculates status, and does not recreate monetary entries.
9. Laravel's standard `APP_ENV=production` now enables the same recurring ERP
   schedules as the legacy `APP_ENV=live` value.
10. A read-only, secret-safe deployment audit is available as
    `php artisan casherp:release-audit` or
    `php artisan casherp:release-audit --json`.

## Safe staging procedure

1. Keep `app.casherp.com` and its database intact. Do not overwrite or delete
   that installation during acceptance.
2. Back up the target staging files and database and verify that both backups
   can be restored.
3. Confirm the target is the compatible Laravel 9 / PHP 8 CashERP installation.
4. Extract the V21 overlay into the staging application root, preserving paths.
5. Review the delete manifest. V21 does not request deletion of application
   source or user data.
6. Install the authoritative application's PHP dependencies using its lock file.
7. Run all core and module migrations. The HMS migration
   `2026_09_04_000008_unify_event_document_security_deposits.php` is a deliberate
   forward data repair; its rollback is a no-op because splitting a real deposit
   ledger into arbitrary document ledgers would be unsafe.
8. Build the React assets with `npm ci` followed by `npm run build`, or upload
   the verified compiled assets supplied in the overlay.
9. Clear and rebuild caches:

   ```text
   php artisan optimize:clear
   php artisan config:cache
   php artisan view:cache
   ```

   Do not enable route caching until the inherited duplicate booking, coupon
   and logout route names have been corrected and regression-tested. Laravel's
   route-cache command currently rejects that ambiguous baseline as designed.

10. Run:

    ```text
    php artisan casherp:release-audit
    php artisan migrate:status
    php artisan schedule:list
    ```

    Do not promote while the release audit reports blockers.

11. Configure a cPanel cron to execute `php artisan schedule:run` every minute.
    Configure a supervised queue worker when `QUEUE_CONNECTION` is not `sync`.
12. Verify that `APP_URL`, `ASSET_URL`, and `CANONICAL_URL` are all
    `https://www.casherp.com`, CORS permits that origin, and secure cookies are
    enabled. The existing `app.casherp.com` installation remains a separate,
    reversible fallback until acceptance is signed off.

## Required role-based acceptance

Use two different users wherever separation of duties requires it.

1. Register one company for each of the six industries and confirm its subtype,
   onboarding answers, default features, HRM availability, menus, and company
   switcher. Confirm records never cross company boundaries.
2. Confirm HMS and HRM are separate workspaces in both hotel industries.
3. Create an HMS event, then generate an event quotation, contract, and A4
   invoice. Confirm all retain the same event/customer/venue/dates and show one
   refundable security-deposit ledger.
4. Receive a security deposit and confirm invoice revenue and paid totals do not
   change. Record damage, issue the linked damage invoice, request a refund,
   approve it as another user, pay it, and confirm closure is blocked until all
   balances are settled or explicitly waived.
5. Confirm a payment deposit reduces the event/booking/rent balance and remains
   separate from the refundable security deposit.
6. Create four-night accommodation documents and verify arrival, departure,
   nights, folio charges, payments, A4 output, and quotation payment suppression.
7. From hotel restaurant POS, test POS receipt and A4 sales invoice output,
   kitchen routing, post-to-room/event, register reconciliation, and location
   permissions.
8. For property management, test multiple locations, portfolios/properties,
   units, tenant access, lease/rental invoice, payment advance, security deposit,
   damage settlement, notices, maintenance, documents, and role restrictions.
9. For every transactional document role, verify view, preview, download, print,
   share, edit-draft, issue, payment, reversal, and void permissions separately.
10. Test the import workflow with preview, validation errors, duplicate policy,
    commit, rollback, audit events, file retention, and cross-company denial.
11. Test registration, login, password recovery, system SMTP, tenant SMTP, queued
    notifications, CRM reminders, and company-account closure recovery.
12. Complete DPO `CreateToken`, hosted test checkout, callback/return, and
    `verifyToken` acceptance using test credentials. Never place live credentials
    in source control or the overlay.
13. Test current Chrome, Edge, Firefox, Safari/mobile Safari, Android Chrome,
    keyboard navigation, focus visibility, responsive layouts, and print output.
14. Review logs, failed jobs, scheduler output, database indexes, backup results,
    HTTPS headers, storage permissions, and restore procedure before promotion.

## Certification boundary

The local checks prove source syntax, static architecture contracts, Blade
compilation covered by the unit suite, and React bundle compilation. They do not
prove the target server's database migration, web-server configuration, DNS,
TLS, external cron/worker processes, SMTP delivery, DPO network behavior, or
authenticated browser workflows. Those items require the staging acceptance
above.

The application still contains legacy Blade views as server-rendered/fallback
surfaces. V21 expands and compiles the React workspaces, but it is not truthful
to call the entire historic ERP a zero-Blade React application. Removing every
Blade surface is a separate full frontend migration that requires page-by-page
route/API/browser acceptance and must not be hidden inside a deployment patch.
