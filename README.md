# Journey Missions

Journey Church's mission trip app at **missions.journeychurch.org**. It replaces ManagedMissions: trips, teams, applications, schedules, documents, fundraising pages and giving, with Stripe for payments and Planning Center for people.

## How deploys work

- `public/` is what the website serves.
- Every push to `main` runs `.github/workflows/deploy.yml`, which uploads `public/` to SiteGround over encrypted FTP (FTPS). Only changed files are sent.
- You can re-run a deploy by hand: **Actions → Deploy to missions.journeychurch.org → Run workflow**.

## Secrets (never in this repo)

Set in **Settings → Secrets and variables → Actions**:

| Name | Type | Value |
|---|---|---|
| `FTP_SERVER` | Secret | `ftp.journeychurch.org` |
| `FTP_USERNAME` | Secret | `missions@journeychurch.org` |
| `FTP_PASSWORD` | Secret | the FTP account's password |
| `FTP_SERVER_DIR` | Variable | the folder to upload into, e.g. `public_html/` |

## Server config

Server-side settings live in `config.php`, which exists only on the server, in the `missions.journeychurch.org` folder **next to** `public_html` (so it can never be downloaded). It is in `.gitignore` and the deploy skips it.

```php
<?php
return [
    'preview_password' => 'choose-a-strong-password',
    // later: database, Stripe and Planning Center keys
];
```

## Pages

- `/signin.php` preview sign-in · `/` pick staff or traveler view
- Staff: `/admin/` trips · `/admin/trip.php` · `people` · `applications` · `giving` · `reports` · `settings`
- Traveler: `/trip/` · `schedule` · `documents` · `fundraising` · `messages`

Data is sample data from `public/inc/data.php` until the database is connected.

## Design

- Clickable design of every page: Journey Missions — App Mockups (Claude artifact)
- Brand: Journey Church design system (rounded corners, soft shadows, cream / ink / one ember red)

## Status

- [x] Design: every admin and member page
- [x] Deploy pipeline and coming-soon page
- [ ] App: sign-in, trips, people, applications, schedules, documents, giving, reports, settings
