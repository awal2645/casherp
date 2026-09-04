# CashERP React Home Dashboard (V17)

## Purpose

V17 makes the authenticated `/home` experience a React workspace backed by a company-scoped Laravel JSON endpoint. It extends the visual system introduced for CRM and Company Hub while leaving the operational controllers for POS, sales, purchasing, inventory, accounting, HRM, Hotel Management, Restaurant Operations and Property Management unchanged.

The previous Blade dashboard remains available at `/home?legacy=1` as a controlled rollback and comparison surface. This is intentional until every third-party and module-provided legacy widget has an accepted React equivalent.

## User experience

- Responsive CashERP-branded hero and active-industry context.
- Active-company display and authorised company switching.
- Permission-aware top navigation for Home, CRM and Company Hub.
- Reporting periods for today, 7 days, 30 days, month, quarter, year or a validated custom period.
- A location filter containing only the user’s permitted active-company locations.
- Real net sales, sales outstanding, purchases, supplier balances and expenses.
- A clearly labelled `Sales less due & expenses` operating indicator; it is not presented as a bank or accounting cash balance.
- Real net-sales trend rendered without an additional charting dependency.
- Permission-aware action centre for unsettled transactions, low stock, security deposits and CRM activities.
- Industry-, feature-, package- and role-aware workspace shortcuts.
- Recent real transactions with document reference, party, location, payment status and amount.
- Existing industry onboarding progress and links.
- In-app notification drawer with individual and mark-all-read actions.
- Per-user, per-company date, location, density, accent and section-visibility preferences.
- Keyboard focus, modal focus containment, responsive behavior and reduced-motion support.

## Data and permission boundaries

- The company is derived from the authenticated session and checked with `canAccessBusiness`; no request field selects the company.
- Location IDs are validated against the active company and again against the user’s permitted locations.
- Company-wide financial totals and recent transaction values are omitted unless the user has `dashboard.data`.
- Financial attention counts are also omitted for roles without dashboard financial access.
- Low-stock counts require product view access.
- Refundable security-deposit counts require the relevant Hotel or Property permission.
- CRM action counts respect own-versus-all schedule permission.
- Quick actions are filtered by active industry, feature profile and role permission.
- Company switching changes context and reloads data; it never copies records.
- Dashboard preferences use a `(business_id, user_id)` unique boundary.

## Industry behavior

- General Business and Professional Services prioritise selling, documents, customers, products, purchasing, CRM and reports as permitted.
- Restaurant, Café & Fast Food adds Restaurant Operations and Kitchen Board controls.
- Hotel, Lodge & Guest House adds Hotel Front Desk, Reservations and Housekeeping controls.
- Hotel / Lodge with Restaurant presents Hotel and Restaurant workspaces separately.
- Property Management & Rentals presents the property portfolio, rents/leases and maintenance workspaces.
- Human Resource Management remains a separate workspace and is offered when the company’s HRM feature is enabled.

## API routes

- `GET /home/workspace`
- `PUT /home/workspace/preferences`
- `GET /notifications/in-app`
- `PATCH /notifications/in-app/{notification}/read`
- `PATCH /notifications/in-app/read-all`

All routes execute inside the existing authenticated, session, language, timezone, sidebar and login-status middleware group.

## Deployment

1. Back up the files and database and deploy the cumulative V17 overlay to staging.
2. Install the Composer and frontend dependencies that match the authoritative lock files.
3. Run `npm run build` or verify the included compiled bundle.
4. Run `php artisan migrate --force` to create `user_dashboard_preferences`.
5. Run `php artisan optimize:clear` and reset the permission cache.
6. Confirm `/home`, `/home/workspace` and `/home?legacy=1` while authenticated.
7. Do not remove `app.casherp.com` during acceptance of `www.casherp.com`.

## Required staging acceptance

- Test one owner/admin and one restricted employee in each of the six industries.
- Test one login across two companies and confirm metrics, locations, shortcuts, preferences and notifications change without record leakage.
- Substitute another company’s location ID in the dashboard and preference endpoints and confirm denial.
- Compare financial metrics against the legacy dashboard and source reports for today, month, year and one custom range.
- Confirm a role without `dashboard.data` cannot receive financial values or financial attention counts in JSON.
- Verify Hotel Management and Human Resource Management remain separate, and that Hotel-with-Restaurant shows separate Hotel and Restaurant workspaces.
- Verify notification read state, preference persistence, browser back/forward navigation and the legacy rollback URL.
- Test keyboard-only use, reduced motion, Chrome, Edge, Firefox and Safari at phone, tablet and desktop widths.

## Verification boundary

Offline checks cover PHP parsing, source contracts, React compilation and the cumulative unit/architecture suite. The patch root does not contain its own Composer vendor tree or a production database, mail transport, queue, scheduler or authenticated browser fixture. Database execution, route boot, production-volume query plans and real browser workflows therefore require staging acceptance.
