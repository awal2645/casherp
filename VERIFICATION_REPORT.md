# CashERP Cumulative V21 Merge Verification Report

Date: 2026-09-04

## Current automated results

- Notification governance architecture: 7 tests, 91 assertions, no failures.
- Complete cumulative unit/architecture suite excluding the baseline database-coupled example test: 136 tests, 1,434 assertions, no failures.
- Notification/Google/domain/backend target: 19 PHP files checked with `php -l`, no syntax failures. The lint pass caught and corrected a duplicate `$casts` declaration before packaging.
- Full Laravel route registry booted and parsed: 1,450 routes.
- Notification/recovery routes discovered: 16 total, including 7 new in-app preference/policy/read endpoints, a signed registration-resume endpoint and the signed opt-out endpoint.
- Catalog consistency: 34/34 events are handled by the scheduled auditor; zero non-platform event categories are missing from the six-industry mapping.
- Laravel schedule booted and contains the 15-minute `casherp:audit-notifications` task, 5-minute CRM reminders and 10-minute HMS guest-message dispatch.
- Isolated SQLite notification migration: completed; all five governance tables exist; `notification_deliveries` includes `next_retry_at`; dry-run auditor completed with no records.
- React SMTP settings and cumulative workspace bundles compiled successfully with Node.js 24.19.0, pnpm 11.19.0 and esbuild.
- Compiled workspaces bundle: 261,451 bytes.
- Compiled SMTP settings bundle: 159,287 bytes.
- Production JavaScript dependency audit: no known vulnerabilities.
- Composer schema validation: passed with exact-version-constraint warnings.
- Locked Composer install dry-run: passed after ignoring only locally unavailable `ext-gd` and `ext-sodium`; these extensions remain mandatory on the server.
- Supplied DPO test company-token strings found in deliverable source: zero.
- `.env`, `vendor`, `node_modules`, logs and runtime cache paths found in the deliverable candidate: zero.

## Notification contracts covered

- Five-table governance schema for recovery consent, tenant policies, user preferences, canonical events and delivery audit.
- 34 stable event contracts covering abandoned registration, trial/subscription lifecycle, overdue sales/purchases, inventory, procurement, restaurant, hospitality, property, refundable deposits, HRM, CRM, projects, Company Hub and failed imports.
- Exact six-industry category profiles; HRM remains available to all six, while HMS remains distinct from HRM.
- Active-company notification visibility and explicit platform-only handling for legacy notifications without a company ID.
- Permission-aware recipients plus company owner governance; tenant policy and personal preference controls cannot suppress critical in-app alerts.
- SHA-256 event deduplication, maximum five delivery attempts, exponential backoff, `next_retry_at`, delivery health and safe error capture.
- Automatic resolution of cleared conditions and automatic read state for the linked in-app alert.
- Company SMTP configuration used for operational email delivery.
- Consent-based abandoned-registration recovery, two-reminder cap, signed opt-out, registration completion and retention cleanup.
- Existing functional module notifications retained and enriched for company isolation instead of being duplicated.

## Other cumulative V21 contracts covered

- Google Socialite dependency and lock, guarded redirect/callback routes, social-account persistence, verified-email account linking, login/sign-up entry points, secret-preserving Super Admin settings and exact main-domain callback configuration.
- HMS event sources persist through authorization and service allow-lists.
- One real HMS event resolves to one refundable deposit ledger across quotation, contract, invoice and other A4 outputs.
- Standalone property/location event documents retain their deposit context.
- Hotel event venue, branch, customer and dates cannot diverge from the source.
- Draft source and parent links are immutable; event dates are required; closure uses the resolved deposit context.
- Folio/event sources enforce active-company and permitted-location scope.
- Standard `production` and legacy `live` environments both schedule core jobs.
- The release audit is read-only, secret-safe and checks real CashERP tables and industry/profile/document/runtime prerequisites.

## Known baseline findings that block production certification

- `composer audit --locked` reports **103 advisories affecting 25 packages** in the compatible locked dependency tree and one abandoned package (`spatie/data-transfer-object`). These are inherited baseline dependency risks, but they remain real and must be remediated through a controlled dependency-upgrade and regression-test track before production approval.
- Laravel reports 15 duplicate route names in the compatible baseline: hotel/restaurant booking resources, coupon resources and logout. Route boot succeeds, but `php artisan route:cache` fails first on duplicate `logout`; the cache was cleared after this check. Inventory and rename these routes in a dedicated backward-compatible cleanup, then run full link/API regression tests before enabling route caching or approving production.
- The default `Tests\Unit\ExampleTest` boots application services against the configured MySQL database and cannot run in this local environment because no matching database is available. It is excluded from the 136-test result; this does not hide a notification test failure.
- Local PHP lacks `ext-gd` and `ext-sodium`; locked dependencies were verified with those two requirements ignored only for local source testing. The deployment must provide both extensions.
- Composer package discovery against a fresh SQLite database encounters a legacy CRM provider dependency on the old `system` table. The isolated migration test supplied only that disposable bootstrap table. Production/staging must use the complete compatible database and run the full migration sequence.

## Environment limitations

No live server files, database, DNS, TLS, cPanel cron, queue worker, real SMTP message, Google OAuth callback, DPO transaction/callback, authenticated role session, PDF/font/printer path or cross-browser device was changed or certified by these checks. Source verification and an isolated migration are not production certification. Follow `docs/RELEASE_READINESS_V21.md` and `docs/NOTIFICATION_GOVERNANCE.md` on a backed-up staging copy before production promotion. The existing `app.casherp.com` installation was not modified.
