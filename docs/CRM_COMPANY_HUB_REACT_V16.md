# CashERP CRM + Company Hub React Release (V16)

## Purpose

V16 rebuilds the weak CRM landing experience around CashERP's existing contacts, leads, follow-ups, proposals, campaigns and subscription architecture. It also adds a separate Company Hub for internal communication. Company Hub is not HRM, HMS, customer CRM, hotel operations or property operations; it links to those modules without copying their sensitive records.

The supplied Perfex CRM archive was audited only for common workflow ideas. V16 is original CashERP Laravel/React implementation and does not copy Perfex source, assets, templates, branding or wording.

## What users receive

### CRM workspace

- Company-scoped pipelines and configurable stages.
- Kanban-style opportunity view with owner, contact, value, currency, expected close date and source.
- Default New, Qualified, Proposal, Negotiation, Won and Lost stages.
- Controlled stage movement with won/lost state and retained stage history.
- Lost-reason capture.
- Own-versus-all assignment rules are enforced by the backend; own-only users cannot reassign opportunities or activities to another employee.
- Opportunity create, edit and soft-archive controls.
- Calls, emails, meetings, tasks and communication notes on an opportunity.
- Next-action queue, due/overdue display and completion outcome.
- Scheduled database reminders every five minutes.
- Open value, weighted forecast, open opportunity, overdue action and won metrics based on real records.
- Existing CashERP leads, contacts, proposals, follow-ups, campaigns, reports and call logs remain linked; no duplicate lead/customer table was introduced.
- Optional Super Admin SaaS capacity plan for active opportunities. It remains inactive until the Super Admin deliberately prices/activates it.

### Company Hub / Intranet

- Company-wide, location, department, role and named-user audiences.
- Open, private and announcement channels with private membership.
- Discussion, announcement and recognition posts.
- Normal, important and urgent priority.
- Pinned updates controlled by announcement publishers.
- Read tracking and separate acknowledgement tracking.
- Comments with company-scoped authorization.
- Database notifications and optional email notification for announcements.
- Knowledge articles, policies, documents and templates.
- Private non-web-addressable file storage and permission-checked downloads.
- Draft/published/archived resource status, review dates and immutable revision chain.
- Internal calendar for meetings, training and internal deadlines.
- Active-company staff directory.
- Company settings for comments, retention guidance, attachment size and announcement email.
- Audit events for content, acknowledgement, downloads, channels, events and setting changes.
- Existing CashERP Project tasks are linked when that module and subscription are enabled. Company Hub does not create a second competing task ledger.
- Optional Super Admin SaaS usage plan for published posts per month. It remains inactive until deliberately priced/activated.

## React frontage and visual system

The CRM workspace and every new Company Hub surface use React 18 and a Laravel JSON backend. They are built from:

- `resources/js/casherp-workspaces-react.jsx`
- `public/casherp-workspace.html`
- `public/css/casherp-workspaces.css`
- compiled `public/js/casherp-workspaces-react.js`

The shared workspace visual system supplies responsive layouts, accessible focus states, reduced-motion behavior, cards, feedback banners, modals, forms, metrics, timelines and pipeline columns. `public/css/casherp-global-modern.css` also improves legacy CashERP screens without changing their business logic.

V16 does not claim that every historical Blade screen in CashERP has already been converted. Removing those templates before equivalent React routes, APIs and acceptance tests exist would break the ERP. CRM workspace and Company Hub are the first complete React surfaces; other modules should be migrated in bounded releases using the same shell and tokens.

## Multi-company and data boundaries

- Every new row contains `business_id`.
- All reads and writes derive the company from the authenticated session, never from an editable request field.
- User, role, location, department, channel, pipeline, stage, contact and opportunity IDs are revalidated against the active company.
- Company switching changes context; it never copies CRM or intranet records.
- Private-channel membership is checked before reading or posting.
- Resource files are stored under `storage/app/private/company-hub`, not under public uploads.
- Staff collaboration never copies payroll, disciplinary, guest, tenant, lease, room, security-deposit or payment details into Company Hub.

## Default availability

CRM and Company Hub are enabled by default for the first six industry profiles while remaining company-toggleable:

1. General Business & Trading
2. Restaurant, Cafe & Fast Food
3. Hotel, Lodge & Guest House
4. Hotel / Lodge with Restaurant
5. Property Management & Rentals / Real Estate
6. Professional Services

Explicit existing company feature decisions are preserved. The migration inserts a business default only where no business-feature row already exists.

## Permissions

CRM:

- `crm.workspace.view`
- `crm.pipeline.manage`
- `crm.opportunity.manage`
- `crm.activity.manage`
- `crm.reports.view`

Company Hub:

- `company_hub.view`
- `company_hub.post`
- `company_hub.comment`
- `company_hub.publish_announcements`
- `company_hub.manage_channels`
- `company_hub.manage_knowledge`
- `company_hub.manage_documents`
- `company_hub.manage_events`
- `company_hub.manage_settings`
- `company_hub.view_acknowledgements`
- `company_hub.moderate`

The migration grants these to company Admin roles. Company administrators can assign the granular abilities to other roles through the existing dynamic role-permission screen.

## Deployment

1. Back up files and database. Keep `app.casherp.com` intact until `www.casherp.com` acceptance is complete.
2. Apply the cumulative V16 overlay to the authoritative CashERP root. Do not stack V15 after V16.
3. Confirm production uses PHP 8.0+ and install the locked Composer dependencies.
4. Install the locked frontend dependencies and run `npm run build` (or deploy the included compiled assets after verifying them).
5. Run `php artisan migrate --force`.
6. Run any existing module migration commands required by prior cumulative releases.
7. Run `php artisan optimize:clear` and `php artisan permission:cache-reset` when available.
8. Ensure the scheduler runs every minute: `* * * * * php /absolute/path/artisan schedule:run`.
9. Ensure `storage/app/private/company-hub` is writable by PHP and is not mapped to a public URL.
10. Review CRM/Company Hub feature defaults, premium plans and non-admin role permissions before enabling users.

## Required staging acceptance

- Register or select one company in each of the six industries and verify CRM + Company Hub visibility.
- Use one login in two companies and prove contacts, opportunities, posts, channels, files, people, events and notifications never cross contexts.
- Attempt cross-company ID substitution for every mutating endpoint and confirm 403/404/422.
- Test Admin, CRM manager, sales user, announcement publisher, Hub contributor and read-only staff roles.
- Create, edit, move, win, lose and archive opportunities; verify stage history and weighted metrics.
- Create each activity type, deliver a scheduled reminder, complete it and verify the next-action date recalculates.
- Publish to every audience type and verify included/excluded users.
- Exercise private-channel membership, read tracking, acknowledgements, comments and archive behavior.
- Upload every allowed file type, reject disallowed/oversize files, download as an authorized user and deny an unauthorized user.
- Publish a resource revision and confirm the older row is archived while its file remains auditable.
- Verify database notifications, company SMTP email behavior and scheduler/queue logs.
- Test Chrome, Edge, Firefox and Safari at phone, tablet and desktop widths, including keyboard-only and reduced-motion use.

## Verification boundary

Offline checks cover PHP parsing, React bundling and source-level architecture/contracts. The patch workspace has no root `vendor` tree and no production database, mail transport, queue worker or browser-authenticated fixture. Therefore live routes, migrations and end-to-end behavior must be accepted on a backup-restorable staging environment before production traffic is switched.
