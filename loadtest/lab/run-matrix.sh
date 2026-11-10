#!/usr/bin/env bash
# Runs a list of "profile:level[:ip_mode]" cases against the LOCAL LAB and stores everything under $RESULTS.
# Needs: the lab Apache (127.0.0.1:8088) and the lab MariaDB (127.0.0.1:3307) running, php + node on PATH.
# This script never touches anything but the lab: loadtest:* commands and node-load.mjs refuse other targets.
#
#   RESULTS=/d/claude-scratch/emc/loadtest/results loadtest/lab/run-matrix.sh steady:50 steady:100 burst:250 stress:500
set -u
cd "$(dirname "$0")/../.."
RESULTS="${RESULTS:-loadtest/results}"
BASE="${BASE:-http://127.0.0.1:8088}"
COOLDOWN="${COOLDOWN:-20}"
mkdir -p "$RESULTS"; RESULTS="$(cygpath -m "$RESULTS")"
export APP_ENV=local DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_PORT=3307 DB_DATABASE=emc_lab_loadtest DB_USERNAME=labuser DB_PASSWORD=labpass

json() { node -e "let s='';process.stdin.on('data',d=>s+=d).on('end',()=>console.log(JSON.parse(s)['$1']))"; }

for spec in "$@"; do
  IFS=: read -r profile level ipmode <<<"$spec"; ipmode="${ipmode:-single}"
  tag="${profile}-${level}-${ipmode//:/_}"
  prep=$(php artisan loadtest:prepare --json) || { echo "prepare failed"; exit 1; }
  run=$(echo "$prep" | json run); token=$(echo "$prep" | json token)
  perf_log="storage/logs/performance-$(date -u +%F).log"
  offset=$(stat -c %s "$perf_log" 2>/dev/null || echo 0)
  case "$profile" in steady) dur=$((${RAMP:-30} + ${HOLD:-120}));; burst) dur=$((${RAMP:-15} + ${HOLD:-90}));; *) dur=$((${RAMP:-15} + ${HOLD:-60}));; esac
  echo "=== $tag (run $run) ==="
  powershell -NoProfile -File loadtest/lab/sample-lab.ps1 -Out "$(cygpath -w "$RESULTS/res-$tag.csv")" -Seconds $((dur + 25)) -Interval 2 >/dev/null &
  sampler=$!
  ARGS=(--base "$BASE" --token "$token" --run "$run" --level "$level" --profile "$profile" --ip-mode "$ipmode" --out "$RESULTS/drv-$tag.json")
  [ -n "${RAMP:-}" ] && ARGS+=(--ramp "$RAMP"); [ -n "${HOLD:-}" ] && ARGS+=(--hold "$HOLD"); [ -n "${KEEPALIVE:-}" ] && ARGS+=(--keepalive "$KEEPALIVE")
  node loadtest/lab/node-load.mjs "${ARGS[@]}" | tee "$RESULTS/drv-$tag.txt" | grep -E "^(ALL|GET_|POST_|requests=|statuses:)"
  wait $sampler 2>/dev/null
  expected=$(node -e "console.log(JSON.parse(require('fs').readFileSync('$RESULTS/drv-$tag.json','utf8')).counters.submits_ok)")
  php artisan loadtest:verify "$run" --expected="$expected" --json > "$RESULTS/verify-$tag.json" 2>&1; echo "verify: $(node -e "try{const j=JSON.parse(require('fs').readFileSync('$RESULTS/verify-$tag.json','utf8'));console.log(j.result,'saved='+j.reviews_saved,'expected='+j.k6_successful_expected,'dups='+j.duplicate_pairs)}catch(e){console.log('unparseable')}")"
  node loadtest/lab/analyze-perf-log.mjs "$perf_log" "$offset" "$RESULTS/srv-$tag.json" >/dev/null
  php artisan loadtest:cleanup "$run" >/dev/null
  sleep "$COOLDOWN"
done
