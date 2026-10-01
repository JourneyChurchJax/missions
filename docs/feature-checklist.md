# Feature checklist

Everything ManagedMissions does today, so Journey Missions misses nothing.
Captured October 1, 2026 from managedmissions.com (home, FAQ, about, privacy, terms), public pages, the logged-in admin,
API docs, and 3 setup PDFs.

## Roles & access
- **Account Admin** (Global Admin; one Primary) — everything. Admin list: edit, remove, make primary, send invite, access level.
- **Groups** — organize trips by region/ministry; filter trips; apps can target groups.
- **Trip Admin** (all on trip) / **Trip Leader** (configurable) / **Participant** (sees only self; no budget/expenses; own contributions).
- **Traveling** flag per member — non-traveling leaders excluded from tasks, goals, contributions, budget split.
- **Leader Permissions matrix** (Hidden / Read-only / Editable) per feature: Documents, Links, Team Members, Flight Info,
  Meetings, Tasks&Goals, Budget, Expense, Contributions, Mission Apps, Background Checks, Story, Medical Info,
  Waive Application Deposit, Approve Public Profiles, Edit Public Profiles.
- Donor login: view past gifts, update info, download statements. Trip member "I don't have a login" → email set-password link.
- MFA (TOTP: 1Password/MS/Google Authenticator); admins can reset a member's password / turn off MFA. One login per person.

## Trips
- Dashboard: all trips w/ progress bar (raised of goal), departs/returns, filter Recent&Upcoming/Past/Cancelled, group, search.
- Create: from scratch (9-step wizard, step 1 Basic Info) or **clone** a trip (tasks, goals, budget…). Shift dates of reminders/tasks/goals/meetings when trip dates change.
- Trip fields: Name, Public Display Name, Departure/Return, Destination city + country, Description, Team Member Qualifications,
  Partnering Organization (None/Young Life/YWAM/AIM/Azusa Pacific/Other), Trip Member Cost (goal/person), Group,
  Application Deadline, Max Team Size, custom "About My Trip" text, coordinator email routing (Trip Admin/Leader/Account Admin),
  toggles: Budget tab, Expense tab, Disable Fundraising, Hide contribution amounts from participants, Hide progress on team page,
  Use Budget for fundraising graph, Send profile-update emails, Disable public profiles, Require admin approval to publish,
  Hide trip dates on profile, Tax-deductible donations, "Pay for my Trip" not tax-deductible + custom redirect URL,
  Income/Expense account numbers, Purpose Code, Import/Export Key, PCO Fund, Cancelled/Postponed/DonationsPaused/ApplicationsPaused.
- Active trip = current/upcoming; auto-archives to Past 30 days after return.
- Trip page tabs: Trip Details, Team Members, Flight Info, Story, Meetings, Tasks & Goals, Budget, Expenses, Fundraising, Public Profile.
- Trip Details: summary, Financial Summary (budget, expenses, contributions, balance), upcoming goals, **Links** and **Documents**
  (20MB, name, description, visible-to-participants flag), Actions (calendar, travel quotes, passport expediting, trip insurance).
- Trip Story: blog posts per trip, public "Our Story" page, filter, preview.
- Flight Info: AFC Travel / Fly For Good sync (Client Access Code + trip ID; deposit/due dates); travel-quote request to 13 agencies.

## People / team members
- Person profile: preferred/first/middle/last, address, gender (Unspecified/M/F), birth date, multiple emails (notify/primary),
  multiple phones (Mobile/Home/Work/Other), notes, import/export key, tags, data-verified flag, fundraising exception.
- Travel profile: passport name/number/issue/expiry/issuing country, nationality, airport, frequent flyer, seat pref,
  primary+secondary emergency contacts (name, relationship, phone, email), health concerns, diet, allergies, medications,
  other considerations, T-shirt size.
- Member page: tasks, member budget items, trip docs, member docs, total raised, per-person goal.
- Exports: Team data CSV, Travel Agent export, STEP (State Dept) export, Group Travel Roster PDF. Account people DB export; merge duplicates.
- Searchable tags (trip tags + people tags).

## Communication
- Email the team / individuals / filtered ("who haven't completed task X", "haven't met goal Y"); auto-fill trip data; invite emails.
- In-app Messages inbox (sent + received). Templated emails with merge codes ({accountName} {tripName} {tripLink} {firstName}…).

## Meetings
- Name, start/end datetime, location + address (auto-fill org address), notes, multi-trip create, attendance tracking, calendar view.

## Tasks & Goals
- Task types: Member, Leadership, Trip Admin, Data Verification, Document (upload to complete), Account Admin; options:
  member can self-complete, general (all trips), minors only (<18 by birthdate), by tag; attachments; scheduled reminders.
- Goals: fundraising milestones (Percent or Amount) with due dates, % complete per member; multi-trip create; send reminders.

## Budget & expenses
- Budget items: description, type (Airfare, Transportation-Other, Meals/Food, Taxes/Visas, Lodging, MISC, Supplies, Insurance,
  Debrief/Tourism), vendor, est. date, per-unit cost, qty, "tie quantity to team size". Breakdown: total, goal/person,
  fundraising exceptions, member budgets, calculated per-person goal; warns when goal/person out of sync. CSV export.
- Expenses: description, type, vendor, date, amount, currency, exchange rate; compare budget vs expenses.

## Fundraising & donations (core)
- **Public profile** per member (premium): display name, custom URL slug, photo, trip description, hide progress, hide social icons,
  submit-for-approval workflow, QR code, share FB/X. Shows name, destination, dates, "$X of $Y" bar, Donate, About My Trip,
  About [Church], contact info, up to 5 website links. Themes Dark/Light/custom CSS, logo.
- **Team donation page**: participant dropdown (Entire Team / each member), team goal.
- Donation form: title, suffix, first/last, address, country, city/state/zip, phone, email, amount, frequency (One Time/Monthly/
  Every 2 Weeks/Weekly) + start date (recurring for trip duration), anonymous, note, "cover processing fees", returning-donor login.
- Processors: Stripe (Connect; USD/CAD/AUD; ACH $.25; recurring), Pushpay, Vanco, URL redirect. Fee settings: CC %, CC fixed,
  Amex %, ACH fee; participant receives gross vs net. Text above/below form. Customizable thank-you/receipt email.
- Contributions: manual entry (deposit date, member or General, donor lookup/new, reference # / 'C' cash, amount, key, comments,
  tax-deductible, anonymous, exclude from exports); import from spreadsheet; summary per member; all contributions; deposits +
  deposit reports; exports (all, by member, single member, date range). Gross/Net/Fee, refunds, general (fund) contributions.
- Donor management: list/search, without email/mailing address filters, date range, merge, recurring donations list,
  year-end statements (email or print; custom text; email template), Stripe payouts list.

## Mission applications
- Flow: unique link → login/register → Personal Info → Travel Info → Preferred Trip (top 1 or top 3) → custom questions → submit →
  admin review (add to trip / mark reviewed) → welcome email; save & resume link.
- Builder: name, submitted message, expiration, published, profile picture required, trips (All/Specific/groups/None),
  hidden/required per standard field, references (1–5, typed, reference email + form), deposit (amount, tax-deductible,
  thank-you email, early-bird + code discounts %/fixed), custom field types (text, textarea, dropdown w/ choices, checkbox groups;
  label, sub-label, required; drag reorder; copy/delete). Fields lock once responses exist → duplicate. Responses:
  Unprocessed/Processed/In Progress; download. Config: notify emails, leaders can approve own trips.

## Integrations & settings
- PCO (export donors+contributions to Giving funds, date range, all/online/manual, default fund, notify email), CCB, Rock RMS,
  Checkr background checks, SignNow e-signature (templates), Imports (People basic/full, Contributions basic/full/Mission Connex,
  Donors, Member Budgets), REST API with Bearer key.
- Settings: subdomain, reply-to email, colors & logo, hide budget/expense/fundraising tabs, disable travel quotes/insurance/passport,
  flight preference fields, hide birth date, state validation, public account page (all trips, sort, search, date filters).

## API data model (for migration)
Endpoints: Account/Details; MissionTripAPI ActiveTrips/List/Get/Create/Update/Ensure; PersonAPI List/Get/Create/Update/Ensure;
MemberAPI AddMemberToTrip(Role Administrator/Leader/Participant)/GetMembersOfTrip/DeleteMemberFromTrip; PublicProfile list/Publish;
ToDo List/Get/Create/Update/Ensure/GetTodoTypes; Meeting List/Get/Create/Update/Ensure; ContributionAPI List/Create/Update;
FundContributionAPI List/Create/Update; DonorAPI List/Get/GetByMissionTrip/Create/Update/Ensure.
Status codes 0 Success…7 Invalid method. Every entity has ImportExportKey.

## Pricing reference
Free ≤5 trips (no online giving); Premium $29/mo; trip tiers 5→500; PCO/CCB +$10/mo; Rock $39/mo; SignNow $10/mo + $2/doc.
