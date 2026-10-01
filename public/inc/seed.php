<?php
// Starting data.
//   seed()      REAL data: only facts Journey already has (Belize details, Corey and Thomas, Israel dates). No sample people or amounts.
//   seed_demo() DEMO data: both trips fully filled in, sample travelers, progress, updates and photos. Turned on in Settings.

function seed_belize_trip(string $now): int {
    return insert('trips', [
        'slug' => 'belize', 'name' => 'Belize', 'public_name' => 'Belize 2027', 'city' => 'Belize City', 'country' => 'Belize',
        'partner' => 'Adventures in Missions', 'start_date' => '2027-06-19', 'end_date' => '2027-06-25',
        'description' => 'We are joining Adventures in Missions to serve alongside their ministry partners, reaching communities in Belize through their kids and families, showing them the love of Jesus, encouraging them to walk in faith, and growing together as the body of Christ.',
        'qualifications' => "Professing Christians\nGoing into 9th grade or older\nAble to lift 20 lbs and walk 2 miles on uneven ground\nLeaders need a clean background check\nPassport valid through December 25, 2027\nRead and sign the missions code of conduct",
        'cost_per_person' => 1900, 'max_team' => 12, 'app_deadline' => '2027-01-05', 'status' => 'active',
        'passport_valid_through' => '2027-12-25', 'created_at' => $now,
    ]);
}
function seed_belize_budget(int $bz): void {
    foreach ([['Lodging, food and transportation', 'Lodging', 'Adventures in Missions', 945, 2, 1, '2027-04-19'],
              ['Block party', 'Ministry', 'God Cares Outreach', 650, 1, 0, '2027-06-19'],
              ['Home visits', 'Ministry', 'God Cares Outreach', 30, 18, 0, '2027-06-19'],
              ['Feeding program', 'Ministry', 'God Cares Outreach', 300, 1, 0, '2027-06-19'],
              ["Children's home VBS food", 'Meals/Food', 'God Cares Outreach', 300, 2, 0, '2027-06-19'],
              ['Airfare', 'Airfare', 'AFC Travel / American Airlines', 851, 2, 1, '2027-06-19']] as [$d, $ty, $v, $u, $qty, $per, $dt]) {
        insert('budget', ['trip_id' => $bz, 'description' => $d, 'type' => $ty, 'vendor' => $v, 'unit_cost' => $u, 'qty' => $qty, 'per_traveler' => $per, 'est_date' => $dt]);
    }
    foreach ([['2026-12-19', 10], ['2027-02-07', 50], ['2027-03-14', 100]] as [$d, $p]) insert('goals', ['trip_id' => $bz, 'due_date' => $d, 'kind' => 'percent', 'amount' => $p]);
}
function seed_tasks(int $trip, string $now, string $shift = '+0 days'): array {
    $d = fn($x) => date('Y-m-d', strtotime("$x $shift"));
    return [
        'waiver' => insert('tasks', ['trip_id' => $trip, 'title' => 'Sign the liability waiver', 'type' => 'traveler', 'due_date' => $d('2026-10-15'), 'allow_self' => 1, 'created_at' => $now]),
        'ec' => insert('tasks', ['trip_id' => $trip, 'title' => 'Add your emergency contacts', 'description' => 'Add them on your profile.', 'type' => 'traveler', 'due_date' => $d('2026-10-15'), 'allow_self' => 1, 'created_at' => $now]),
        'passport' => insert('tasks', ['trip_id' => $trip, 'title' => 'Upload a passport copy', 'description' => 'A clear photo of the photo page.', 'type' => 'upload', 'due_date' => $d('2026-11-15'), 'allow_self' => 1, 'created_at' => $now]),
        'coc' => insert('tasks', ['trip_id' => $trip, 'title' => 'Sign the code of conduct', 'description' => 'Read it in Documents, then tap I agree.', 'type' => 'traveler', 'due_date' => $d('2026-12-01'), 'allow_self' => 1, 'created_at' => $now]),
        'verify' => insert('tasks', ['trip_id' => $trip, 'title' => 'Confirm your name and birth date', 'description' => 'Must match your passport exactly before airfare is ticketed.', 'type' => 'verify', 'due_date' => $d('2027-01-15'), 'allow_self' => 1, 'created_at' => $now]),
        'parent' => insert('tasks', ['trip_id' => $trip, 'title' => 'Parent consent form', 'description' => 'A parent or guardian signs this for travelers under 18.', 'type' => 'upload', 'due_date' => $d('2027-02-01'), 'minors_only' => 1, 'allow_self' => 1, 'created_at' => $now]),
        'air' => insert('tasks', ['trip_id' => $trip, 'title' => 'Book group airfare', 'type' => 'leader', 'due_date' => $d('2027-02-01'), 'created_at' => $now]),
    ];
}

function seed(PDO $pdo): void {
    $now = now();
    $bz = seed_belize_trip($now);
    $corey = insert('people', ['first_name' => 'Corey', 'last_name' => 'Rees', 'created_at' => $now]);
    $thomas = insert('people', ['first_name' => 'Thomas', 'last_name' => 'Sereno', 'created_at' => $now]);
    insert('members', ['trip_id' => $bz, 'person_id' => $corey, 'role' => 'leader', 'traveling' => 1, 'raised' => 0, 'created_at' => $now]);
    insert('members', ['trip_id' => $bz, 'person_id' => $thomas, 'role' => 'traveler', 'traveling' => 1, 'raised' => 0, 'created_at' => $now]);
    seed_belize_budget($bz);
    seed_tasks($bz, $now);
    foreach ([['Tentative Belize ministry schedule', '', 0], ['Missions code of conduct', 'Read and agree', 1], ['Missions trip liability waiver', 'Read and agree', 1], ['AIM 5 objectives', 'From Adventures in Missions', 0], ['Belize country guide', '', 0]] as [$title, $note, $ack]) {
        insert('files', ['trip_id' => $bz, 'title' => $title, 'note' => $note, 'visible' => 1, 'must_ack' => $ack, 'kind' => 'doc', 'created_at' => $now]);
    }
    foreach (['Pre-trip training guide', 'AIM ministry interaction guidelines', 'AIM fundraising guide', 'Applying for a passport'] as $title) {
        insert('files', ['trip_id' => $bz, 'title' => $title, 'url' => '', 'visible' => 1, 'kind' => 'link', 'created_at' => $now]);
    }
    foreach (GUIDE_SECTIONS as $k => $label) insert('guide', ['trip_id' => $bz, 'section' => $k, 'body' => '[' . $label . ': add details]', 'updated_at' => $now]);
    q("UPDATE guide SET body = ? WHERE trip_id = ? AND section = 'entry'", ["Passport valid through December 25, 2027.\n[Entry requirements for U.S. citizens]", $bz]);
    q("UPDATE guide SET body = ? WHERE trip_id = ? AND section = 'contacts'", ["Trip leader: Corey Rees\n[Adventures in Missions in-country contact]\n[Journey Church office phone]", $bz]);
    insert('flights', ['trip_id' => $bz, 'direction' => 'out', 'airline' => 'American Airlines', 'flight_no' => '[TBD]', 'from_code' => 'JAX', 'to_code' => 'BZE', 'departs_at' => '2027-06-19 00:00:00', 'notes' => 'Booked through AFC Travel']);
    insert('flights', ['trip_id' => $bz, 'direction' => 'home', 'airline' => 'American Airlines', 'flight_no' => '[TBD]', 'from_code' => 'BZE', 'to_code' => 'JAX', 'departs_at' => '2027-06-25 00:00:00']);
    $il = insert('trips', ['slug' => 'israel', 'name' => 'Israel', 'public_name' => 'Israel 2027', 'country' => 'Israel', 'start_date' => '2027-05-19', 'end_date' => '2027-05-28', 'status' => 'active', 'created_at' => $now]);
    insert('activity', ['trip_id' => $bz, 'who' => 'Setup', 'what' => 'Brought the Belize trip over from ManagedMissions', 'created_at' => $now]);
    insert('activity', ['trip_id' => $il, 'who' => 'Setup', 'what' => 'Created the Israel trip with its dates', 'created_at' => $now]);
    seed_apps_real();
    seed_phase5(false);
}

function seed_demo(PDO $pdo): void {
    $now = now();
    $person = fn(array $p) => insert('people', $p + ['created_at' => $now]);
    $member = fn(int $t, int $p, string $role, float $raised, array $x = []) => insert('members', ['trip_id' => $t, 'person_id' => $p, 'role' => $role, 'traveling' => 1, 'raised' => $raised, 'created_at' => $now] + $x);
    $photo = function (int $t, string $file, string $title) use ($now) { insert('files', ['trip_id' => $t, 'title' => $title, 'url' => "/assets/demo/$file", 'visible' => 1, 'kind' => 'photo', 'created_at' => $now]); };
    $done = fn(int $task, int $p, string $when) => insert('task_done', ['task_id' => $task, 'person_id' => $p, 'done_at' => $when]);

    // ================= Belize =================
    $bz = seed_belize_trip($now);
    seed_belize_budget($bz);
    q('UPDATE budget SET qty = 8 WHERE trip_id = ? AND per_traveler = 0 AND description = ?', [$bz, "Children's home VBS food"]);
    foreach ([['belize-1.jpg', 'Maya ruins'], ['belize-4.jpg', 'Caribbean shallows'], ['belize-3.jpg', 'Jungle river'], ['belize-2.jpg', 'Beach house'], ['belize-5.jpg', 'Great Blue Hole'], ['belize-6.jpg', 'Waterfall']] as [$f, $title]) $photo($bz, $f, $title);
    $team = [
        ['Corey', 'Rees', 'leader', 1900, ['passport_expires' => '2031-04-02', 'ec1_name' => 'Dana Rees', 'ec1_rel' => 'Spouse', 'ec1_phone' => '(904) 555-0110', 'shirt' => "Men's L", 'gender' => 'male', 'phone' => '(904) 555-0101', 'email' => 'corey@example.com'], ['room' => 'Guys', 'seat' => 'Van 1', 'confirmation' => 'KXQ4TR']],
        ['Thomas', 'Sereno', 'traveler', 1260, ['passport_expires' => '2034-02-01', 'ec1_name' => 'Sample Contact', 'ec1_rel' => 'Spouse', 'ec1_phone' => '(904) 555-0120', 'shirt' => "Men's 3XL", 'gender' => 'male', 'meds' => 'Two daily prescriptions', 'email' => 'thomas@example.com'], ['room' => 'Guys', 'seat' => 'Van 1', 'confirmation' => 'KXQ4TR']],
        ['Maya', 'Bennett', 'leader', 1900, ['passport_expires' => '2030-08-12', 'ec1_name' => 'Chris Bennett', 'ec1_rel' => 'Spouse', 'ec1_phone' => '(904) 555-0130', 'shirt' => "Women's M", 'gender' => 'female', 'allergies' => 'Penicillin'], ['room' => 'Girls', 'seat' => 'Van 2', 'confirmation' => 'KXQ4TR']],
        ['Elijah', 'Grant', 'traveler', 1525, ['passport_expires' => '2029-01-20', 'ec1_name' => 'Renee Grant', 'ec1_rel' => 'Parent/Guardian', 'ec1_phone' => '(904) 555-0140', 'shirt' => "Men's M", 'gender' => 'male', 'birth_date' => '2010-05-04', 'diet' => 'Vegetarian'], ['room' => 'Guys', 'seat' => 'Van 1']],
        ['Sofia', 'Ramirez', 'traveler', 980, ['passport_expires' => '2032-03-15', 'ec1_name' => 'Ana Ramirez', 'ec1_rel' => 'Parent/Guardian', 'ec1_phone' => '(904) 555-0150', 'shirt' => "Women's S", 'gender' => 'female', 'birth_date' => '2009-11-22', 'allergies' => 'Peanuts (carries an EpiPen)'], ['room' => 'Girls', 'seat' => 'Van 2']],
        ['Noah', 'Whitfield', 'traveler', 640, ['shirt' => "Men's XL", 'gender' => 'male'], ['room' => 'Guys', 'seat' => 'Van 2']],
        ['Grace', 'Okafor', 'traveler', 1900, ['passport_expires' => '2033-06-30', 'ec1_name' => 'Ifeoma Okafor', 'ec1_rel' => 'Parent/Guardian', 'ec1_phone' => '(904) 555-0170', 'shirt' => "Women's M", 'gender' => 'female'], ['room' => 'Girls', 'seat' => 'Van 1', 'confirmation' => 'KXQ4TR']],
        ['Lucas', 'Fernandez', 'traveler', 310, ['passport_expires' => '2027-09-01', 'shirt' => "Men's L", 'gender' => 'male'], ['room' => 'Guys', 'seat' => 'Van 2']],
    ];
    $ids = [];
    foreach ($team as [$f, $l, $role, $raised, $p, $m]) { $pid = $person(['first_name' => $f, 'last_name' => $l] + $p); $member($bz, $pid, $role, $raised, $m); $ids[] = $pid; }
    $tk = seed_tasks($bz, $now);
    foreach ($ids as $i => $pid) {
        $done($tk['waiver'], $pid, '2026-09-1' . ($i % 9) . ' 19:00:00');
        if ($i !== 5) $done($tk['ec'], $pid, '2026-09-20 18:30:00');
        if (in_array($i, [0, 1, 2, 6], true)) $done($tk['passport'], $pid, '2026-09-28 12:00:00');
        if (in_array($i, [0, 2, 6], true)) $done($tk['coc'], $pid, '2026-09-29 08:15:00');
        if (in_array($i, [0, 6], true)) $done($tk['verify'], $pid, '2026-09-30 21:00:00');
    }
    $past = insert('meetings', ['trip_id' => $bz, 'title' => 'Kickoff meeting', 'starts_at' => '2026-09-13 12:30:00', 'ends_at' => '2026-09-13 14:00:00', 'location' => 'Room B133', 'notes' => 'Trip overview, fundraising plan and passports.']);
    foreach (array_slice($ids, 0, 7) as $pid) insert('attendance', ['meeting_id' => $past, 'person_id' => $pid, 'present' => 1]);
    insert('meetings', ['trip_id' => $bz, 'title' => 'Team meeting', 'starts_at' => '2026-11-08 12:30:00', 'ends_at' => '2026-11-08 14:00:00', 'location' => 'Room B133', 'notes' => 'Bring your passport if you have not uploaded it yet.']);
    insert('meetings', ['trip_id' => $bz, 'title' => 'VBS planning night', 'starts_at' => '2027-03-04 18:30:00', 'ends_at' => '2027-03-04 20:30:00', 'location' => 'Fellowship hall', 'notes' => 'We build the VBS crafts and skits together.']);
    insert('meetings', ['trip_id' => $bz, 'title' => 'Packing night and commissioning', 'starts_at' => '2027-06-13 18:00:00', 'ends_at' => '2027-06-13 20:00:00', 'location' => 'Main auditorium', 'notes' => 'Pack the team bins, then the church prays over the team.']);
    foreach ([['Belize ministry schedule', 'Week at a glance from Adventures in Missions', 0], ['Missions code of conduct', 'Read and agree before December 1', 1], ['Missions trip liability waiver', 'Read and agree', 1], ['Packing list', 'Print it and check things off', 0], ['AIM 5 objectives', 'From Adventures in Missions', 0]] as [$title, $note, $ack]) {
        insert('files', ['trip_id' => $bz, 'title' => $title, 'note' => $note, 'visible' => 1, 'must_ack' => $ack, 'kind' => 'doc', 'created_at' => $now]);
    }
    foreach ([['Pre-trip training videos', 'Four short videos, about 10 minutes each', 'https://www.journeychurch.org'], ['Applying for a passport', 'Clerk of Courts or the post office', 'https://travel.state.gov/content/travel/en/passports.html'], ['Belize travel advisory', 'From the U.S. State Department', 'https://travel.state.gov']] as [$title, $note, $url]) {
        insert('files', ['trip_id' => $bz, 'title' => $title, 'note' => $note, 'url' => $url, 'visible' => 1, 'kind' => 'link', 'created_at' => $now]);
    }
    $coc = (int)val("SELECT id FROM files WHERE trip_id = ? AND title = 'Missions code of conduct'", [$bz]);
    foreach ([$ids[0], $ids[2], $ids[6]] as $pid) insert('file_acks', ['file_id' => $coc, 'person_id' => $pid, 'opened_at' => '2026-09-29 08:00:00', 'acked_at' => '2026-09-29 08:15:00']);
    $bz_guide = [
        'airport' => "Jacksonville International (JAX), American Airlines counter\nMeet at 4:15 AM on Saturday, June 19\nBring your passport and one checked bag with team supplies",
        'lodging' => "Adventures in Missions ministry base, Belize City\nShared rooms with bunks, fans and running water\nThe team eats breakfast and dinner together at the base",
        'contacts' => "Trip leader: Corey Rees, (904) 555-0101\nCo-leader: Maya Bennett\nAdventures in Missions host: Sample Host, +501 555 0100\nJourney Church office: (904) 555-0199",
        'packing' => "Passport and a copy of it\n5 modest ministry outfits\nComfortable closed-toe shoes and sandals\nRefillable water bottle\nSunscreen and bug spray\nRain jacket\nBible and journal\nMedications in their original bottles",
        'wear' => "Ministry days: knee-length shorts or skirts, T-shirts with sleeves\nChurch Sunday: nice casual\nNo tank tops or clothing with slogans",
        'money' => "U.S. dollars are accepted almost everywhere\n2 Belize dollars = 1 U.S. dollar\nBring $50 to $100 in small bills for snacks and souvenirs",
        'weather' => "Hot and humid, highs near 88°F\nJune is the start of the rainy season: expect quick afternoon showers",
        'power' => "Same plugs and 110 volts as the U.S. No adapter needed.",
        'phone' => "Wi-Fi at the base in the evenings\nCheck your carrier's international plan before you go\nFamilies get a daily update from the leaders",
        'health' => "Drink bottled or filtered water only\nCheck with your doctor about recommended vaccines\nTravel medical insurance is included in your trip cost",
        'entry' => "Passport valid through December 25, 2027\nU.S. citizens do not need a visa for a short visit",
        'safety' => "Always stay with your buddy\nLeaders keep everyone's passports in a lockbox at the base\nIn an emergency, find Corey or Maya first",
    ];
    foreach ($bz_guide as $k => $body) insert('guide', ['trip_id' => $bz, 'section' => $k, 'body' => $body, 'updated_at' => $now]);
    foreach ([['2027-06-19', '6:00 AM', 'Fly JAX to Miami', 'AA 1820'], ['2027-06-19', '10:45 AM', 'Fly Miami to Belize City', 'AA 1311'], ['2027-06-19', '6:30 PM', 'Welcome dinner and orientation', 'AIM base'],
              ['2027-06-20', '9:00 AM', 'Church with our partners', ''], ['2027-06-20', '3:00 PM', 'Neighborhood block party', 'God Cares Outreach'],
              ['2027-06-21', '9:00 AM', 'Home visits', 'In teams of three'], ['2027-06-21', '4:00 PM', 'Feeding program', ''],
              ['2027-06-22', '8:30 AM', "Children's home VBS, day 1", ''], ['2027-06-23', '8:30 AM', "Children's home VBS, day 2", ''],
              ['2027-06-24', '9:00 AM', 'Debrief and a day at the Maya ruins', 'Altun Ha'], ['2027-06-25', '12:15 PM', 'Fly home', 'AA 1312, home by 9:30 PM']] as [$d, $tm, $title, $det]) {
        insert('itinerary', ['trip_id' => $bz, 'day' => $d, 'time' => $tm, 'title' => $title, 'detail' => $det]);
    }
    insert('flights', ['trip_id' => $bz, 'direction' => 'out', 'airline' => 'American Airlines', 'flight_no' => 'AA 1820', 'from_code' => 'JAX', 'to_code' => 'MIA', 'departs_at' => '2027-06-19 06:00:00', 'arrives_at' => '2027-06-19 07:20:00', 'notes' => 'Meet at the AA counter at 4:15 AM']);
    insert('flights', ['trip_id' => $bz, 'direction' => 'out', 'airline' => 'American Airlines', 'flight_no' => 'AA 1311', 'from_code' => 'MIA', 'to_code' => 'BZE', 'departs_at' => '2027-06-19 10:45:00', 'arrives_at' => '2027-06-19 11:55:00']);
    insert('flights', ['trip_id' => $bz, 'direction' => 'home', 'airline' => 'American Airlines', 'flight_no' => 'AA 1312', 'from_code' => 'BZE', 'to_code' => 'MIA', 'departs_at' => '2027-06-25 12:15:00', 'arrives_at' => '2027-06-25 15:20:00']);
    insert('flights', ['trip_id' => $bz, 'direction' => 'home', 'airline' => 'American Airlines', 'flight_no' => 'AA 2289', 'from_code' => 'MIA', 'to_code' => 'JAX', 'departs_at' => '2027-06-25 18:05:00', 'arrives_at' => '2027-06-25 19:25:00']);
    foreach ([['Welcome to the Belize team', 'So glad you are going. Your checklist on the Trip page shows what to do first.', '2026-09-12 10:00:00'],
              ['Kickoff recap', 'Thanks for coming Sunday. Slides and the fundraising plan are in Documents.', '2026-09-14 09:00:00'],
              ['Bring your passport November 8', "If you haven't uploaded it yet, bring it to the meeting and we'll scan it.", '2026-10-01 09:41:00']] as [$title, $body, $when]) {
        insert('announcements', ['trip_id' => $bz, 'title' => $title, 'body' => $body, 'author' => 'Corey Rees', 'created_at' => $when]);
    }

    // ================= Israel =================
    $il = insert('trips', ['slug' => 'israel', 'name' => 'Israel', 'public_name' => 'Israel 2027', 'city' => 'Jerusalem', 'country' => 'Israel', 'partner' => 'Sample Tours',
        'start_date' => '2027-05-19', 'end_date' => '2027-05-28', 'cost_per_person' => 3950, 'max_team' => 24, 'app_deadline' => '2026-12-15', 'status' => 'active',
        'passport_valid_through' => '2027-11-28', 'group_name' => 'Adults',
        'description' => 'Walk where Jesus walked: Galilee, Jerusalem and the Judean wilderness, with daily teaching and worship along the way.',
        'qualifications' => "Adults 18 and older\nAble to walk 3 to 5 miles a day, with hills and stairs\nPassport valid through November 28, 2027", 'created_at' => $now]);
    foreach ([['israel-1.jpg', 'Old City from the Mount of Olives'], ['israel-2.jpg', 'Old City at golden hour'], ['israel-5.jpg', 'Judean hills'], ['israel-3.jpg', 'Old City stairs'], ['israel-6.jpg', 'Old City passage'], ['israel-4.jpg', 'Jerusalem at night']] as [$f, $title]) $photo($il, $f, $title);
    $names = [['Taylor', 'Brooks', 'leader', 3950], ['Avery', 'Collins', 'traveler', 2100], ['Jordan', 'Price', 'traveler', 1800], ['Morgan', 'Hayes', 'traveler', 3950], ['Riley', 'Foster', 'traveler', 900],
              ['Casey', 'Morgan', 'traveler', 3100], ['Drew', 'Bennett', 'traveler', 600], ['Hannah', 'Lee', 'traveler', 2750], ['Marcus', 'Hill', 'traveler', 3950], ['Priya', 'Shah', 'traveler', 1500]];
    $iids = [];
    foreach ($names as $i => [$f, $l, $role, $raised]) {
        $pid = $person(['first_name' => $f, 'last_name' => $l, 'shirt' => ['S', 'M', 'L', 'XL'][$i % 4], 'passport_expires' => $i % 3 ? '2031-0' . (1 + $i % 8) . '-15' : null, 'ec1_name' => $i % 4 ? 'Sample Contact' : null]);
        $member($il, $pid, $role, $raised); $iids[] = $pid;
    }
    $itk = seed_tasks($il, $now, '-1 month');
    foreach ($iids as $i => $pid) { $done($itk['waiver'], $pid, '2026-09-05 10:00:00'); if ($i % 4) $done($itk['ec'], $pid, '2026-09-08 10:00:00'); if ($i % 3) $done($itk['passport'], $pid, '2026-09-25 10:00:00'); if ($i % 2) $done($itk['coc'], $pid, '2026-09-27 10:00:00'); }
    insert('goals', ['trip_id' => $il, 'due_date' => '2026-11-30', 'kind' => 'percent', 'amount' => 25]);
    insert('goals', ['trip_id' => $il, 'due_date' => '2027-01-31', 'kind' => 'percent', 'amount' => 60]);
    insert('goals', ['trip_id' => $il, 'due_date' => '2027-03-15', 'kind' => 'percent', 'amount' => 100]);
    insert('meetings', ['trip_id' => $il, 'title' => 'Israel info night', 'starts_at' => '2026-10-12 18:30:00', 'ends_at' => '2026-10-12 20:00:00', 'location' => 'Room B133']);
    insert('meetings', ['trip_id' => $il, 'title' => 'Bible background: the Gospels in their land', 'starts_at' => '2027-01-24 18:30:00', 'ends_at' => '2027-01-24 20:00:00', 'location' => 'Fellowship hall']);
    insert('budget', ['trip_id' => $il, 'description' => 'Tour package: hotels, breakfast and dinner, guide, bus', 'type' => 'Lodging', 'vendor' => 'Sample Tours', 'unit_cost' => 2650, 'qty' => 1, 'per_traveler' => 1, 'est_date' => '2027-02-01']);
    insert('budget', ['trip_id' => $il, 'description' => 'Airfare JAX to Tel Aviv', 'type' => 'Airfare', 'vendor' => 'Sample Airline', 'unit_cost' => 1200, 'qty' => 1, 'per_traveler' => 1, 'est_date' => '2027-01-15']);
    insert('budget', ['trip_id' => $il, 'description' => 'Tips and entry fees', 'type' => 'MISC', 'vendor' => '', 'unit_cost' => 100, 'qty' => 1, 'per_traveler' => 1, 'est_date' => '2027-05-19']);
    $il_guide = [
        'airport' => "Jacksonville International (JAX)\nMeet at 1:30 PM on Wednesday, May 19 by the check-in counters",
        'lodging' => "Tiberias by the Sea of Galilee for 3 nights\nJerusalem for 5 nights\nHotels with breakfast and dinner included",
        'contacts' => "Trip leader: Taylor Brooks\nTour guide: Sample Guide\nJourney Church office: (904) 555-0199",
        'packing' => "Passport and a copy\nGood walking shoes, already broken in\nHat and sunscreen\nLight layers for cool evenings\nModest clothes for holy sites (shoulders and knees covered)\nSmall daypack and water bottle",
        'wear' => "Holy sites require covered shoulders and knees\nA light scarf is handy for women",
        'money' => "Israeli shekels, though U.S. dollars and cards work in most places\nBring $150 to $250 for lunches and souvenirs",
        'weather' => "Late May is warm and dry\nHighs in the 80s, cooler in Jerusalem evenings",
        'power' => "230 volts with type H and C plugs\nBring a plug adapter. Most phone chargers handle 230 V.",
        'phone' => "Hotel Wi-Fi every night\nAn international plan or eSIM is the easiest way to stay connected",
        'health' => "Tap water is safe\nHydrate often: we walk a lot in the sun\nTravel insurance is included",
        'entry' => "Passport valid through November 28, 2027\nU.S. citizens apply online for an ETA-IL travel authorization before the trip. We'll walk you through it.",
        'safety' => "Stay with the group in crowded places\nKeep your passport in the hotel safe\nFollow your guide's instructions at all sites",
    ];
    foreach ($il_guide as $k => $body) insert('guide', ['trip_id' => $il, 'section' => $k, 'body' => $body, 'updated_at' => $now]);
    foreach ([['2027-05-19', '5:10 PM', 'Fly to Tel Aviv overnight', ''], ['2027-05-20', 'Afternoon', 'Arrive and drive to Galilee', ''], ['2027-05-21', 'All day', 'Sea of Galilee boat ride, Capernaum, Mount of Beatitudes', ''],
              ['2027-05-22', 'All day', 'Nazareth and the Jordan River', ''], ['2027-05-23', 'All day', 'Drive to Jerusalem through the Jordan Valley', ''], ['2027-05-24', 'All day', 'Mount of Olives, Garden of Gethsemane', ''],
              ['2027-05-25', 'All day', 'Old City, Pools of Bethesda, the Western Wall', ''], ['2027-05-26', 'All day', 'Masada and the Dead Sea', ''], ['2027-05-27', 'Morning', 'Garden Tomb communion service', ''],
              ['2027-05-28', 'Morning', 'Fly home', '']] as [$d, $tm, $title, $det]) {
        insert('itinerary', ['trip_id' => $il, 'day' => $d, 'time' => $tm, 'title' => $title, 'detail' => $det]);
    }
    insert('flights', ['trip_id' => $il, 'direction' => 'out', 'airline' => 'Sample Airline', 'flight_no' => 'SA 100', 'from_code' => 'JAX', 'to_code' => 'TLV', 'departs_at' => '2027-05-19 17:10:00', 'arrives_at' => '2027-05-20 12:30:00']);
    insert('flights', ['trip_id' => $il, 'direction' => 'home', 'airline' => 'Sample Airline', 'flight_no' => 'SA 101', 'from_code' => 'TLV', 'to_code' => 'JAX', 'departs_at' => '2027-05-28 10:00:00', 'arrives_at' => '2027-05-28 19:45:00']);
    foreach (['Israel trip packet', 'Holy sites dress guide'] as $title) insert('files', ['trip_id' => $il, 'title' => $title, 'visible' => 1, 'kind' => 'doc', 'created_at' => $now]);
    insert('announcements', ['trip_id' => $il, 'title' => 'Info night October 12', 'body' => 'Bring a friend who is thinking about going.', 'author' => 'Taylor Brooks', 'created_at' => '2026-09-30 15:00:00']);

    insert('people', ['first_name' => 'Jamie', 'last_name' => 'Ortiz', 'tags' => 'Applicant', 'created_at' => $now]);
    foreach ([[$bz, 'Corey Rees', 'Posted the packing list'], [$bz, 'Grace Okafor', 'Uploaded: Upload a passport copy'], [$il, 'Taylor Brooks', 'Scheduled Israel info night'], [$bz, 'Corey Rees', 'Posted an announcement']] as [$t, $who, $what]) {
        insert('activity', ['trip_id' => $t, 'who' => $who, 'what' => $what, 'created_at' => $now]);
    }
    seed_apps_demo();
    seed_money_demo();
    seed_comms_demo();
    seed_phase5(true);
}

// ---------------- Applications (phase 2) ----------------
function seed_app_questions(int $form): void {
    $qs = [
        ['long', 'Tell us how you came to know Jesus', 'A few sentences is plenty.', '', 1],
        ['long', 'Why do you want to go on this trip?', '', '', 1],
        ['yesno', 'Have you been on a mission trip before?', '', '', 1],
        ['short', 'If yes, where and when?', '', '', 0],
        ['checkboxes', 'Where would you love to serve?', 'Pick any that fit.', "Kids and VBS\nWorship and music\nConstruction and work projects\nPrayer and home visits\nMedical or first aid\nPhotos and storytelling", 0],
        ['choice', 'How did you hear about this trip?', '', "Sunday announcement\nA friend\nSocial media\nSmall group\nOther", 0],
        ['long', 'Anything else we should know?', 'Health, schedule conflicts, questions for us.', '', 0],
    ];
    foreach ($qs as $i => [$k, $l, $h, $o, $r]) insert('app_questions', ['form_id' => $form, 'sort' => $i, 'kind' => $k, 'label' => $l, 'help' => $h, 'options' => $o, 'required' => $r]);
}

function seed_apps_real(): void {
    if (val('SELECT COUNT(*) FROM app_forms')) return;
    $bz = (int)val("SELECT id FROM trips WHERE slug = 'belize'");
    $f = insert('app_forms', ['name' => 'Belize 2027', 'slug' => 'belize-2027', 'intro' => 'Apply to serve with Journey Church in Belize City, June 19–25, 2027.', 'closes_on' => '2027-01-05', 'published' => 0,
        'trip_mode' => 'specific', 'trip_ids' => (string)$bz, 'choices' => 1, 'refs_required' => 0, 'ref_types' => '', 'deposit' => 0, 'deposit_tax' => 1, 'photo_required' => 0,
        'submitted_message' => "Your application is in. We'll email you once it has been reviewed.", 'created_at' => now()]);
    seed_app_questions($f);
}

function seed_apps_demo(): void {
    if (val('SELECT COUNT(*) FROM app_forms')) return;
    $now = now();
    $bz = (int)val("SELECT id FROM trips WHERE slug = 'belize'");
    $il = (int)val("SELECT id FROM trips WHERE slug = 'israel'");
    $fb = insert('app_forms', ['name' => 'Belize 2027', 'slug' => 'belize-2027', 'intro' => 'Apply to serve with Journey Church in Belize City, June 19–25, 2027. Students going into 9th grade and older, and adults.', 'closes_on' => '2027-01-05', 'published' => 1,
        'trip_mode' => 'specific', 'trip_ids' => (string)$bz, 'choices' => 1, 'refs_required' => 2, 'ref_types' => "Pastor or ministry leader\nFriend or mentor", 'deposit' => 100, 'deposit_tax' => 1, 'photo_required' => 0,
        'submitted_message' => "Your application is in. We'll email your references and let you know once it has been reviewed.", 'created_at' => $now]);
    seed_app_questions($fb);
    insert('app_discounts', ['form_id' => $fb, 'code' => '', 'kind' => 'amount', 'amount' => 25, 'early_bird' => 1, 'expires_on' => '2026-11-01']);
    insert('app_discounts', ['form_id' => $fb, 'code' => 'FAMILY', 'kind' => 'percent', 'amount' => 50, 'early_bird' => 0, 'expires_on' => '2027-01-05']);
    $fi = insert('app_forms', ['name' => 'Israel 2027', 'slug' => 'israel-2027', 'intro' => 'Walk where Jesus walked, May 19–28, 2027. Adults 18 and older.', 'closes_on' => '2026-12-15', 'published' => 1,
        'trip_mode' => 'specific', 'trip_ids' => (string)$il, 'choices' => 1, 'refs_required' => 1, 'ref_types' => 'Pastor or small group leader', 'deposit' => 250, 'deposit_tax' => 0, 'photo_required' => 0,
        'submitted_message' => "Thanks for applying. Taylor will be in touch within a week.", 'created_at' => $now]);
    seed_app_questions($fi);

    $qb = app_questions($fb); $qi = app_questions($fi);
    $ans = function (array $qs, array $vals): string { $o = []; foreach ($qs as $i => $q) if (isset($vals[$i])) $o[$q['id']] = $vals[$i]; return json_encode($o); };
    $mk = function (int $form, array $qs, string $first, string $last, string $status, array $vals, int $trip, array $x = []) use ($now, $ans) {
        $pid = (int)(val('SELECT id FROM people WHERE first_name = ? AND last_name = ?', [$first, $last]) ?: insert('people', ['first_name' => $first, 'last_name' => $last, 'email' => strtolower($first) . '@example.com', 'tags' => 'Applicant', 'created_at' => $now]));
        return insert('applications', ['form_id' => $form, 'person_id' => $pid, 'status' => $status, 'token' => new_token(), 'step' => $status === 'draft' ? 'questions' : 'review', 'choice1' => $trip,
            'answers' => $ans($qs, $vals), 'deposit_due' => $x['deposit'] ?? 100, 'deposit_status' => $x['dep'] ?? 'due', 'assigned_trip_id' => $status === 'approved' ? $trip : null,
            'submitted_at' => $status === 'draft' ? null : ($x['sub'] ?? '2026-09-25 19:00:00'), 'decided_at' => in_array($status, ['approved', 'declined', 'waitlist'], true) ? '2026-09-28 10:00:00' : null,
            'decided_by' => in_array($status, ['approved', 'declined', 'waitlist'], true) ? 'Corey Rees' : null, 'decision_note' => $x['note'] ?? null, 'created_at' => '2026-09-20 18:00:00', 'updated_at' => $now]);
    };
    $ref = function (int $app, string $type, string $name, bool $in) use ($now) {
        insert('app_refs', ['application_id' => $app, 'ref_type' => $type, 'name' => $name, 'email' => strtolower(str_replace(' ', '.', $name)) . '@example.com', 'token' => new_token(),
            'status' => $in ? 'received' : 'requested', 'requested_at' => '2026-09-26 09:00:00', 'received_at' => $in ? '2026-09-29 20:00:00' : null,
            'answers' => $in ? json_encode(['known' => 'I have been their small group leader for three years.', 'faith' => 'Steady and growing. They serve every week without being asked.', 'team' => 'Calm, kind and quick to help. Great with kids.', 'concerns' => 'None that I know of.', 'recommend' => 'Yes, without reservation']) : null]);
    };
    $a1 = $mk($fb, $qb, 'Jamie', 'Ortiz', 'submitted', ['I gave my life to Jesus at youth camp when I was 14.', 'I want to love kids well and see how God is moving in Belize.', 'No', '', ['Kids and VBS', 'Worship and music'], 'A friend', ''], $bz, ['sub' => '2026-09-29 21:10:00', 'dep' => 'paid', 'deposit' => 75]);
    $ref($a1, 'Pastor or ministry leader', 'Pastor Sample', true); $ref($a1, 'Friend or mentor', 'Kelly Sample', true);
    $a2 = $mk($fb, $qb, 'Sam', 'Rivera', 'submitted', ['My grandmother took me to church every Sunday, and I made my faith my own in high school.', 'Serving is how I grow. I want to use my Spanish.', 'Yes', 'Dominican Republic, 2025', ['Prayer and home visits', 'Construction and work projects'], 'Sunday announcement', 'I have a soccer tournament the week before.'], $bz, ['sub' => '2026-09-27 16:00:00']);
    $ref($a2, 'Pastor or ministry leader', 'Pastor Sample', true); $ref($a2, 'Friend or mentor', 'Alex Sample', false);
    $a3 = $mk($fi, $qi, 'Alex', 'Kim', 'submitted', ['Through a college ministry.', 'To see the places I read about and come home teaching my kids.', 'No', '', ['Photos and storytelling'], 'Small group', ''], $il, ['deposit' => 250, 'dep' => 'paid']);
    $ref($a3, 'Pastor or small group leader', 'Jordan Sample', true);
    $a4 = $mk($fi, $qi, 'Chris', 'Lane', 'submitted', ['I was baptized at Journey last Easter.', 'I want my faith to come alive in a new way.', 'No', '', [], 'Social media', ''], $il, ['deposit' => 250]);
    $ref($a4, 'Pastor or small group leader', 'Morgan Sample', false);
    $mk($fb, $qb, 'Avery', 'Stone', 'draft', ['Still writing this.'], $bz, ['dep' => 'none']);
    $mk($fb, $qb, 'Riley', 'Park', 'draft', [], $bz, ['dep' => 'none']);
    $a7 = $mk($fb, $qb, 'Grace', 'Okafor', 'approved', ['At home with my family.', 'I loved serving kids at VBS this summer.', 'No', '', ['Kids and VBS'], 'A friend', ''], $bz, ['dep' => 'paid', 'note' => 'Great fit for the VBS team.']);
    $ref($a7, 'Pastor or ministry leader', 'Pastor Sample', true); $ref($a7, 'Friend or mentor', 'Taylor Sample', true);
    $a8 = $mk($fb, $qb, 'Dylan', 'Moore', 'waitlist', ['Through my older brother.', 'I want to go somewhere new and help.', 'No', '', ['Construction and work projects'], 'A friend', ''], $bz, ['dep' => 'due', 'note' => 'Team is nearly full. Offer the next open spot.']);
    $ref($a8, 'Pastor or ministry leader', 'Pastor Sample', true); $ref($a8, 'Friend or mentor', 'Casey Sample', true);
}

// ---------------- Money (phase 3) ----------------
// Turns each sample traveler's "raised" amount into real gifts and payments that add up to it.
function seed_money_demo(): void {
    $now = now(); mt_srand(7);
    $names = [['Maria', 'Lopez'], ['Ben', 'Dalton'], ['Pat', 'Walsh'], ['Lee', 'Huang'], ['Jo', 'Reyes'], ['Kim', 'Carter'], ['Sam', 'Nguyen'],
              ['Alex', 'Murphy'], ['Robin', 'Price'], ['Terry', 'Stone'], ['Dana', 'Brooks'], ['Chris', 'Patel'], ['Jesse', 'Ward'], ['Morgan', 'Ellis'],
              ['Shawn', 'Kelly'], ['Lynn', 'Ford'], ['Frank', 'Olsen'], ['Rosa', 'Diaz']];
    $donors = [];
    foreach ($names as $i => [$f, $l]) $donors[] = insert('donors', ['first_name' => $f, 'last_name' => $l, 'email' => strtolower($f . '.' . $l) . '@example.com',
        'address' => (100 + $i * 7) . ' Sample St', 'city' => 'Jacksonville', 'state' => 'FL', 'zip' => '3220' . ($i % 9), 'created_at' => $now]);
    $donors[] = insert('donors', ['org' => 'Sample Family Foundation', 'first_name' => 'Ruth', 'last_name' => 'Allen', 'email' => 'grants@example.org', 'created_at' => $now]);

    $closed = insert('batches', ['name' => 'Sunday offering, Sep 27', 'deposit_date' => '2026-09-28', 'status' => 'closed', 'created_by' => 'Adam Hardegree', 'created_at' => '2026-09-27 13:00:00', 'closed_at' => '2026-09-28 10:00:00']);
    $open = insert('batches', ['name' => 'Sunday offering, Oct 4', 'deposit_date' => '2026-10-05', 'status' => 'open', 'created_by' => 'Adam Hardegree', 'created_at' => $now]);
    $methods = ['card', 'card', 'card', 'bank', 'check', 'card', 'cash'];
    $gift = function (int $trip, ?int $person, float $amt, string $date, ?int $batchOverride = null) use ($donors, $methods, $closed, $now) {
        $m = $methods[mt_rand(0, count($methods) - 1)];
        $anon = mt_rand(1, 12) === 1;
        $card = in_array($m, ['card', 'bank'], true);
        insert('gifts', ['donor_id' => $m === 'cash' && mt_rand(0, 1) ? null : $donors[mt_rand(0, count($donors) - 1)], 'trip_id' => $trip, 'person_id' => $person,
            'amount' => $amt, 'fee' => $card ? round($amt * ($m === 'bank' ? .008 : .029) + ($m === 'bank' ? 0 : .3), 2) : 0, 'method' => $m,
            'check_no' => $m === 'check' ? (string)mt_rand(1001, 4999) : null, 'batch_id' => $batchOverride ?? (in_array($m, ['check', 'cash'], true) ? $closed : null),
            'gift_date' => $date, 'anonymous' => $anon ? 1 : 0, 'source' => $card ? 'stripe' : 'manual', 'status' => 'cleared',
            'thanked_at' => mt_rand(0, 2) ? '2026-09-30 12:00:00' : null, 'created_by' => 'Sample', 'created_at' => $now]);
    };
    foreach (all('SELECT * FROM members WHERE raised > 0') as $m) {
        $left = (float)$m['raised'];
        // Some travelers paid part themselves
        if (mt_rand(0, 2) === 0 && $left >= 400) {
            $mine = $left >= 1000 ? 300 : 150;
            insert('payments', ['trip_id' => $m['trip_id'], 'person_id' => $m['person_id'], 'amount' => $mine, 'method' => 'card', 'kind' => 'payment', 'paid_on' => '2026-09-1' . mt_rand(0, 9), 'created_by' => 'Sample', 'created_at' => $now]);
            $left -= $mine;
        }
        while ($left > 0.001) {
            $amt = $left <= 150 ? $left : min($left, [50, 75, 100, 100, 150, 200, 250, 500][mt_rand(0, 7)]);
            $gift((int)$m['trip_id'], (int)$m['person_id'], (float)$amt, '2026-0' . mt_rand(8, 9) . '-' . str_pad((string)mt_rand(1, 28), 2, '0', STR_PAD_LEFT));
            $left -= $amt;
        }
    }
    foreach (all('SELECT id FROM trips') as $i => $t) {
        $gift((int)$t['id'], null, $i ? 500.0 : 250.0, '2026-09-21');
        $gift((int)$t['id'], null, 100.0, '2026-10-01', $open);
    }
    $bz = (int)val("SELECT id FROM trips WHERE slug = 'belize'"); $il = (int)val("SELECT id FROM trips WHERE slug = 'israel'");
    foreach ([[$bz, 'Airfare', 'Group airfare deposit', 'American Airlines', 1600, 'USD', 1, '2026-09-15', 'Church card'],
              [$bz, 'Lodging', 'Adventures in Missions team deposit', 'Adventures in Missions', 800, 'USD', 1, '2026-09-20', 'Church check'],
              [$bz, 'Supplies', 'VBS craft supplies', 'Hobby store', 186.40, 'USD', 1, '2026-09-29', 'Maya Bennett'],
              [$il, 'Lodging', 'Tour deposit', 'Sample Tours', 2400, 'USD', 1, '2026-09-10', 'Church check'],
              [$il, 'MISC', 'Welcome gifts for guides', 'Shuk vendor', 120, 'ILS', 0.27, '2026-09-25', 'Taylor Brooks']] as [$t, $type, $desc, $vendor, $amt, $cur, $rate, $date, $by]) {
        insert('expenses', ['trip_id' => $t, 'type' => $type, 'description' => $desc, 'vendor' => $vendor, 'amount' => $amt, 'currency' => $cur, 'rate' => $rate,
            'usd' => round($amt * $rate, 2), 'spent_on' => $date, 'paid_by' => $by, 'reimburse' => in_array($by, ['Maya Bennett', 'Taylor Brooks'], true) ? 1 : 0,
            'reimbursed_at' => $by === 'Taylor Brooks' ? '2026-09-30 10:00:00' : null, 'created_by' => 'Sample', 'created_at' => $now]);
    }
}

// ---------------- Communication and trip tools (phase 4) ----------------
function seed_comms_demo(): void {
    $now = now();
    $bz = (int)val("SELECT id FROM trips WHERE slug = 'belize'");
    foreach (all("SELECT p.* FROM people p JOIN members m ON m.person_id = p.id WHERE m.trip_id = ? AND p.ec1_rel = 'Parent/Guardian'", [$bz]) as $p)
        insert('guardians', ['person_id' => $p['id'], 'name' => $p['ec1_name'], 'rel' => 'Parent', 'email' => strtolower(str_replace(' ', '.', (string)$p['ec1_name'])) . '@example.com',
            'phone' => $p['ec1_phone'], 'token' => new_token(), 'created_at' => $now]);
    $by = fn(string $first) => one("SELECT p.* FROM people p JOIN members m ON m.person_id = p.id WHERE m.trip_id = ? AND p.first_name = ?", [$bz, $first]);
    $corey = $by('Corey'); $grace = $by('Grace'); $noah = $by('Noah'); $sofia = $by('Sofia');
    foreach ([[$corey, 1, 'Hey team! Reply here with questions anytime.', '2026-09-28 18:02:00'],
              [$grace, 0, 'Do we need to bring our own sheets?', '2026-09-28 19:15:00'],
              [$corey, 1, 'Nope, the base has sheets and pillows. Bring a towel though.', '2026-09-28 19:31:00'],
              [$sofia, 0, 'Can I pack my EpiPens in my carry-on?', '2026-09-30 20:10:00'],
              [$corey, 1, 'Yes, carry them on and keep the pharmacy label. Maya will hold a backup.', '2026-09-30 20:40:00']] as [$p, $staff, $body, $when])
        insert('chat', ['trip_id' => $bz, 'thread' => 'team', 'person_id' => $p['id'], 'author' => full_name($p), 'staff' => $staff, 'body' => $body, 'created_at' => $when]);
    insert('chat', ['trip_id' => $bz, 'thread' => 'p' . $noah['id'], 'person_id' => $noah['id'], 'author' => full_name($noah), 'staff' => 0, 'body' => "My passport appointment is October 20. Is that still in time?", 'created_at' => '2026-09-29 12:05:00']);
    insert('chat', ['trip_id' => $bz, 'thread' => 'p' . $noah['id'], 'person_id' => $corey['id'], 'author' => full_name($corey), 'staff' => 1, 'body' => 'Yes, plenty of time. Upload a photo of it when it arrives.', 'created_at' => '2026-09-29 12:30:00']);
    insert('incidents', ['trip_id' => $bz, 'person_id' => $sofia['id'], 'happened_at' => '2026-09-13 13:10:00', 'kind' => 'medical', 'severity' => 'low',
        'description' => 'Mild allergic reaction to a snack at the kickoff meeting. No EpiPen needed.', 'action_taken' => 'Gave water, checked the label, called her mom.',
        'parent_notified' => 1, 'resolved' => 1, 'reported_by' => 'Maya Bennett', 'created_at' => '2026-09-13 13:30:00']);
    insert('outbox', ['trip_id' => $bz, 'channel' => 'email', 'to_addr' => 'grace.okafor@example.com', 'subject' => 'Welcome to the Belize team', 'body' => 'Sample message', 'status' => 'not_sent', 'error' => 'Email is not set up yet', 'created_by' => 'Corey Rees', 'created_at' => '2026-09-12 10:00:00']);
}

// ---------------- Signatures, background checks, fundraising pages (phase 5) ----------------
const WAIVER_TEXT = "I have read the Missions Trip Liability Waiver. I understand the risks of international travel and service, and I release Journey Church, its staff and volunteers from liability as the waiver describes.";
const COC_TEXT = "I have read the Missions Code of Conduct and agree to follow it for the whole trip, including the team meetings before we leave.";
function seed_phase5(bool $demo): void {
    $now = now();
    // The waiver and code of conduct become real e-signature tasks tied to their documents
    foreach ([['Sign the liability waiver', 'Missions trip liability waiver', WAIVER_TEXT, 1], ['Sign the code of conduct', 'Missions code of conduct', COC_TEXT, 0]] as [$title, $doc, $text, $parent]) {
        foreach (all('SELECT * FROM tasks WHERE title = ?', [$title]) as $tk) {
            $fid = val('SELECT id FROM files WHERE trip_id = ? AND title = ?', [$tk['trip_id'], $doc]);
            update('tasks', (int)$tk['id'], ['type' => 'sign', 'file_id' => $fid ? (int)$fid : null, 'description' => $text, 'parent_sign' => $parent, 'allow_self' => 1]);
            foreach (all('SELECT d.*, p.first_name, p.preferred_name, p.last_name FROM task_done d JOIN people p ON p.id = d.person_id WHERE d.task_id = ?', [$tk['id']]) as $d)
                insert('signatures', ['task_id' => $tk['id'], 'person_id' => $d['person_id'], 'signer_role' => 'traveler', 'signer_name' => full_name($d), 'agreement' => $text,
                    'doc_title' => $doc, 'ip' => 'Recorded before e-signatures', 'signed_at' => $d['done_at']]);
        }
    }
    q("UPDATE trips SET bg_required = 'leaders' WHERE bg_required IS NULL");
    foreach (all('SELECT m.id, p.first_name, p.preferred_name, p.last_name FROM members m JOIN people p ON p.id = m.person_id WHERE m.page_slug IS NULL') as $m)
        update('members', (int)$m['id'], ['page_slug' => unique_page_slug($m), 'page_status' => 'draft']);
    if (!$demo) return;

    foreach ([['Corey', 'clear', '2025-08-01', '2028-08-01'], ['Maya', 'requested', null, null], ['Taylor', 'clear', '2024-03-10', '2026-11-10']] as [$first, $st, $done, $exp]) {
        $pid = val('SELECT p.id FROM people p JOIN members m ON m.person_id = p.id WHERE p.first_name = ? AND m.role = \'leader\'', [$first]);
        if ($pid) insert('background_checks', ['person_id' => $pid, 'provider' => 'Protect My Ministry', 'status' => $st, 'requested_at' => $done ?: '2026-09-25', 'completed_at' => $done, 'expires_on' => $exp, 'created_by' => 'Sample', 'created_at' => $now]);
    }
    $stories = ["I'm going to Belize to help run VBS at a children's home and serve families in Belize City. Would you pray for our team and help me get there?",
                "This summer I get to walk where Jesus walked and come home ready to serve our church. Thanks for being part of it!"];
    foreach (all("SELECT m.*, t.slug AS tslug FROM members m JOIN trips t ON t.id = m.trip_id WHERE m.raised > 0 ORDER BY m.id") as $i => $m)
        if ($i % 3 !== 2) update('members', (int)$m['id'], ['page_status' => $i % 5 === 4 ? 'pending' : 'live', 'page_story' => $stories[$m['tslug'] === 'israel' ? 1 : 0]]);
    foreach (all('SELECT id FROM gifts WHERE person_id IS NOT NULL AND anonymous = 0 ORDER BY id LIMIT 6') as $i => $g)
        update('gifts', (int)$g['id'], ['message' => ['So proud of you!', 'Praying for you and the team.', 'Go get em!', 'Love you, have an amazing trip.', 'Thankful you said yes.', 'Bring back stories!'][$i]]);
    $g = one("SELECT * FROM gifts WHERE method = 'card' AND person_id IS NOT NULL ORDER BY id LIMIT 1");
    if ($g) { $rid = insert('recurring', ['donor_id' => $g['donor_id'], 'trip_id' => $g['trip_id'], 'person_id' => $g['person_id'], 'amount' => $g['amount'], 'stripe_sub_id' => 'sub_sample', 'status' => 'active', 'created_at' => $now]); update('gifts', (int)$g['id'], ['recurring_id' => $rid]); }
}
