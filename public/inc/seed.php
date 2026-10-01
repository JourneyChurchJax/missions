<?php
// First-run data. Belize uses real trip details (no private personal data). Israel and its people are samples.

function seed(PDO $pdo): void {
    $now = now();
    $person = function (string $first, string $last, array $extra = []) use ($now): int {
        return insert('people', ['first_name' => $first, 'last_name' => $last, 'created_at' => $now] + $extra);
    };

    // ---- Belize (real) ----
    $bz = insert('trips', [
        'slug' => 'belize', 'name' => 'Belize', 'public_name' => 'Belize 2027', 'city' => 'Belize City', 'country' => 'Belize',
        'partner' => 'Adventures in Missions', 'start_date' => '2027-06-19', 'end_date' => '2027-06-25',
        'description' => 'We are joining Adventures in Missions to serve alongside their ministry partners, reaching communities in Belize through their kids and families, showing them the love of Jesus, encouraging them to walk in faith, and growing together as the body of Christ.',
        'qualifications' => "Professing Christians\nGoing into 9th grade or older\nAble to lift 20 lbs and walk 2 miles on uneven ground\nLeaders need a clean background check\nPassport valid through December 25, 2027\nRead and sign the missions code of conduct",
        'cost_per_person' => 1900, 'max_team' => 12, 'app_deadline' => '2027-01-05', 'status' => 'active',
        'passport_valid_through' => '2027-12-25', 'created_at' => $now,
    ]);
    $corey = $person('Corey', 'Rees');
    $thomas = $person('Thomas', 'Sereno', ['passport_expires' => '2034-02-01', 'ec1_name' => '[On file]', 'meds' => '[On file]']);
    insert('members', ['trip_id' => $bz, 'person_id' => $corey, 'role' => 'leader', 'traveling' => 1, 'raised' => 600, 'created_at' => $now]);
    insert('members', ['trip_id' => $bz, 'person_id' => $thomas, 'role' => 'traveler', 'traveling' => 1, 'raised' => 640, 'created_at' => $now]);

    foreach ([['Lodging, food and transportation', 'MISC', 'Adventures in Missions', 945, 2, 1, '2027-04-19'],
              ['Block party', 'MISC', 'God Cares Outreach', 650, 1, 0, '2027-06-19'],
              ['Home visits', 'MISC', 'God Cares Outreach', 30, 18, 0, '2027-06-19'],
              ['Feeding program', 'MISC', 'God Cares Outreach', 300, 1, 0, '2027-06-19'],
              ["Children's home VBS food", 'MISC', 'God Cares Outreach', 300, 2, 0, '2027-06-19'],
              ['Airfare', 'Airfare', 'AFC Travel / American Airlines', 851, 2, 1, '2027-06-19']] as [$d, $ty, $v, $u, $qty, $per, $dt]) {
        insert('budget', ['trip_id' => $bz, 'description' => $d, 'type' => $ty, 'vendor' => $v, 'unit_cost' => $u, 'qty' => $qty, 'per_traveler' => $per, 'est_date' => $dt]);
    }
    foreach ([['2026-12-19', 10], ['2027-02-07', 50], ['2027-03-14', 100]] as [$d, $p]) {
        insert('goals', ['trip_id' => $bz, 'due_date' => $d, 'kind' => 'percent', 'amount' => $p]);
    }
    $t_pass = insert('tasks', ['trip_id' => $bz, 'title' => 'Upload a passport copy', 'description' => 'A clear photo of the photo page.', 'type' => 'upload', 'due_date' => '2026-11-15', 'allow_self' => 1, 'created_at' => $now]);
    $t_coc = insert('tasks', ['trip_id' => $bz, 'title' => 'Sign the code of conduct', 'description' => 'Read it in Documents, then mark it signed.', 'type' => 'traveler', 'due_date' => '2026-12-01', 'allow_self' => 1, 'created_at' => $now]);
    insert('tasks', ['trip_id' => $bz, 'title' => 'Confirm your name and birth date', 'description' => 'Must match your passport exactly before airfare is ticketed.', 'type' => 'verify', 'due_date' => '2027-01-15', 'allow_self' => 1, 'created_at' => $now]);
    $t_waiver = insert('tasks', ['trip_id' => $bz, 'title' => 'Sign the liability waiver', 'type' => 'traveler', 'due_date' => '2026-10-15', 'allow_self' => 1, 'created_at' => $now]);
    $t_ec = insert('tasks', ['trip_id' => $bz, 'title' => 'Add your emergency contacts', 'type' => 'traveler', 'due_date' => '2026-10-15', 'allow_self' => 1, 'created_at' => $now]);
    insert('tasks', ['trip_id' => $bz, 'title' => 'Book group airfare with AFC Travel', 'type' => 'leader', 'due_date' => '2027-02-01', 'created_at' => $now]);
    foreach ([$corey, $thomas] as $p) {
        insert('task_done', ['task_id' => $t_waiver, 'person_id' => $p, 'done_at' => '2026-09-12 10:00:00']);
        insert('task_done', ['task_id' => $t_ec, 'person_id' => $p, 'done_at' => '2026-09-12 10:05:00']);
    }
    $m = insert('meetings', ['trip_id' => $bz, 'title' => 'Team meeting', 'starts_at' => '2026-11-09 12:30:00', 'ends_at' => '2026-11-09 14:00:00', 'location' => '[Location]', 'notes' => 'Bring your passport if you have not uploaded it yet.']);
    foreach ([['Tentative Belize ministry schedule', 'Updated by Corey', 0], ['Missions code of conduct', 'Read and sign', 1], ['Missions trip liability waiver', 'Read and sign', 1], ['AIM 5 objectives', 'From Adventures in Missions', 0], ['Belize country guide', 'Money, weather, what to pack', 0]] as [$title, $note, $ack]) {
        insert('files', ['trip_id' => $bz, 'title' => $title, 'note' => $note, 'visible' => 1, 'must_ack' => $ack, 'kind' => 'doc', 'created_at' => $now]);
    }
    foreach ([['Pre-trip training guide', ''], ['AIM ministry interaction guidelines', ''], ['AIM fundraising guide', ''], ['Applying for a passport', 'Clerk of Courts or the post office']] as [$title, $note]) {
        insert('files', ['trip_id' => $bz, 'title' => $title, 'note' => $note, 'url' => '', 'visible' => 1, 'kind' => 'link', 'created_at' => $now]);
    }
    $guide = [
        'lodging' => "[Where the team sleeps: name, address and phone of the lodging]",
        'contacts' => "Trip leader: Corey Rees\nPartner: Adventures in Missions [in-country contact name and phone]\nJourney Church office: [phone]",
        'packing' => "Passport and a copy of it\nModest clothes for ministry: [what to wear]\nComfortable closed-toe shoes\nRefillable water bottle\nSunscreen and bug spray\nBible and journal\nAny medications in their original bottles",
        'wear' => "[Dress guidelines for ministry days, church and free time]",
        'money' => "[Currency, how much spending money to bring, where to exchange, cards that work]",
        'weather' => "[Typical June weather in Belize City]",
        'power' => "[Plug type and voltage]",
        'phone' => "[International plan, Wi-Fi at the lodging, how families can reach the team]",
        'health' => "[Vaccines to consider, travel insurance details, water and food safety]",
        'entry' => "Passport valid through December 25, 2027.\n[Entry requirements for U.S. citizens]",
        'safety' => "[What to do in an emergency, buddy system, who to call]",
        'airport' => "[Airport, terminal, meeting spot and time on June 19]",
    ];
    foreach ($guide as $section => $body) insert('guide', ['trip_id' => $bz, 'section' => $section, 'body' => $body, 'updated_at' => $now]);
    foreach ([['2027-06-19', '[Time]', 'Fly to Belize City', 'American Airlines · meet at the airport'], ['2027-06-19', 'Evening', 'Arrive and settle in', ''],
              ['2027-06-20', 'Morning', 'Church with partners', ''], ['2027-06-20', 'Afternoon', 'Block party', 'God Cares Outreach'],
              ['2027-06-21', 'Morning', 'Home visits', ''], ['2027-06-21', 'Afternoon', 'Feeding program', ''],
              ['2027-06-22', 'All day', "Children's home VBS", ''], ['2027-06-23', 'All day', "Children's home VBS", ''],
              ['2027-06-24', 'All day', 'Debrief and rest', ''], ['2027-06-25', '[Time]', 'Fly home', '']] as [$d, $tm, $title, $det]) {
        insert('itinerary', ['trip_id' => $bz, 'day' => $d, 'time' => $tm, 'title' => $title, 'detail' => $det]);
    }
    insert('flights', ['trip_id' => $bz, 'direction' => 'out', 'airline' => 'American Airlines', 'flight_no' => '[TBD]', 'from_code' => 'JAX', 'to_code' => 'BZE', 'departs_at' => '2027-06-19 00:00:00', 'notes' => 'Booked through AFC Travel once the group deposit is in']);
    insert('flights', ['trip_id' => $bz, 'direction' => 'home', 'airline' => 'American Airlines', 'flight_no' => '[TBD]', 'from_code' => 'BZE', 'to_code' => 'JAX', 'departs_at' => '2027-06-25 00:00:00', 'notes' => '']);
    insert('announcements', ['trip_id' => $bz, 'title' => 'Bring your passport Sunday', 'body' => "If you haven't uploaded it yet, bring your passport to the November 9 meeting and we'll scan it.", 'author' => 'Corey Rees', 'created_at' => '2026-10-01 09:41:00']);

    // ---- Israel (sample) ----
    $il = insert('trips', ['slug' => 'israel', 'name' => 'Israel', 'public_name' => 'Israel 2027', 'city' => 'Jerusalem', 'country' => 'Israel', 'partner' => '[Partner]',
        'start_date' => '2027-05-19', 'end_date' => '2027-05-28', 'cost_per_person' => 2970, 'max_team' => 20, 'status' => 'active', 'created_at' => $now]);
    $names = [['Taylor', 'Brooks', 'leader', 2970], ['Avery', 'Collins', 'traveler', 1120], ['Jordan', 'Price', 'traveler', 980], ['Morgan', 'Hayes', 'traveler', 2400],
              ['Riley', 'Foster', 'traveler', 450], ['Casey', 'Morgan', 'traveler', 1760], ['Drew', 'Bennett', 'traveler', 300]];
    foreach ($names as [$f, $l, $role, $raised]) {
        $pid = $person($f, $l);
        insert('members', ['trip_id' => $il, 'person_id' => $pid, 'role' => $role, 'traveling' => 1, 'raised' => $raised, 'created_at' => $now]);
    }
    $person('Jamie', 'Ortiz', ['tags' => 'Applicant']);
    insert('meetings', ['trip_id' => $il, 'title' => 'Israel info night', 'starts_at' => '2026-10-12 18:30:00', 'ends_at' => '2026-10-12 20:00:00', 'location' => '[Room]']);
    insert('tasks', ['trip_id' => $il, 'title' => 'Upload a passport copy', 'type' => 'upload', 'due_date' => '2026-11-15', 'allow_self' => 1, 'created_at' => $now]);
    insert('budget', ['trip_id' => $il, 'description' => '[Sample budget]', 'type' => 'MISC', 'vendor' => '', 'unit_cost' => 2970, 'qty' => 7, 'per_traveler' => 1]);

    insert('activity', ['trip_id' => $bz, 'who' => 'Setup', 'what' => 'Created the Belize trip from ManagedMissions details', 'created_at' => $now]);
}
