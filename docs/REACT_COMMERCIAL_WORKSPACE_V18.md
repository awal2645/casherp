# CashERP React Commercial Workspace (V18)

## Purpose

V18 makes the high-frequency commercial list surfaces React-first while Laravel remains authoritative for transactions, accounting, stock, document numbering, permissions and industry rules. The unified workspace covers invoices, quotations, Smart Documents, receipts/payments and customers without merging their distinct business records.

Complex invoice, quotation and Smart Document editors remain on their proven Laravel workflows in this staged migration. React actions open those secured routes; they do not reproduce tax, inventory, payment, security-deposit or document-transition logic in the browser.

## React-first entry points

- `/commercial`
- `/sells`
- `/sells/quotations`
- `/smart-documents`
- `/contacts?type=customer`

The former list pages remain available at `/sells?legacy=1`, `/sells/quotations?legacy=1`, `/smart-documents?legacy=1` and `/contacts?type=customer&legacy=1` for controlled rollback and comparison.

## User experience

- One responsive commercial centre with permission-aware tabs.
- Real invoice count, invoice value, outstanding amount, quotation count, actionable-document count and customer count.
- Search, status, document-type, permitted-location, date and page-size filters.
- Invoice and quotation actions for open, preview, print, download, share, edit and Smart Document generation when authorised.
- Industry-specific Smart Document names and scenarios from the company document profile.
- Smart Document actions for open, preview, print, download, controlled sharing and draft editing.
- Receipt centre separating issued receipt documents from recent customer payments.
- Explicit explanation that refundable security deposits are liabilities and are excluded from ordinary payment/income totals.
- Responsive customer cards with contact details, customer ID, advance balance and ledger access.
- Validated React quick-create form for customers, backed by a dedicated Laravel endpoint and existing contact lifecycle hooks.
- Existing import flows remain available for customers and sales.
- Keyboard-compatible controls, accessible labels, focus-managed modals, loading/error/empty states and reduced-motion support.

## Security and accounting boundaries

- The active company comes only from the authenticated session and must pass `canAccessBusiness`.
- Requested locations are validated against the active company and checked against the user’s permitted locations.
- Invoice and quotation queries distinguish company-wide, own-only and commission-agent access.
- Customer queries distinguish all-customer and assigned/created-customer access.
- Smart Documents are filtered through the industry catalog, company feature setting, document-type user/role/department rules, permitted locations and Property access grants.
- All preview, print, download, share, edit and detail actions re-enter their existing controller/service authorization checks.
- Payment rows are limited to visible sales; transaction and summary calculations exclude `payment_purpose = security_deposit`.
- Security-deposit ledgers remain separate from normal payments and revenue.
- Customer creation validates the active subscription, company access, `customer.create`, contact type, business name, person name, mobile, email and field lengths before a database transaction.

## API routes

- `GET /commercial/workspace`
- `POST /commercial/customers`

Both routes run inside the existing authenticated session, language, timezone, sidebar and login-status middleware group. No request parameter can select another company.

## Deployment

1. Back up the authoritative files and database. Keep `app.casherp.com` intact during `www.casherp.com` acceptance.
2. Apply the cumulative V18 overlay to a clean staging copy; do not apply V17 afterward.
3. Install Composer and frontend dependencies matching the authoritative lock files.
4. Run `npm run build` or verify the included compiled workspace bundle and source map.
5. Run every outstanding cumulative migration with `php artisan migrate --force`.
6. Run `php artisan optimize:clear` and reset the permission cache.
7. Confirm the two commercial routes and every React-first alias before production traffic changes.

## Required staging acceptance

- Test an owner/admin plus restricted all-record, own-record, commission-agent and assigned-customer roles.
- Attempt another company’s transaction, payment, contact, Smart Document, document type and location identifiers and confirm denial or absence.
- Reconcile invoice totals and outstanding amounts against Sales reports for at least one paid, partial, due, returned and multi-payment invoice.
- Confirm refundable security-deposit receipts and entries never increase ordinary payment totals or sales income.
- Verify quotation/proforma classification and own/all scoping.
- Verify enabled and disabled Smart Document types plus explicit user, role and department rules.
- Exercise preview, print, PDF download, sharing, editing and Smart Document conversion for every permitted document kind; confirm forbidden actions are absent and still rejected when called directly.
- Create an individual and business customer; test missing mobile, invalid email, missing business name and an expired subscription.
- Test search, filters, pagination, imports, browser back/forward and all four legacy rollback URLs.
- Test Chrome, Edge, Firefox and Safari at phone, tablet and desktop widths, including keyboard-only and reduced-motion operation.
- Review query plans and response time against production-volume transaction, payment, contact and Smart Document data.

## Verification boundary

Offline checks cover PHP parsing, source-level authorization contracts, React compilation and the cumulative unit/architecture suite. This patch root has no root Composer vendor tree, production database, real authenticated role fixtures, mail transport, queue, scheduler or browser farm. Route boot, migrations, database-driver SQL behavior, production-volume query plans and end-to-end browser actions require staging acceptance.
