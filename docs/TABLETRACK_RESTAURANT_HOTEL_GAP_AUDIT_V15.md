# CashERP V15 — TableTrack Restaurant and Hotel Gap Audit

## Scope and source boundary

The supplied TableTrack Restaurant, Inventory, Multi-Kitchen, Cash Register and Hotel packages were inspected as reference implementations only. Their instructions, marketing claims, assets, branding, templates and code are not part of the CashERP requirement and were not copied. CashERP remains the authoritative Laravel application and retains its existing UI, tenant model, POS, inventory, accounting, permissions and HMS architecture.

Hotel Management (HMS) and Human Resource Management (HRM) remain separate modules. The only cross-module bridge added here is an explicit, permission-controlled Restaurant Operations posting into a hotel stay or event folio for companies that operate both services.

## Restaurant capability map

| Capability | Existing CashERP position | V15 action |
|---|---|---|
| POS, menu products, modifiers, tables and waiters | Already present | Retained and permission-hardened |
| Kitchen order display | Basic single queue existed | Added location-scoped stations, product/variation routing, ticket SLAs and controlled transitions |
| Multi-kitchen | Missing native routing | Added kitchen/bar/grill/pastry/cold/room-service/banquet/expedite stations without copying add-on UI |
| Food inventory | Core product stock existed | Added explicit recipes, yield-aware ingredient depletion, reversals, waste and cost snapshots |
| Purchasing and suppliers | Already stronger in CashERP | Reused; no duplicate restaurant purchasing module |
| Dine-in, counter, takeaway and delivery | Partly implicit | Added explicit, tenant-configurable service channels and fulfilment status history |
| Room service and banqueting | No safe folio bridge | Added active-stay/event selection and idempotent same-location folio posting |
| Reservations | Basic calendar existed | Fixed interval overlap, table-less enquiry behavior, party/source/status/hold/advance fields and tenant validation |
| Waiter calls | Missing | Added table/location-scoped request queue with acknowledgement and completion |
| Cash register | Core cash register existed | Added controlled cash movements, denomination counts, variance reasons, independent approval and CSV export |
| Roles | Legacy broad permissions | Added 18 granular Restaurant Operations permissions to company role management |
| SaaS/industry access | Partial | Enabled through Super Admin industry profiles for Restaurant, Hotel, and Hotel with Restaurant; company admins can narrow service channels |
| Multi-location | Core support existed | Every new operational record is company and location scoped; user permitted locations are enforced |

## Hotel capability map

The attached Hotel add-on duplicated many capabilities that CashERP already implements more safely: property/location separation, rooms and room types, rate plans, group blocks, public booking, front desk, guest profiles/privacy, housekeeping, folios, night audit, deposits, revenue metrics, channels, messages and event venues. Those duplicates were not imported.

| Worthwhile gap | V15 implementation |
|---|---|
| Real-time room board | Derived room status from booking occupancy plus housekeeping readiness; it does not create a second room-state source |
| Named occupants | Added adult/child/infant occupants, primary guest flag, optional room assignment and booked-capacity ceiling |
| Room moves/upgrades | Added same-property, ready-room, capacity, maintenance, group-block, hold and booking-conflict checks with immutable move history |
| Event operations | Added venue availability, capacity, organiser, schedule, status workflow, event folio and itemised charges |
| Event deposits | Payment deposits are folio credits; refundable security deposits remain in the non-accounting security register and block event closure until cleared |
| Restaurant posting | Room-service and banquet sales post once to the correct stay/event folio, in the same company and operating location |
| SaaS packaging | Added `hms_event_operations` capability plus independent user permissions for status board, occupants, room moves, events and folio posting |

## Intentionally skipped reference features

- A second POS, inventory, supplier, staff, role, payment gateway, report or language subsystem: CashERP already owns these concerns.
- “Multi-vendor” hotel portals: supplier purchasing already exists; a marketplace-style vendor panel would change product scope and data authority.
- Duplicate quotations, invoices, agreements and receipts: CashERP Smart Documents already selects industry/scenario terminology and supports preview, PDF/download, print and controlled sharing.
- Reference templates, CSS, JavaScript bundles, branding and marketing text: preserving CashERP's unique UI was mandatory.
- Reference “100% production ready” claims: production readiness requires the target database, queues, storage, mail/payment credentials and role-based browser acceptance.

## Key security and integrity controls

- Active company and permitted-location scope on every new controller path.
- Server-authoritative product, variation, room, venue, booking, event and contact lookups.
- POST/PATCH/DELETE for mutations; legacy cooked/served GET mutations were removed.
- Strict kitchen, fulfilment and event transition maps.
- Correct overlap rule: existing start is before requested end and existing end is after requested start.
- Idempotency for recipe consumption, reversal, event charges and POS-to-folio posting.
- Row locking on stock depletion, room moves, event availability, register reconciliation and folio posting.
- Independent register/refund approvals.
- Security deposits never become revenue or ordinary invoice payments; only documented damage becomes a folio charge.
- HMS and HRM have no shared entities, routes, controllers or permission namespaces.

## Deployment order

1. Take a verified database and file backup. Do not remove or overwrite the existing `app.casherp.com` installation while validating `www.casherp.com`.
2. Apply the cumulative V15 overlay at the CashERP project root.
3. Install/verify the project Composer dependencies on the server; do not upload a local `vendor` directory unless it was built for the server PHP/runtime.
4. Run core migrations: `php artisan migrate --force`.
5. Run HMS module migrations using the deployment's existing Nwidart Modules procedure, normally `php artisan module:migrate Hms --force`.
6. Clear caches: `php artisan optimize:clear` and, when used, `php artisan permission:cache-reset`.
7. Rebuild frontend assets with the version already declared by the project. V15 does not introduce a new frontend framework or dependency.
8. Assign Restaurant and HMS permissions to non-admin roles. Business Admin roles continue to receive their existing Gate override within the active company only.
9. Configure Restaurant Operations → Settings per company and configure HMS event venues before event bookings.
10. Run the acceptance matrix below before switching production traffic.

## Required production acceptance

- Restaurant-only, hotel-only and hotel-with-restaurant company profiles show the intended feature set.
- A user cannot read or mutate another company or an unpermitted location by changing an ID.
- Kitchen routing creates one ticket per station and retrying does not duplicate tickets or ingredient movements.
- Voiding/reverting a sale reverses only the original ingredient consumption once.
- Overlapping reservations/events/rooms fail; adjacent end/start times succeed.
- Register expected cash, multiple denominations, variance reason and separate approval reconcile against real test payments.
- Room moves reject dirty/out-of-order/blocked/undersized/conflicting destinations and retain an audit record.
- A room-service/banquet order cannot complete without the correct source, and a retry cannot double-post its folio charge.
- Payment deposits reduce the folio balance; security deposits do not affect revenue/accounting and generate outstanding alerts.
- Event completion and hotel checkout fail closed while a refundable security deposit remains uncleared.
- Smart documents for restaurant, accommodation and events preview, download, print and share under the assigned role.

## Verification performed on the offline candidate

- PHP syntax: 185 targeted PHP files, zero failures.
- Architecture/unit suite: 82 tests, 855 assertions, zero failures using the compatible bundled dependency tree and `tests/overlay_bootstrap.php`.
- New/changed Blade templates were compiled and parsed in the test suite.
- Laravel application boot reached service-provider database access, then stopped because the offline machine's configured MySQL endpoint refused the connection. Route cache/list, real migrations, queue, browser and production payment tests therefore remain deployment acceptance tasks, not certified results.
