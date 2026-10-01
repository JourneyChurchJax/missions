<?php
// Sample data for the preview. Every list here becomes a database table later.
// Real: trip names, dates, partner, budget lines, documents, guides, goal dates, Corey and Thomas.
// Sample: amounts raised, statuses, other people, donors, gifts, applicants, messages.

$today = new DateTimeImmutable('today');

$trips = [
    'belize' => [
        'slug' => 'belize', 'name' => 'Belize', 'ghost' => 'BELIZE', 'code' => 'BZ',
        'city' => 'Belize City, Belize', 'partner' => 'Adventures in Missions', 'leader' => 'Corey Rees',
        'start' => '2027-06-19', 'end' => '2027-06-25', 'dates' => 'June 19–25, 2027',
        'travelers' => 2, 'ready' => 0, 'max' => 12, 'raised' => 1240, 'goal' => 3800, 'per_person' => 1900,
        'budget' => 5682, 'spent' => 0, 'tasks_done' => 4, 'tasks_total' => 10,
    ],
    'israel' => [
        'slug' => 'israel', 'name' => 'Israel', 'ghost' => 'ISRAEL', 'code' => 'IL',
        'city' => 'Israel', 'partner' => '[Partner]', 'leader' => 'Sample Leader',
        'start' => '2027-05-19', 'end' => '2027-05-28', 'dates' => 'May 19–28, 2027',
        'travelers' => 14, 'ready' => 5, 'max' => 20, 'raised' => 8620, 'goal' => 41600, 'per_person' => 2970,
        'budget' => 41600, 'spent' => 3200, 'tasks_done' => 41, 'tasks_total' => 70,
    ],
];
foreach ($trips as &$t) { $t['days_away'] = (int)$today->diff(new DateTimeImmutable($t['start']))->format('%a'); }
unset($t);

$budget_lines = [
    ['Lodging, food and transportation', 'Adventures in Missions', 1890],
    ['Airfare', 'AFC Travel / American Airlines', 1702],
    ['Block party', 'God Cares Outreach', 650],
    ["Children's home VBS food", 'God Cares Outreach', 600],
    ['Home visits', '18 at $30', 540],
    ['Feeding program', 'God Cares Outreach', 300],
];

$team = [
    ['name' => 'Corey Rees', 'role' => 'Leader', 'passport' => 'Missing', 'passport_ok' => false, 'forms' => '1 of 2', 'medical' => 'Not started', 'medical_ok' => false, 'tasks' => '2 of 5', 'raised' => 600, 'goal' => 1900],
    ['name' => 'Thomas Sereno', 'role' => 'Traveler', 'passport' => 'Valid to 2034', 'passport_ok' => true, 'forms' => '1 of 2', 'medical' => 'On file', 'medical_ok' => true, 'tasks' => '2 of 5', 'raised' => 640, 'goal' => 1900],
];

$people = [
    ['Avery Collins', 'Israel', 'Traveler', '3 of 5', 1120, 'Linked'],
    ['Jordan Price', 'Israel', 'Traveler', '3 of 5', 980, 'Linked'],
    ['Morgan Hayes', 'Israel', 'Traveler', 'Ready', 2400, 'Linked'],
    ['Taylor Brooks', 'Israel', 'Leader', 'Ready', 2970, 'Linked'],
    ['Riley Foster', 'Israel', 'Traveler', '2 of 5', 450, '2 records to merge'],
    ['Corey Rees', 'Belize', 'Leader', '2 of 5', 600, 'Linked'],
    ['Thomas Sereno', 'Belize', 'Traveler', '2 of 5', 640, 'Linked'],
    ['Casey Morgan', 'Israel', 'Traveler', '4 of 5', 1760, 'Linked'],
    ['Jamie Ortiz', '—', 'Applicant', '—', 0, 'Linked'],
    ['Drew Bennett', 'Israel', 'Traveler', '1 of 5', 300, 'Not in Planning Center'],
];

$coming_up = [
    ['OCT', '12', 'Israel info night', '6:30 PM · [Room]', 'Israel'],
    ['NOV', '9', 'Belize team meeting', '12:30 PM · [Room]', 'Belize'],
    ['NOV', '15', 'Passport copies due', '9 of 16 uploaded', 'Both trips'],
];

$applicants = [
    ['name' => 'Jamie Ortiz', 'note' => 'Israel · first choice · references in', 'ago' => '2d'],
    ['name' => 'Sam Rivera', 'note' => 'Israel · 1 reference out', 'ago' => '4d'],
    ['name' => 'Alex Kim', 'note' => 'Israel · deposit paid', 'ago' => '6d'],
    ['name' => 'Chris Lane', 'note' => 'Israel · under 18 · parent signed', 'ago' => '9d'],
];

$gifts = [
    ['Sep 30', 'Maria L.', 'Thomas · Belize', 'Apple Pay', 'Covered', 100],
    ['Sep 29', 'Anonymous', 'Israel team', 'Bank · monthly', '$0.25', 50],
    ['Sep 28', 'The Daltons', 'Corey · Belize', 'Check #1042', '—', 250],
    ['Sep 27', 'Pat W.', 'Taylor · Israel', 'Card', 'Covered', 75],
    ['Sep 26', 'Lee H.', 'Morgan · Israel', 'Card · monthly', '$1.18', 40],
    ['Sep 25', 'Jo R.', 'Israel team', 'Cash', '—', 20],
];

$documents = [
    ['Tentative Belize ministry schedule', 'PDF', 'Updated by Corey'],
    ['AIM 5 objectives', 'PDF', 'From Adventures in Missions'],
    ['Belize country guide', 'PDF', 'Money, weather, what to pack'],
];
$guides = [
    ['Pre-trip training guide', ''],
    ['AIM ministry interaction guidelines', ''],
    ['AIM fundraising guide', ''],
    ['Applying for a passport', 'Clerk of Courts or the post office'],
];

$week = [
    ['SAT', '19', [['Fly out', '[Time] · American Airlines'], ['Arrive Belize City', '']]],
    ['SUN', '20', [['Church with partners', ''], ['Block party', 'God Cares Outreach']]],
    ['MON', '21', [['Home visits', ''], ['Feeding program', '']]],
    ['TUE', '22', [["Children's home VBS", '']]],
    ['WED', '23', [["Children's home VBS", '']]],
    ['THU', '24', [['Debrief and rest', '']]],
    ['FRI', '25', [['Fly home', '[Time]']]],
];

$milestones = [
    ['December 19', 10, 190],
    ['February 7', 50, 950],
    ['March 14', 100, 1900],
];

$me = ['name' => 'Thomas Sereno', 'first' => 'Thomas', 'raised' => 640, 'goal' => 1900, 'trip' => 'belize'];
