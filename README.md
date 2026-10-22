# randevu

A multi-tenant online appointment booking system for barbers and salons. Each shop is a tenant with its own services, staff, working hours and theme. Built with plain PHP 8.3 and PostgreSQL – no framework, no Composer.

> **Status: early stage.** Phases 0–3 of the [plan](PLAN.md) are implemented (infrastructure, core, customer booking flow, owner panel). OTP verification, statistics, reminder cron and real SMS/WhatsApp providers are planned (see below). The plan document is written in Turkish.

## What works today

**Customer side** (mobile-first, dark/light theme, per-shop colors and fonts)
- Step-by-step booking: day & time → staff → service (prices visible) → name & phone → confirmation
- No account required; payment happens at the shop
- Passwordless cancel link (random token) shown on the confirmation page
- Availability engine that respects working hours, split shifts, time blocks and existing bookings; overlapping bookings are rejected at the database level with a PostgreSQL `EXCLUDE USING gist` constraint

**Shop owner panel**
- Login with brute-force protection and CSRF protection
- Daily timeline with one column per staff member
- Manual booking that can override free-slot rules (highlighted in red)
- CRUD for services, staff and working hours

**Multi-tenancy**
- Subdomain mode (`{shop}.<root_domain>`) for production, path mode (`/d/{shop}`) for testing

## Roadmap

- Phase 4 – statistics, reminder cron, super-admin onboarding
- Phase 5 – hardening: OTP, anti-spam, Turnstile, data purge, CSP, rate limiting
- Phase 6 – real SMS/WhatsApp providers (notification driver is currently a stub)

## Stack

PHP 8.3 (FPM) · PostgreSQL 16 (`btree_gist` extension) · nginx · vanilla JS/CSS

## Setup

1. Create a PostgreSQL role and database `randevu`, then load `db/schema.sql` (needs the `btree_gist` extension) and optionally `db/seed.sql` for demo data. The seed uses placeholder password hashes – generate real bcrypt hashes before using it.
2. Copy `config.example.php` to `config.php` and fill in the database credentials and a long random `app.secret`. Keep `config.php` out of version control (it is already in `.gitignore`).
3. Use `deploy/php-fpm-randevu.conf` and `deploy/nginx-randevu.conf` as starting points for the PHP-FPM pool and nginx vhost.
4. Open `/d/demo` (path tenancy) to try the booking flow, and `/panel` for the owner panel.

## Project layout

```
public/            front controller and assets
src/Core/          database, tenant resolution, auth, CSRF, rate limiter, views, notifications
src/Controllers/   booking and panel controllers
src/Services/      availability and booking logic
src/Views/         PHP templates
db/                schema and demo seed
deploy/            nginx and PHP-FPM configuration examples
```
