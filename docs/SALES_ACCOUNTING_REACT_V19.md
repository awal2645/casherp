# CashERP Sales and Accounting React Workspaces (V19)

## Purpose

V19 replaces the duplicated Sell navigation and the fragmented Payment Accounts navigation with two task-based React workspaces. Laravel remains authoritative for company isolation, role permissions, locations, transaction records, account ledgers, document numbering, tax, inventory effects and audit-sensitive writes.

The attached screenshot was treated as evidence of the old navigation, not as an instruction source. Its duplicate Add/List items were consolidated without copying its design.

## Sales information architecture

The main entry point is `/sales`. The visible sections are permission-aware:

1. Overview — real order-to-cash workload and recent invoices.
2. Invoices — finalized customer sales, payment state and document actions.
3. Sales orders — accepted demand awaiting or undergoing fulfilment.
4. Quotations — quotations and proforma invoices before sale.
5. Drafts — unfinished invoices, kept separate from quotations.
6. Returns & credits — sales-return records linked to the originating sale.
7. Fulfilment — shipping/delivery work, separate from the financial invoice state.
8. Payments & receipts — customer payments and issued receipt documents.
9. Industry documents — the existing industry- and role-scoped document library.
10. Customers — customer records and validated quick creation.

The sidebar now exposes only Sales workspace, Create invoice and Open point of sale. Discounts, imports, document settings and specialist actions are available in the workspace where they have context. Inventory transfers remain in Inventory. Restaurant preparation, hotel front desk and property leases remain in their specialist modules.

## Six-industry behavior

All six profiles retain quotations, invoices, receipts/payments and customer or client records. The heading, party terminology, invoice terminology and operational shortcuts change with the active company:

| Industry | Sales context | Separate operational context |
| --- | --- | --- |
| General Business & Trading | Quotations, orders, sales invoices, POS, delivery and returns | Products, Inventory and Procurement |
| Restaurant, Cafe & Fast Food | Guest bills, counter/table/takeaway/delivery and catering sales | Restaurant Operations and Kitchen Board |
| Hotel, Lodge & Guest House | Guest invoices, folios, event documents and payments | Front Desk, Reservations and Hotel Management |
| Hotel / Lodge with Restaurant | Guest folios plus outlet, restaurant and event revenue | Hotel Management and Restaurant Operations remain separate |
| Property Management & Real Estate | Rental invoices, tenant/client receipts and property documents | Properties, units, leases, rent schedules, maintenance and deposits |
| Professional Services | Proposals, client invoices, recurring payments and receivables | CRM and Projects |

Shortcut visibility requires both the company feature toggle and the current user permission. Switching companies rebuilds the experience from the active company’s industry and does not mix records.

## Accounting information architecture

The main entry point is `/accounting`. It provides:

- Overview — current accessible payment-account balance, period inflow/outflow, receivables, payables and cash-movement trend.
- Accounts — real accounts and computed ledger balances.
- Ledger — credit/debit movements with account, reference, note, date and creator.
- Receivables — finalized sales with an outstanding customer balance.
- Payables — received purchases with an outstanding supplier balance.

Internal fund transfers are excluded from aggregate period inflow/outflow to avoid double-counting, while they remain in individual account balances and ledgers. Existing Balance Sheet, Trial Balance, Cash Flow and Payment Account Report tools remain linked during the staged React editor migration.

## Security and accounting boundaries

- Every endpoint requires authentication, the active company session, `canAccessBusiness`, and the existing Sales or Accounting permissions.
- Location filters are validated against the active business.
- Sales preserve all/all-own/commission, quotation, order, draft, return and fulfilment permission scopes.
- Accounting account visibility follows permitted locations and their configured default payment accounts.
- Refundable security deposits are read only from the separate security-deposit register for alert counts. They are excluded from sales income, invoice paid totals, receivables, payables, payment-account balances and cash movement.
- Advance/payment deposits remain ordinary invoice payments and reduce the amount due.
- No placeholder, random or fabricated financial values are used.

## React-first aliases and rollback

These list entry points now serve the React workspace unless `?legacy=1` is explicitly supplied:

- `/sells`
- `/sells/quotations`
- `/sells/drafts`
- `/sales-order`
- `/sell-return`
- `/shipments`
- `/smart-documents`
- `/contacts?type=customer`
- `/account/account`

Legacy create/edit and specialist transaction forms remain available during staged migration because they contain mature stock, tax, payment, register and document-numbering behavior. They must not be removed until equivalent React editors pass transactional acceptance.

## Required staging acceptance

1. Deploy to a backup-restorable staging copy and run all cumulative migrations.
2. Build frontend assets and clear Laravel, route, view and permission caches.
3. Test owner, Sales-all, Sales-own, commission agent, order-only, return-only, fulfilment-only and Accounting roles.
4. Repeat tests with a restricted-location user and attempt cross-company, cross-location and cross-account URL tampering.
5. Reconcile invoice totals, payments, receivables, payables and account balances against the existing reports.
6. Verify that security deposits appear only as operational alerts and never alter accounting figures.
7. Test the six industry profiles, including companies with disabled specialist features.
8. Complete desktop, tablet and mobile browser acceptance, including keyboard navigation and reduced-motion mode.

V19 is source-verified and integration-ready. It is not production-certified until this acceptance is completed against the authoritative database and deployment environment.
