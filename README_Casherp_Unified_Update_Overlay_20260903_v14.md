# CashERP Unified Industry ERP Cumulative Overlay v14

## Release purpose

This is the current single cumulative developer overlay for the audited
CashERP Laravel source baseline. It supersedes v13 and all earlier overlays
from this workspace. Deploy v14 by itself; do not stack earlier ZIP files.

The package preserves the current CashERP UI structure and includes the
cumulative core/addon, SaaS, industry-onboarding, multi-company, HRM, Hotel
Management, Property Management, Procurement, pricing, DPO Pay, SMTP, import,
Smart Document, main-domain, authorization, transactional-document,
Security Deposit, Damage Charge, Payment Deposit, payment-method, and
company-customization work.

## New in v14: 50% Payment Deposit guidance

CashERP now shows a non-blocking Payment Deposit (Advance) notice on the
relevant Hospitality and Real Estate/Property workflows:

- Hotel reservation creation.
- Hotel reservation editing.
- Public hotel booking requests.
- Group reservations and room blocks.
- Hotel rate-plan setup.
- Property lease/unit allocation.
- Hospitality and Property Smart Documents, including applicable quotations,
  invoices, rental documents, long-stay documents, catering documents, event
  contracts, and venue-booking documents.

Where both a total and Payment Deposit amount are present, the notice:

- calculates 50% of the current agreed total;
- shows the total, recommended minimum, and entered advance;
- shows the percentage entered;
- turns red when the entered amount is below the guide;
- shows the remaining shortfall;
- turns green when 50% or more has been entered;
- recalculates when prices, lines, discounts, taxes, or payments change.

The notice is deliberately advisory:

- It does not add a backend minimum rule.
- It does not disable Save, Issue, Invoice, Request Booking, or Reservation.
- It tells users that authorized company policy, contractual terms, sales
  channels, and local requirements can differ.
- Public guests may submit an availability/booking request without payment;
  the property can communicate its applicable confirmation terms.
- Hotel rate plans may still use a percentage below or above 50%.

The notice also states that a refundable Security Deposit is separate and must
not be included when calculating the 50% Payment Deposit.

## Deposit distinction retained

### Security Deposit (Refundable)

- Separate safeguarding register.
- Not revenue, tax, an expense, or an invoice payment.
- Does not reduce the customer balance.
- Does not post to CashERP Accounting, following the requested business rule.
- Tied to the exact lease/unit, hotel booking/folio, event venue, or operating
  location.
- Supports receipt, evidence-backed damage, deduction, excess charge, refund,
  waiver, alerts, and closure control.
- Refund workflow is Request -> independent Approval -> Payment Confirmation.
- The requester cannot approve their own refund.

### Payment Deposit (Advance)

- Real payment toward a room, event, rental, invoice, or service.
- Reduces the related customer balance.
- Remains separate from Security Deposit in UI, data, documents, and reports.
- Property automatic accounting uses Cash/Bank, Customer Advances liability,
  Tenant Receivables, and Rental Income through balanced postings.

## Tenant customization retained

Each company can:

- enable or disable its industry-approved document types;
- customize document titles, prefixes, numbering, terms, and access;
- restrict documents to company roles, HR departments, or named users;
- enable, disable, and rename Cash, Visa/Card, Mobile Money, Bank Transfer,
  Cheque, Credit, Free/Complimentary, Other, and five custom payment methods.

Credit and Free/Complimentary remain arrangements rather than money received
and cannot be selected for receipts, refunds, or deposit settlements.

## Package contents

- 785 deployable added/changed source and configuration files compared with the
  audited live-source baseline.
- 760 PHP and Blade-PHP files.
- One deletion manifest for the obsolete
  `app/Notifications/TestEmailNotification.php`.
- A complete SHA-256 source-file manifest.
- This deployment guide and the v14 verification report.

The package excludes the production `.env`, credentials, customer uploads,
logs, caches, sessions, database dumps, backups, `vendor`, and
`node_modules`. The included `.env.domain.example` is a safe template with
empty DPO credential placeholders and must not replace the real production
`.env`.

## Deployment

1. Deploy to a staging clone of the current production files and database.
2. Confirm the target matches the audited CashERP source baseline.
3. Take verified, restorable file and database backups.
4. Put staging into maintenance mode.
5. Extract the v14 ZIP into the Laravel application root and preserve paths.
6. Review the deletion manifest and remove only the exact obsolete notification
   file after confirming the target.
7. Keep the real `.env`; manually merge the required canonical-domain, cookie,
   mail, queue, and DPO environment values.
8. Install production Composer dependencies and build the React SMTP-settings
   bundle where required:

   ```bash
   composer install --no-dev --prefer-dist --optimize-autoloader
   pnpm install --frozen-lockfile
   pnpm run build:smtp
   ```

9. Run migrations and rebuild caches:

   ```bash
   php artisan migrate --force
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

10. Restart PHP-FPM/Apache and queue workers; confirm Laravel Scheduler runs
    every minute.
11. Test `www.casherp.com` registration, login, company switching, documents,
    payments, DPO callbacks, email links, and assets before removing the old
    application subdomain.

## Required acceptance for the 50% guide

1. Create a hotel reservation and change the advance below, equal to, and above
   50%; confirm the notice changes without blocking Save.
2. Edit a reservation with multiple payment lines and confirm the displayed
   percentage uses their total.
3. Change room rates, nights, extras, coupons, discounts, and tax; confirm the
   minimum recalculates.
4. Create Property and Hospitality Smart Documents in the supported scenarios;
   confirm the guide appears.
5. Select Payment or Security Deposit document scenarios; confirm the unrelated
   50% guide does not appear.
6. Confirm the guide appears on Property lease setup but does not confuse the
   refundable Security Deposit field with a Payment Deposit.
7. Submit a public booking request without payment and confirm it remains
   permitted.
8. Confirm a hotel rate plan can deliberately use a lower or higher percentage.
9. Test desktop, tablet, and mobile layouts and supported browsers.

## Verification boundary

The package passed the available source-level syntax, Blade compilation,
architecture, and regression tests. This is not production certification.
Migration against a current MySQL staging clone, real user/role acceptance,
multi-user concurrency, Accounting reconciliation, queue/Scheduler operation,
SMTP delivery, DPO provider approval, cPanel/DNS cutover, browser testing, and
country-specific legal/accounting review remain staging requirements.

