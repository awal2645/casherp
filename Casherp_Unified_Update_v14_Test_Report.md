# CashERP Unified Update v14 Verification Report

Verification date: 2026-09-03

## Automated result

- Tests: 78
- Assertions: 793
- Failures: 0

The focused deposit and advance-guidance suite passes:

- Tests: 8
- Assertions: 87
- Failures: 0

## v14 checks

The automated checks verify that:

- the shared notification identifies itself as a 50% Payment Deposit
  (Advance) guide;
- the wording explicitly says that it is advisory only;
- saving, invoicing, and reservation are not blocked;
- refundable Security Deposits are expressly excluded from the calculation;
- live total, minimum, paid, percentage, and shortfall calculation hooks exist;
- the Smart Document controller supplies the active company industry;
- the Smart Document notice is limited to Property Management, Hotel/Lodge/
  Guest House, and Hotel with Restaurant;
- hotel booking create and edit pages include the live guide;
- public booking requests explain that a request can be submitted without
  payment;
- Property lease creation includes the notice;
- group booking and rate-plan screens contain the advisory wording;
- affected Blade views compile to parseable PHP.

## Cumulative checks retained

The complete suite continues to verify:

- separate Security Deposit and Payment Deposit ledgers;
- no Accounting dependency in the Security Deposit service;
- exact lease/unit, hotel booking, event venue, and operating-location context;
- lease, checkout, and event closure gates;
- fail-closed damage invoice generation;
- damage invoice and hotel folio integration;
- independent refund approval and payment confirmation;
- Customer Advances liability accounting for Property payment advances;
- customizable company payment methods and rejection of non-money settlement
  terms;
- industry onboarding and active-company isolation;
- separate HRM and HMS feature boundaries;
- HR availability in all six launch industries;
- HR security, payroll calculations, Property import, SaaS pricing, DPO
  architecture, SMTP architecture, canonical-domain behavior, Smart Documents,
  printing, PDF download, sharing, and transaction-document access controls.

## Source/package result

- Deployable source/configuration files: 785
- PHP and Blade-PHP files: 760
- Obsolete files listed for removal: 1

All packaged PHP and Blade-PHP files must pass `php -l` before the ZIP is
released. The ZIP is also checked against its SHA-256 file manifest for missing,
mismatched, unexpected, or forbidden runtime files.

## Test environment and limitation

The candidate archive does not include its own Composer `vendor` tree. Tests
use the compatible local dependency tree and workspace bootstrap with
`C:\xampp\php\php.exe` and PHPUnit 9.6.3.

The tests do not certify the production MySQL database, live queue/Scheduler,
SMTP delivery, DPO provider integration, cPanel/DNS cutover, browser/device
rendering, production accounting reconciliation, or country-specific legal and
accounting compliance. Those remain staging acceptance requirements.

