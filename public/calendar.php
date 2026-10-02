<?php
// Calendar feed (.ics) that phones and Google Calendar can subscribe to.
// ?p=token → one traveler (meetings, trip days, flights, their open tasks). ?t=token → the whole trip.
define('NO_SESSION', true);
define('REAL_DB', true);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/flightparse.php';

$person = g('p') !== '' ? one('SELECT id FROM people WHERE cal_token = ?', [g('p')]) : null;
$trip = g('t') !== '' ? one('SELECT * FROM trips WHERE cal_token = ?', [g('t')]) : ($person ? trip_for_person((int)$person['id']) : null);
if (!$trip) { http_response_code(404); exit('Calendar not found'); }
$tid = (int)$trip['id'];

$esc = fn(string $s) => str_replace(["\\", ";", ",", "\r\n", "\n"], ["\\\\", "\;", "\\,", "\\n", "\\n"], $s);
// Times are stored as local times; convert from the place they happen (an airport, or the trip's country) to UTC
$utc = fn(string $local, string $tz = 'America/New_York') => (new DateTime($local, new DateTimeZone($tz)))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
// Calendar files wrap lines at 75 bytes (some apps reject longer ones), without splitting a character
$fold = function (string $line): string {
    $out = ''; $cur = '';
    foreach (mb_str_split($line) as $ch) { if (strlen($cur) + strlen($ch) > 74) { $out .= $cur . "\r\n "; $cur = ''; } $cur .= $ch; }
    return $out . $cur;
};
$tripTz = $trip['timezone'] ?: 'America/New_York';
$ev = [];
$add = function (string $uid, string $title, ?string $start, ?string $end, string $desc = '', string $where = '', bool $allday = false, string $tz = 'America/New_York', ?string $endTz = null) use (&$ev, $esc, $utc, $fold) {
    if (!$start) return;
    $l = ["BEGIN:VEVENT", "UID:$uid@missions.journeychurch.org", 'DTSTAMP:' . gmdate('Ymd\THis\Z'), 'SUMMARY:' . $esc($title)];
    if ($allday) { $l[] = 'DTSTART;VALUE=DATE:' . date('Ymd', strtotime($start)); $l[] = 'DTEND;VALUE=DATE:' . date('Ymd', strtotime(($end ?: $start) . ' +1 day')); }
    else { $l[] = 'DTSTART:' . $utc($start, $tz); $l[] = 'DTEND:' . ($end ? $utc($end, $endTz ?? $tz) : $utc(date('Y-m-d H:i:s', strtotime($start . ' +1 hour')), $tz)); }
    if ($desc !== '') $l[] = 'DESCRIPTION:' . $esc($desc);
    if ($where !== '') $l[] = 'LOCATION:' . $esc($where);
    $l[] = 'END:VEVENT';
    $ev[] = implode("\r\n", array_map($fold, $l));
};

$add("trip-$tid", $trip['name'] . ' mission trip', $trip['start_date'], $trip['end_date'], church_name() . ' Missions', trim($trip['city'] . ', ' . $trip['country'], ', '), true);
foreach (all('SELECT * FROM meetings WHERE trip_id = ?', [$tid]) as $m) $add("meeting-{$m['id']}", $trip['name'] . ': ' . $m['title'], $m['starts_at'], $m['ends_at'], (string)$m['notes'], trim($m['location'] . ' ' . $m['address']));
foreach (all('SELECT * FROM flights WHERE trip_id = ? AND departs_at IS NOT NULL', [$tid]) as $f) if (!is_placeholder($f['flight_no'])) $add("flight-{$f['id']}", "{$f['flight_no']} {$f['from_code']} → {$f['to_code']}", $f['departs_at'], $f['arrives_at'], trim($f['airline'] . '. ' . $f['notes']), (string)$f['from_code'], false, airport_tz($f['from_code'], $tripTz), airport_tz($f['to_code'], $tripTz));
foreach (all('SELECT * FROM itinerary WHERE trip_id = ?', [$tid]) as $i) {
    $ts = strtotime($i['day'] . ' ' . $i['time']);
    $timed = $ts && preg_match('/\d/', (string)$i['time']);
    $add("itin-{$i['id']}", $i['title'], $timed ? date('Y-m-d H:i:s', $ts) : $i['day'], null, (string)$i['detail'], '', !$timed, $tripTz);
}
if ($person) foreach (tasks_for($tid, (int)$person['id']) as $k) if ($k['due_date'] && !$k['done_at']) $add("task-{$k['id']}-{$person['id']}", 'Due: ' . $k['title'], $k['due_date'], null, 'From your Journey Missions checklist', '', true);

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename="' . preg_replace('/[^a-z0-9]+/i', '-', $trip['name']) . '.ics"');
echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Journey Church//Missions//EN\r\nCALSCALE:GREGORIAN\r\nX-WR-CALNAME:" . $esc($trip['name'] . ' trip') . "\r\nX-PUBLISHED-TTL:PT6H\r\n" . implode("\r\n", $ev) . ($ev ? "\r\n" : '') . "END:VCALENDAR\r\n";
