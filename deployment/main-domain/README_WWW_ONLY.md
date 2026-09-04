# CashERP www-only deployment contract

## Sole public origin

The complete CashERP application must execute only at:

```text
https://www.casherp.com
```

This includes the landing page, login, registration, password reset, pricing,
subscription checkout, dashboards, Super Admin, every industry module, APIs,
uploads, generated links, notification links, and payment callbacks.

`casherp.com` and `app.casherp.com` are not application origins. The bare domain
is the only application-configured redirect host. During cutover, the former
`app` virtual host may only return a web-server-level, path-preserving redirect
to `www`; it is deliberately absent from Laravel's accepted host list. Once old
bookmark and gateway traffic has been reviewed, delete the `app` DNS record and
cPanel subdomain. If cPanel offers to delete its document-root directory, do not
select that option while the Laravel source remains in that directory.

## Required cPanel state

- `www.casherp.com` serves `public_html`.
- `public_html/index.php` boots the Laravel application stored outside the web
  root.
- The old static landing-page entry point is replaced by Laravel's public files.
- The apex domain redirects to `https://www.casherp.com`.
- The temporary `app` host contains only the supplied redirect `.htaccess`; it
  must not reach Laravel's `public/index.php`.
- No wildcard subdomain should point to the CashERP application.
- SSL must be valid for `www`; retain SSL for the redirect-only hosts only while
  they still exist.

An internal folder named `app.casherp.com` is not itself a public subdomain. It
is simply the current cPanel filesystem location. The cPanel host mapping and
DNS record determine whether the subdomain exists. The folder may be renamed to
a neutral name in a separately backed-up maintenance operation, but renaming is
not required for a single public web origin.

## Required environment

```dotenv
APP_URL=https://www.casherp.com
ASSET_URL=https://www.casherp.com
CANONICAL_URL=https://www.casherp.com
CANONICAL_LEGACY_HOSTS=casherp.com
SESSION_DOMAIN=www.casherp.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CORS_ALLOWED_ORIGINS=https://www.casherp.com
```

Do not use `.casherp.com` for `SESSION_DOMAIN`; that would share the session
cookie with subdomains. The Laravel legacy-host list authorizes only the bare
domain redirect. The former `app` host is outside the application and must be
retired from cPanel and DNS after its optional transition redirect.

## Enforced source behavior

- Laravel forces generated absolute URLs to the canonical root, including
  queue-generated email links and DPO/payment return URLs.
- Requests on the canonical host and HTTPS continue normally.
- GET/HEAD requests on the bare domain receive a path-preserving `301`.
- Non-idempotent bare-domain requests receive a path-preserving `308`.
- The optional old `app` virtual host uses the same statuses without loading
  Laravel, and is removed after cutover acceptance.
- An unknown Host or wildcard subdomain receives `421 Misdirected Request`.
- Browser API CORS defaults to the canonical origin rather than `*`.
- Landing-page links use same-origin Laravel URL generation.

## Deployment order

1. Take verified filesystem and database backups.
2. Confirm the current source and the `public_html` document root.
3. Deploy the cumulative CashERP application changes, including pricing/DPO.
4. Run `deployment/main-domain/deploy.sh` from the Laravel application root.
5. Confirm the generated environment values before rebuilding caches.
6. Test `/`, `/login`, `/business/register`, `/pricing`, `/home`, assets,
   uploads, API authentication, and every payment return URL on `www`.
7. Confirm apex and temporary `app` requests never execute the ERP.
8. Test login/logout, password reset, registration, email links, company
   switching, DPO and every enabled gateway.
9. Observe logs and traffic, then remove the `app` cPanel subdomain and DNS
   record without deleting the Laravel application directory.

## Acceptance commands

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

Expected external results:

```text
https://www.casherp.com/                  200
https://www.casherp.com/login             200
https://www.casherp.com/business/register 200 or intentional registration-disabled response
https://www.casherp.com/pricing           200
https://www.casherp.com/home              302 to www login when signed out
https://casherp.com/any-path               301 to the same path on www
https://app.casherp.com/any-path           301 to the same path on www during transition, then DNS retired
```

Do not declare completion until an authenticated browser session confirms that
all ERP modules, files, queues, emails, and gateways stay on `www` without a
mixed-origin or redirect loop.
