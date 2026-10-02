<?php
// Reads flights out of pasted text: an airline confirmation, a travel agent's itinerary, or a few typed lines.
// Always shown to staff as a preview to fix before saving.

const AIRLINE_CODES = ['AA' => 'American Airlines', 'DL' => 'Delta', 'UA' => 'United', 'WN' => 'Southwest', 'B6' => 'JetBlue', 'AS' => 'Alaska Airlines',
    'NK' => 'Spirit', 'F9' => 'Frontier', 'G4' => 'Allegiant', 'AC' => 'Air Canada', 'LY' => 'El Al', 'BA' => 'British Airways', 'LH' => 'Lufthansa',
    'AF' => 'Air France', 'KL' => 'KLM', 'CM' => 'Copa', 'AV' => 'Avianca', 'TK' => 'Turkish Airlines', 'IB' => 'Iberia',
    'TA' => 'TACA', '5U' => 'TAG Airlines', 'VS' => 'Virgin Atlantic', 'EK' => 'Emirates', 'QR' => 'Qatar Airways', 'ET' => 'Ethiopian', 'KQ' => 'Kenya Airways'];

function parse_flights(string $text, array $trip): array {
    // Airline emails are full of non-breaking and thin spaces; make them ordinary spaces
    $text = preg_replace('/[\x{00A0}\x{2007}\x{2009}\x{200A}\x{202F}\x{2002}\x{2003}]/u', ' ', str_replace(["\r", "\t"], ["\n", ' '], $text)) ?? $text;
    // "American Airlines Flight 1820" / "Delta 2231" → "AA 1820"
    foreach (AIRLINE_CODES as $code => $name) {
        $short = preg_quote(preg_replace('/ (Airlines|Airways)$/', '', $name), '/');
        // Case-sensitive "American 1820" style names; a 4-digit number that looks like a year needs the word Flight
        $text = preg_replace('/\b' . $short . '(?: (?:Airlines|Airways|Air Lines))?\s*(?:Flight|Flt\.?|#)\s*(\d{1,4})\b/i', $code . ' $1', $text);
        $text = preg_replace('/\b' . $short . '(?: (?:Airlines|Airways|Air Lines))?\s+(?!(?:19|20)\d\d\b)(\d{1,4})\b(?!:)/', $code . ' $1', $text);
    }
    $text = preg_replace('/\b(?:Flight|Flt\.?)\s*#?\s*([A-Z0-9]{2})\s?(\d{1,4})\b/i', '$1 $2', $text);
    $codes = implode('|', array_map(fn($c) => preg_quote($c, '/'), array_keys(AIRLINE_CODES)));
    // A code followed by a time (ET 11:30) or right after AM/PM is a time zone, not a flight
    preg_match_all('/(?<![AaPp][Mm] )\b(' . $codes . ')\s?(\d{1,4})\b(?![:.]\d)/', $text, $m, PREG_OFFSET_CAPTURE);
    if (!$m[0]) return [];
    $stop = ['THE', 'AND', 'FOR', 'YOU', 'PNR', 'USD', 'EST', 'EDT', 'CST', 'CDT', 'PST', 'PDT', 'MST', 'MDT', 'UTC', 'GMT', 'NON', 'SEAT', 'ROW', 'TBD', 'NOT', 'ARE', 'ALL',
             'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN', 'DEP', 'ARR', 'VIA', 'BAG', 'OUT', 'ONE'];
    $year = (int)substr($trip['start_date'], 0, 4);
    $mid = date('Y-m-d', (int)((strtotime($trip['start_date']) + strtotime($trip['end_date'])) / 2));
    $rows = []; $lastDate = null; $n = count($m[0]);
    for ($i = 0; $i < $n; $i++) {
        $start = $m[0][$i][1];
        $from = $i ? $m[0][$i - 1][1] + strlen($m[0][$i - 1][0]) : max(0, $start - 200);
        $to = $i + 1 < $n ? $m[0][$i + 1][1] : min(strlen($text), $start + 400);
        // Text before this flight number (since the last one) often holds its date; text after holds airports and times
        $before = substr($text, $from, $start - $from); $after = substr($text, $start + strlen($m[0][$i][0]), $to - $start - strlen($m[0][$i][0]));
        $win = $before . ' ' . $after;
        // Nearest date before the flight number wins; otherwise a date on the same line after it; otherwise the last one seen
        $bd = flight_dates($before, $year); $firstLine = (string)strtok($after . "\n", "\n");
        $lead = preg_split('/\b\d{1,2}:\d{2}/', $firstLine)[0]; // the part of the line before the first time
        $date = flight_dates($lead, $year)[0] ?? ($bd ? end($bd) : $lastDate);
        if ($date) $lastDate = $date;
        $find = function (string $t) use ($stop) { preg_match_all('/\(([A-Z]{3})\)/', $t, $p); if (count($p[1]) >= 2) return $p[1];
            preg_match_all('/\b([A-Z]{3})\b/', $t, $q); return array_values(array_unique(array_filter($q[1], fn($c) => !in_array($c, $stop, true)))); };
        $ports = $find($after); if (count($ports) < 2) $ports = $find($win);
        // 6:00 AM, 6:00am, 6:00 a.m., 6:00p — but not the "A" in "12:30 Arrive"
        preg_match_all('/\b(\d{1,2}):(\d{2})(?:\s*([AaPp])(?:\.?\s?[Mm]\.?)?(?![A-Za-z]))?/', $after, $tm, PREG_SET_ORDER);
        if (!$tm) preg_match_all('/\b([01]?\d|2[0-3]):([0-5]\d)\b()/', $after, $tm, PREG_SET_ORDER);
        if (!$tm) preg_match_all('/(?<![\d\/-])\b([01]\d|2[0-3])([0-5]\d)\b(?![\/-])()/', $after, $tm, PREG_SET_ORDER);
        $clock = function (?array $t) { if (!$t) return null; $h = (int)$t[1]; $ap = strtolower($t[3] ?? ''); if ($ap === 'p' && $h < 12) $h += 12; if ($ap === 'a' && $h === 12) $h = 0; return sprintf('%02d:%02d:00', $h, (int)$t[2]); };
        $dep = $clock($tm[0] ?? null); $arr = $clock($tm[1] ?? null);
        // A date with no year that lands well before the trip belongs to the next year (trips over New Year's)
        if ($date && $date < date('Y-m-d', strtotime($trip['start_date'] . ' -120 days'))) $date = date('Y-m-d', strtotime($date . ' +1 year'));
        $departs = $date && $dep ? "$date $dep" : null;
        $arrives = $date && $arr ? ($arr < $dep ? date('Y-m-d', strtotime("$date +1 day")) : $date) . " $arr" : null;
        $code = $m[1][$i][0];
        $rows[] = ['flight_no' => $code . ' ' . $m[2][$i][0], 'airline' => AIRLINE_CODES[$code] ?? '', 'from_code' => $ports[0] ?? '', 'to_code' => $ports[1] ?? '',
                   'departs_at' => $departs, 'arrives_at' => $arrives, 'direction' => $date && $date > $mid ? 'home' : ($date ? 'out' : ($i < $n / 2 ? 'out' : 'home'))];
    }
    // The same flight listed twice in an email (summary + details): keep the most complete
    $out = [];
    foreach ($rows as $r) { $k = $r['flight_no'] . '|' . substr((string)$r['departs_at'], 0, 10); $score = count(array_filter($r));
        if (!isset($out[$k]) || $score > count(array_filter($out[$k]))) $out[$k] = $r; }
    return array_values($out);
}

// Every date in the text, in the order they appear
function flight_dates(string $s, int $year): array {
    $found = []; $off = 0; $rest = $s;
    while ($rest !== '' && ($d = flight_date($rest, $year, $pos, $len)) !== null) { $found[] = $d; $rest = substr($rest, $pos + $len); }
    return $found;
}
function flight_date(string $s, int $year, &$pos = null, &$len = null): ?string {
    $mon = 'january|february|march|april|june|july|august|september|october|november|december|jan|feb|mar|apr|may|jun|jul|aug|sept|sep|oct|nov|dec';
    $pats = [
        'iso' => '/\b(20\d\d)-(\d\d)-(\d\d)\b/',
        'us' => '/\b(\d{1,2})\/(\d{1,2})\/(20\d\d|\d\d)\b/',
        'md' => '/\b(' . $mon . ')\.?\s+(\d{1,2})(?:st|nd|rd|th)?\b(?:,?\s+(20\d\d))?/i',
        'dm' => '/\b(\d{1,2})\s?(' . $mon . ')\.?(?:\s?(20\d\d|\d\d))?\b/i',
    ];
    $best = null;
    foreach ($pats as $k => $re) if (preg_match($re, $s, $x, PREG_OFFSET_CAPTURE) && ($best === null || $x[0][1] < $best[1][0][1])) $best = [$k, $x];
    if (!$best) return null;
    [$k, $x] = $best; $pos = $x[0][1]; $len = strlen($x[0][0]); $g = fn($i) => $x[$i][0] ?? '';
    $y2 = fn(string $y) => $y === '' ? $year : (strlen($y) === 2 ? 2000 + (int)$y : (int)$y);
    $t = match ($k) {
        'iso' => strtotime($g(1) . '-' . $g(2) . '-' . $g(3)),
        'us' => mktime(0, 0, 0, (int)$g(1), (int)$g(2), $y2($g(3))),
        'md' => strtotime($g(1) . ' ' . $g(2) . ' ' . $y2($g(3))),
        'dm' => strtotime($g(2) . ' ' . $g(1) . ' ' . $y2($g(3))),
    };
    return $t ? date('Y-m-d', $t) : null;
}

// Time zones for airports, so flight times land at the right hour in calendars
const AIRPORT_TZ = ['JAX' => 'America/New_York', 'MIA' => 'America/New_York', 'ATL' => 'America/New_York', 'MCO' => 'America/New_York', 'TPA' => 'America/New_York',
    'FLL' => 'America/New_York', 'CLT' => 'America/New_York', 'JFK' => 'America/New_York', 'EWR' => 'America/New_York', 'LGA' => 'America/New_York', 'IAD' => 'America/New_York',
    'DCA' => 'America/New_York', 'BOS' => 'America/New_York', 'PHL' => 'America/New_York', 'DTW' => 'America/Detroit', 'IAH' => 'America/Chicago', 'HOU' => 'America/Chicago',
    'DFW' => 'America/Chicago', 'ORD' => 'America/Chicago', 'MSP' => 'America/Chicago', 'BNA' => 'America/Chicago', 'DEN' => 'America/Denver', 'PHX' => 'America/Phoenix',
    'LAX' => 'America/Los_Angeles', 'SFO' => 'America/Los_Angeles', 'SEA' => 'America/Los_Angeles', 'BZE' => 'America/Belize', 'GEO' => 'America/Guyana', 'GUA' => 'America/Guatemala',
    'SAL' => 'America/El_Salvador', 'SAP' => 'America/Tegucigalpa', 'TGU' => 'America/Tegucigalpa', 'RTB' => 'America/Tegucigalpa', 'MGA' => 'America/Managua', 'SJO' => 'America/Costa_Rica',
    'LIR' => 'America/Costa_Rica', 'PTY' => 'America/Panama', 'PAP' => 'America/Port-au-Prince', 'SDQ' => 'America/Santo_Domingo', 'PUJ' => 'America/Santo_Domingo',
    'SJU' => 'America/Puerto_Rico', 'MBJ' => 'America/Jamaica', 'KIN' => 'America/Jamaica', 'NAS' => 'America/Nassau', 'MEX' => 'America/Mexico_City', 'CUN' => 'America/Cancun',
    'BOG' => 'America/Bogota', 'MDE' => 'America/Bogota', 'LIM' => 'America/Lima', 'POS' => 'America/Port_of_Spain', 'TLV' => 'Asia/Jerusalem', 'AMM' => 'Asia/Amman', 'CAI' => 'Africa/Cairo',
    'IST' => 'Europe/Istanbul', 'LHR' => 'Europe/London', 'CDG' => 'Europe/Paris', 'AMS' => 'Europe/Amsterdam', 'FRA' => 'Europe/Berlin', 'MAD' => 'Europe/Madrid', 'NBO' => 'Africa/Nairobi',
    'EBB' => 'Africa/Kampala', 'KGL' => 'Africa/Kigali', 'ADD' => 'Africa/Addis_Ababa', 'JNB' => 'Africa/Johannesburg', 'DXB' => 'Asia/Dubai', 'DOH' => 'Asia/Qatar'];
function airport_tz(?string $code, string $fallback = 'America/New_York'): string { return AIRPORT_TZ[strtoupper((string)$code)] ?? $fallback; }
