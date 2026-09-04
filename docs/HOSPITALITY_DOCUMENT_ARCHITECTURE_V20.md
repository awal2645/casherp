# CashERP V20 Hospitality Document Architecture

## Purpose

V20 separates three workflows that must not share one generic invoice:

1. Accommodation stays use accommodation quotations, invoices, payment receipts and guest folios.
2. Event and venue hire use standard A4 commercial quotations, contracts, invoices and payment receipts.
3. Restaurant sales use the Restaurant/POS transaction flow and can produce either an 80 mm paid receipt or an A4 sales invoice.

The existing CashERP visual identity is retained. React supplies the commercial workspace actions; Laravel remains authoritative for tenant scope, permissions, document creation, totals, numbering, PDF rendering and audit records.

## International control baseline

- CashERP uses the company currency record and ISO-style three-letter currency code so amounts remain unambiguous across countries.
- An advance received for goods or services is kept distinguishable from earned revenue and from a refundable security liability. Final statutory posting remains configurable for the tenant's jurisdiction and accounting policy.
- The 50% reservation message is a visible business-policy guide requested for CashERP. It does not block a transaction and is not described as a universal legal or hospitality rule.
- Tax names, rates, invoice wording, numbering and retention requirements remain company/jurisdiction configurable; CashERP does not impose one country's VAT or fiscal rules globally.
- HMS payment input discards card verification codes, does not retain expiry values and masks the primary account number to its last four digits. Gateway/terminal references should be used for card payments.
- Amount calculations retain four-decimal internal precision and round only at controlled document/payment boundaries.
- Issued records are immutable except through controlled status, credit/refund and audit workflows.

Reference anchors for staging review are IFRS 15 revenue/contract-liability principles, ISO 4217 currency codes and PCI DSS payment-account-data controls. These are architecture references, not a substitute for local legal, tax or accounting advice.

## Accommodation rules

- The source of truth is an HMS booking owned by the active company.
- The document shows booking date, arrival date and time, checkout date and time, billable nights and descriptive elapsed duration.
- Room pricing uses calendar nights. Elapsed hours are informational and do not silently alter pricing.
- Early check-in, late checkout and day-use charges remain explicit extras or rate rules.
- Accommodation quotations are non-financial: they cannot store or print payments or balances.
- Accommodation invoices may show valid booking payments and their remaining balance.
- Accommodation payment receipts show only payments applied to the accommodation price.
- Refundable security deposits are displayed separately and never enter revenue, tax, invoice total, amount paid or balance due.

## Event and venue-hire rules

- The source of truth is an HMS event booking linked to a tenant-owned venue and operating location.
- The normal workflow is Event / Venue Quotation, Event Reservation Contract, Event / Venue Sales Invoice and Event Payment Receipt.
- Event charges and discounts become invoice lines; advance payments become credits against the event invoice.
- The backend determines financial direction: a user cannot convert an ordinary charge into a payment by selecting debit or credit.
- Payment deposits require a valid payment method.
- Refundable event security deposits remain in the separate security-deposit register and are shown only as an informational liability amount.
- Damage becomes a supported charge. Only the retained portion of a security deposit is applied against that charge; excess damage remains due.

## Restaurant and hotel-restaurant rules

- Restaurant, Cafe & Fast Food and Hotel / Lodge with Restaurant profiles receive the explicit restaurant output choices.
- A finalized restaurant sale may create an A4 sales invoice.
- A fully paid finalized restaurant sale may create an 80 mm POS receipt.
- An unpaid sale cannot be presented as a payment receipt.
- Hotel, Lodge & Guest House without a restaurant profile does not receive restaurant/POS actions by default.
- Sales lines are itemized from the actual transaction. A transparent order-adjustment line reconciles transaction-level charges or discounts without replacing the item detail.

## Refundable security-deposit migration policy

The HMS extra master and booking-extra snapshot tables gain `financial_classification`:

- `revenue`: taxable/chargeable accommodation service.
- `refundable_security_deposit`: liability requirement held outside commercial totals.

Reusable master extras whose names contain both `security` and `deposit` are flagged for future use. Existing booking-extra snapshots remain revenue until an authorized user intentionally edits the booking. This avoids silently rewriting issued historical invoices. Existing deposits should be reviewed and migrated through a controlled finance reconciliation.

## Tenant controls and access

- All source records are scoped to the active company.
- Location and property/venue ownership are checked before a document can be created.
- Existing Smart Document type permissions remain authoritative.
- Company administrators can enable document types and customize their displayed titles through Business Document Settings.
- Issued documents retain their business, customer and template snapshots for audit consistency.

## Deployment order

1. Back up application files and database.
2. Apply the cumulative V20 overlay to a staging copy of the authoritative CashERP root.
3. Install the locked backend and frontend dependencies.
4. Run all outstanding Laravel migrations with `php artisan migrate --force`.
5. Rebuild frontend assets with `npm run build`.
6. Clear caches with `php artisan optimize:clear` and reset the permission cache.
7. Verify one company in each applicable profile with an owner and a restricted user.
8. Reconcile accommodation revenue, advance payments and the security-deposit liability register against known records.
9. Test A4 quotation/invoice/receipt PDFs and an 80 mm receipt using the production PDF/fonts environment.
10. Promote only after staging acceptance; keep the existing `app.casherp.com` installation intact until the main-domain deployment has passed rollback-safe acceptance.

## Required acceptance cases

- Four-night stay crossing different arrival and checkout times.
- Same-day/day-use policy handled explicitly without corrupting ordinary night pricing.
- Accommodation quotation contains no payment section.
- Accommodation invoice excludes a refundable security deposit from every financial total.
- Partial and full booking-payment deposits reduce the invoice balance.
- Event quotation converts to the correct contract/invoice family.
- Event advance payment produces an Event Payment Receipt.
- Event and accommodation damage settlement retains only the authorized security amount and leaves excess due.
- Restaurant paid sale produces 80 mm and A4 options; unpaid sale offers A4 invoice only.
- Cross-company, cross-location and unauthorized-role attempts are rejected.
- Customized tenant document titles appear on newly generated documents without changing the global type definition.

## Reference links

- IFRS 15: https://www.ifrs.org/issued-standards/list-of-standards/ifrs-15-revenue-from-contracts-with-customers/
- ISO 4217: https://www.iso.org/iso-4217-currency-codes.html
- PCI DSS: https://www.pcisecuritystandards.org/standards/pci-dss/
