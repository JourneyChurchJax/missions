# Journey Missions

Journey Church's mission trip app at **missions.journeychurch.org**. It replaces ManagedMissions: trips, teams, applications, schedules, documents, signatures, fundraising pages and giving, with Stripe for payments and Planning Center for people.

Plain PHP 8 (no framework), SQLite, hosted on SiteGround.

## How deploys work

- `public/` is what the website serves.
- Every push to `main` runs `.github/workflows/deploy.yml`:
  1. **Check:** every PHP file is syntax-checked, then `tests/smoke.sh` runs the site on a throwaway copy and opens every main page. If anything fails, nothing is uploaded.
  2. **Deploy:** uploads `public/` to SiteGround over encrypted FTP (FTPS). Only changed files are sent.
- Re-run a deploy by hand: **Actions → Deploy to missions.journeychurch.org → Run workflow**.
- Run the check yourself before pushing: `bash tests/smoke.sh`

## Secrets (never in this repo)

Set in **Settings → Secrets and variables → Actions**:

| Name | Type | Value |
|---|---|---|
| `FTP_SERVER` | Secret | `ftp.journeychurch.org` |
| `FTP_USERNAME` | Secret | `missions@journeychurch.org` |
| `FTP_PASSWORD` | Secret | the FTP account's password |
| `FTP_SERVER_DIR` | Variable | the folder to upload into, e.g. `public_html/` |

## Server config

Server-side settings live in `config.php`, which exists only on the server, in the `missions.journeychurch.org` folder **next to** `public_html` (never inside it, so it can't be downloaded). It is in `.gitignore`, and the deploy never touches it. Settings shows a red warning if it's in the wrong place.

```php
<?php
return [
    // Staff sign-in fallback. Planning Center is the normal way in.
    'preview_password' => 'choose-a-long-random-password',
    'site_url' => 'https://missions.journeychurch.org',

    // Church details used on tax statements and receipts
    'church_name' => 'Journey Church',
    'church_legal_name' => 'Journey Church, Inc.',
    'church_address' => '123 Main St, Jacksonville, FL 32200',
    'church_ein' => '00-0000000',

    // Where alerts go when something breaks (failed Stripe events, failing emails, errors)
    'alert_email' => 'adam@journeychurch.org',

    // Email. 'mail_from' turns sending on. Add 'smtp' to send through an email service (recommended: Postmark or Amazon SES).
    'mail_from' => 'missions@journeychurch.org',
    'mail_name' => 'Journey Missions',
    'mail_reply_to' => 'missions@journeychurch.org',
    // 'smtp' => ['host' => 'smtp.postmarkapp.com', 'port' => 587, 'user' => '...', 'pass' => '...'],

    // Texts: a Twilio number registered for A2P 10DLC
    // 'twilio' => ['sid' => 'AC...', 'token' => '...', 'from' => '+19045550100'],

    // Stripe: Developers → API keys, and Developers → Webhooks
    // 'stripe' => ['secret' => 'sk_live_...', 'webhook_secret' => 'whsec_...'],

    // Planning Center: OAuth app (sign-in) and personal access token (people sync)
    // 'pco' => ['client_id' => '...', 'client_secret' => '...', 'app_id' => '...', 'secret' => '...'],
    // Planning Center person IDs who are always staff. Anyone else is made staff with the "Staff access" switch on their person page.
    // 'staff_pco_ids' => ['12345678'],

    // Key for the nightly job (see "Nightly job" below). Any long random string.
    'cron_key' => 'long-random-string',

    // Encryption key for passport numbers and medical notes. If left out, one is created in data/secret.key.
    // Keep a copy somewhere safe: without it, those fields can't be read back.
    // 'data_key' => 'base64 key from: php -r "echo base64_encode(random_bytes(32));"',

    // Days after a trip ends before passport and medical details are erased (default 120)
    // 'retention_days' => 120,

    // Optional Cloudflare Turnstile on the public apply and give forms
    // 'turnstile' => ['site' => '...', 'secret' => '...'],
];
```

## Outside services to point at the site

| Service | Setting | Value |
|---|---|---|
| Stripe | Webhook URL | `https://missions.journeychurch.org/stripe-webhook.php` |
| Stripe | Webhook events | `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed`, `invoice.paid`, `customer.subscription.deleted`, `charge.refunded`, `charge.dispute.created`, `charge.dispute.closed` |
| Planning Center | OAuth redirect URL | `https://missions.journeychurch.org/auth/pco.php` |
| Twilio | "A message comes in" webhook | `https://missions.journeychurch.org/twilio-inbound.php` |
| Uptime monitor | URL to check | `https://missions.journeychurch.org/health.php` |

## Nightly job

In SiteGround **Devs → Cron Jobs**, run once a night:

```
curl -s "https://missions.journeychurch.org/cron.php?key=YOUR_CRON_KEY" > /dev/null
```

It sends queued emails and texts, makes the nightly database backup (kept 30 days in `data/backups`), erases passport and medical details after trips end, and clears old drafts. The site also does light cleanup on its own as people use it.

## Where data lives

- `data/`, next to `public_html` on the server (outside the website, and blocked even if it ever ended up inside): `journey.sqlite` (the database), `uploads/` (files people upload), `backups/`, `logs/`, `settings.json` (settings changed in the app), `secret.key`.
- Database changes happen automatically. The schema version is `SCHEMA_VERSION` in `public/inc/db.php`; before upgrading, the site saves a backup copy first.
- Sample data ("demo") is per staff member: turning it on in Settings only changes what *you* see. Public pages, applications, sign-in and Stripe always use real data.

## Restore from a backup

1. In SiteGround File Manager, open the `data/backups/` folder (next to `public_html`) and pick the backup you want (they're named by date).
2. Rename the current `data/journey.sqlite` to `journey-broken.sqlite`. If `journey.sqlite-wal` and `journey.sqlite-shm` are there, rename those too.
3. Copy the backup to `data/journey.sqlite`.
4. Open the site and check the latest gifts and people look right.

Uploads (passport photos, documents) are not in the database backup. Copy `data/uploads/` off the server on a schedule too.

## Pages

- `/signin.php` sign in · `/` pick staff or traveler view
- Staff: `/admin/` · `trip.php` · `people.php` · `applications.php` · `giving.php` · `reports.php` · `signatures.php` · `settings.php` · `search.php`
- Traveler: `/trip/` · `checklist` · `schedule` · `documents` · `fundraising` · `messages` · `guide` · `profile`
- Public: `/give/` and each traveler's page (`/thomas`), `/apply/`, `/reference/`, `/parent/`, `/sign.php`, `/calendar.php`

## Design

- Brand: Journey Church design system (rounded corners, soft shadows, cream / ink / one ember red).
- Styles: `public/assets/app.css`. Behavior: `public/assets/app.js` (no libraries).

## Open items

See `docs/audit-2026-10-01.md` for what's left. Everything there needs a decision, an account or a professional review.
