# config.php: lines to add

Your `config.php` is now in the `missions.journeychurch.org` folder (next to `public_html`). Open it in SiteGround File Manager (three dots → Edit) and add these lines inside the `return [ ... ];`, next to the settings already there. Don't remove what's already in the file.

Fill in the four blanks marked `FILL IN`. Everything else is ready.

```php
    'site_url' => 'https://missions.journeychurch.org',

    // Printed on tax statements
    'church_name' => 'Journey Church',
    'church_legal_name' => 'FILL IN: exact legal name from your IRS letter or Florida Sunbiz record',
    'church_address' => '6225-2 Lake Gray Blvd, Jacksonville, FL 32244',
    'church_ein' => 'FILL IN: 00-0000000',

    // Where alerts go when something breaks
    'alert_email' => 'adam@journeychurch.org',

    // Email name and reply address
    'mail_from' => 'missions@journeychurch.org',
    'mail_name' => 'Journey Missions',
    'mail_reply_to' => 'missions@journeychurch.org',

    // Key for the nightly job. Make up a long random password (your password manager can generate one).
    'cron_key' => 'FILL IN: long random password',

    // Planning Center person IDs that are always staff (optional; or use the Staff access switch in the app)
    // 'staff_pco_ids' => ['FILL IN'],
```

## Then set up the nightly job

SiteGround → **Devs → Cron Jobs** → add a job that runs once a day (for example 3:00 am) with this command, using the same `cron_key` you typed above:

```
curl -s "https://missions.journeychurch.org/cron.php?key=YOUR_CRON_KEY" > /dev/null
```

## Notes

- The church address came from your Planning Center campus record. Change it if statements should use a mailing address instead.
- `mail_from` must be an address on journeychurch.org. Until an email service (Postmark or SES) is set up, mail may land in spam.
- Leave out `data_key`. The site makes its own key in `data/secret.key`; back that file up in your password manager once it appears.
