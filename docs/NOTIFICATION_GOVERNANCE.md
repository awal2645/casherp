# CashERP Notification Governance and Operations

Date: 2026-09-04

## Purpose and compatibility rule

CashERP now has one governance layer for scheduled operational risks while retaining event-specific notifications that already worked. Existing asset, HRM, project, subscription, SMTP and module notifications are not replaced merely for the sake of replacement. Their payloads are enriched with company, category, severity and event metadata where this is required for isolation and a consistent Notification Centre.

This design keeps Hotel Management (`HMS`) and Human Resource Management (`HRM`) separate. HRM is relevant to every one of the six selectable industries. Industry belongs to a company, not a user, and notification visibility follows the active company selected by the user.

## Delivery architecture

The governance migration creates five tables:

- `registration_intents`: consent-based registration recovery, fingerprint deduplication, reminder count, opt-out and completion state.
- `business_notification_settings`: tenant-level enable/disable and channel policy by stable event key.
- `user_notification_preferences`: personal in-app/email choices by category.
- `notification_events`: canonical event, company, subject, fingerprint, severity, payload and resolution state.
- `notification_deliveries`: recipient/channel status, attempt count, sent/failed timestamps, next retry time and safe error summary.

`NotificationOrchestratorService` applies the following sequence:

1. Validate the event against the central catalog.
2. Confirm that its category is relevant to the active company's industry profile.
3. Generate a stable SHA-256 fingerprint and suppress duplicate open alerts.
4. Apply company policy and user preferences. Critical in-app alerts cannot be silently disabled.
5. Select company-authorised recipients using event permissions, company ownership and valid company access.
6. Create an auditable delivery record for each recipient and channel.
7. Retry transient failures with exponential backoff, stopping after five attempts.
8. Resolve cleared conditions automatically and mark the linked in-app notification read.

Email uses the recipient company's SMTP configuration through `BusinessMailConfigurationService`. Secrets are not included in notification payloads, logs or this package.

## Governed event catalog

The central catalog contains 34 stable event keys:

| Area | Events |
|---|---|
| Registration | abandoned registration with consent, no more than two recovery emails, and a signed stop-reminders link |
| Trial and subscription | trial expiring/expired; subscription expiring/expired; payment failed |
| Sales and purchasing | overdue sales invoice; overdue purchase payment |
| Inventory and procurement | low stock; overdue approval; overdue purchase delivery |
| Restaurant | delayed order; overdue waiter request; pending cash-register reconciliation; reservation needing attention |
| Hospitality | upcoming check-in; overdue arrival; overdue checkout; overdue housekeeping; expiring group hold |
| Property | overdue rent; expiring/expired lease; overdue maintenance; upcoming viewing |
| Refundable deposits | pending security-deposit settlement; overdue refund |
| HRM | expiring employment contract; pending leave approval; payroll action required |
| Shared work | overdue CRM activity; overdue project task; overdue Company Hub acknowledgement |
| Data operations | failed import |

Existing immediate event notifications remain available for asset maintenance assignments, HRM task/document/comment/message/leave/payroll actions, project assignment/comments, subscription expiry and Super Admin communication. These were company-scoped rather than needlessly rebuilt.

## Six-industry relevance

| Selectable industry | Default operational categories |
|---|---|
| General Business & Trading | subscriptions, sales, purchasing, inventory, procurement, assets, HRM, CRM, projects, Company Hub, imports/system |
| Restaurant, Cafe & Fast Food | subscriptions, sales, purchasing, inventory, procurement, restaurant, assets, HRM, CRM, Company Hub, imports/system |
| Hotel, Lodge & Guest House | subscriptions, sales, purchasing, procurement, hospitality, refundable deposits, assets, HRM, CRM, Company Hub, imports/system |
| Hotel / Lodge with Restaurant | hotel categories plus restaurant and inventory operations; HRM remains independent |
| Property Management & Rentals | subscriptions, sales, purchasing, procurement, property, refundable deposits, assets, HRM, CRM, projects, Company Hub, imports/system |
| Professional Services | subscriptions, sales, purchasing, procurement, assets, HRM, CRM, projects, Company Hub, imports/system |

Super Admin defines the default feature profile for each industry. Company owners may tune allowed notification events and channels for their company, subject to permissions and mandatory critical in-app alerts. Users may tune personal category preferences without gaining access to data or events they are not authorised to see.

## Registration recovery safeguards

- Recovery is opt-in; registration forms must not preselect marketing/reminder consent.
- The intent stores only the minimum fields required to resume registration safely.
- Fingerprints prevent repeated records from the same submitted identity/context.
- At most two reminders are sent; a signed, expiring link lets the prospect stop reminders without signing in.
- A completed registration closes the intent immediately.
- Incomplete and completed records are pruned after their configured retention periods.
- This workflow is transactional recovery, not permission to send unrelated marketing.

## Notification Centre and controls

The React workspace provides:

- active-company-only notification visibility;
- unread, category and severity filters;
- critical-unread count and clear severity labels;
- subject/action context without exposing another company's data;
- mark-one and mark-all-as-read controls;
- personal in-app/email category preferences;
- owner/Super Admin company policy matrix;
- delivery-health summary, including open critical events and failed deliveries;
- accessible focus, loading, empty and error states, plus responsive mobile layout.

Legacy header counters and dropdowns use the same active-company boundary. Historical notifications that lack a company ID are not treated as global; only explicit platform types (business-closure and Super Admin communicator notices) may appear without one.

## Scheduler and worker requirements

Add one cPanel cron entry that runs every minute from the production application root:

```bash
* * * * * /usr/local/bin/php /absolute/path/to/artisan schedule:run >> /dev/null 2>&1
```

The application schedules `casherp:audit-notifications` every 15 minutes with `withoutOverlapping()` and `onOneServer()`. Do not add a second direct cron for the same command. Confirm the PHP path and application path with the hosting provider.

When `QUEUE_CONNECTION` is not `sync`, run a supervised queue worker and a failed-job recovery procedure. Alerts must never rely on a browser request to be dispatched.

Useful release commands:

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan casherp:audit-notifications --dry-run
php artisan casherp:audit-notifications
php artisan schedule:list
php artisan queue:failed
```

## Production acceptance checklist

Do not call the system production-ready until all of these pass on an isolated staging copy:

1. Migrate a realistic copy of the database and confirm all five governance tables and indexes.
2. Run the auditor twice; the second run must not duplicate an already-open event.
3. Clear a triggering condition; the event must resolve and the corresponding in-app alert must become read.
4. Force a temporary email failure; verify delivery audit, bounded retry/backoff and no secret leakage in the stored error.
5. Confirm company SMTP delivery for at least one owner and one permission-matched staff member.
6. Switch between two companies under one login; neither company's operational notifications may leak into the other.
7. Test owner, manager, finance, front desk, housekeeping, property, restaurant, HR, CRM/project and restricted staff roles.
8. Confirm every one of the six industry profiles receives relevant events and does not receive unrelated hotel/restaurant/property events.
9. Test registration consent absent/present, completion, two-reminder cap, signed opt-out and expired/invalid link behavior.
10. Test user preferences and company policies, including that a critical in-app alert remains enabled.
11. Confirm scheduler, queue worker, failed-job monitoring, log rotation, retention cleanup, backups and restore.
12. Test desktop/mobile browsers, screen-reader labels, keyboard focus, empty/loading/error states and accurate unread counters.

## Release boundary

Static tests, an isolated SQLite migration and a successful frontend build prove source consistency; they do not prove the production database, real SMTP, cron, workers, role assignments or authenticated cross-company behavior. Complete this checklist in staging before promotion. The compatible baseline also has locked Composer security advisories documented in `VERIFICATION_REPORT.md`; resolve and regression-test those before production approval.
