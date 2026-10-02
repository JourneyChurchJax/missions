#!/usr/bin/env bash
# Smoke test: runs the site on this computer with a throwaway copy, signs in, turns on sample data,
# and opens every main page. Fails if any page errors. Used by the deploy check; run it yourself with: bash tests/smoke.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORK="$(mktemp -d)"; trap 'kill $(cat "$WORK/pid" 2>/dev/null) 2>/dev/null || true; rm -rf "$WORK"' EXIT
cp -R "$ROOT/public" "$WORK/public"
PORT=$((8800 + RANDOM % 900))   # a fresh port each run, so a leftover server can't answer for this one
echo "<?php return ['preview_password' => 'smoke-test-only', 'site_url' => 'http://127.0.0.1:$PORT'];" > "$WORK/config.php"
( cd "$WORK" && php -S 127.0.0.1:$PORT -t public > "$WORK/server.log" 2>&1 & echo $! > "$WORK/pid" )
sleep 1
B=http://127.0.0.1:$PORT; J="$WORK/jar"; FAIL=0
tok(){ grep -o 'name="csrf" value="[a-f0-9]*"' "$1" | head -1 | sed 's/.*value="//;s/"//'; }
curl -s -c "$J" -b "$J" "$B/signin.php" -o "$WORK/s.html"
curl -s -c "$J" -b "$J" -o /dev/null --data-urlencode "csrf=$(tok "$WORK/s.html")" -d "password=smoke-test-only" "$B/signin.php"
check(){ code=$(curl -s -b "$J" -c "$J" -o "$WORK/page.html" -w '%{http_code}' "$B$1"); if [ "$code" != "${2:-200}" ] || grep -qi 'fatal error\|uncaught\|warning:\|something went wrong' "$WORK/page.html"; then echo "FAIL $code $1"; FAIL=1; else echo "ok   $code $1"; fi; }
# Real (empty) data first, then sample data
check /admin/
curl -s -b "$J" "$B/admin/settings.php?s=demo" -o "$WORK/d.html"
curl -s -b "$J" -c "$J" -o /dev/null --data-urlencode "csrf=$(tok "$WORK/d.html")" -d "action=demo_toggle&demo=1" "$B/action.php"
for u in /admin/ "/admin/trip.php?id=1" "/admin/trip.php?id=1&tab=team" "/admin/trip.php?id=1&tab=tasks" "/admin/trip.php?id=1&tab=meetings" "/admin/trip.php?id=1&tab=documents" \
  "/admin/trip.php?id=1&tab=travel" "/admin/trip.php?id=1&tab=guide" "/admin/trip.php?id=1&tab=budget" "/admin/trip.php?id=1&tab=budget&view=spent" "/admin/trip.php?id=1&tab=giving" \
  "/admin/trip.php?id=1&tab=updates" "/admin/trip.php?id=1&tab=ontrip" /admin/people.php "/admin/person.php?id=1" /admin/applications.php "/admin/app-form.php?id=1" \
  /admin/giving.php "/admin/giving.php?v=batches" "/admin/giving.php?v=donors" "/admin/giving.php?v=payments" "/admin/giving.php?v=statements" "/admin/gift.php?id=1" "/admin/donor.php?id=1" \
  /admin/reports.php "/admin/reports.php?trip=1&view=readiness" "/admin/reports.php?trip=1&view=medical" "/admin/signatures.php?task=1" "/admin/search.php?q=maya" \
  "/admin/settings.php?s=church" "/admin/settings.php?s=leaders" "/admin/settings.php?s=email" "/admin/settings.php?s=privacy" "/admin/settings.php?s=audit" "/packet.php?trip=1"; do check "$u"; done
for u in "/apply/?f=belize-2027" "/give/?trip=belize" /health.php; do check "$u"; done
grep -i 'fatal\|warning' "$WORK/server.log" | grep -v 'Deprecated' && FAIL=1 || true
[ "$FAIL" = 0 ] && echo "All pages loaded." || { echo "Some pages failed."; exit 1; }
