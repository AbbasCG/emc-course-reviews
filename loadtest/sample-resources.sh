#!/usr/bin/env bash
# Read-only resource sampler for a Linux host (Hostinger SSH). Writes one CSV row every INTERVAL seconds.
# It only reads /proc and `ps`; it changes nothing and needs no root.
#
#   loadtest/sample-resources.sh results/resources-<run>.csv [INTERVAL_SECONDS=5] [DURATION_SECONDS=900]
#
# NOTE: on shared hosting /proc/meminfo and /proc/loadavg describe the whole HOST, not your 3 GB / CPU quota. Use the
# php_rss_mb column (your own processes) and the hPanel graphs for the plan limits. Default duration (900 s) is shorter than a
# multi-level session: pass a longer third argument.
# Optional MySQL counters: export MYSQL_CMD="mysql -h HOST -u USER -pPASSWORD -N -e" before running, e.g. with a
# staging-only account (never production credentials). Leave unset to skip.
set -u
OUT="${1:-resources.csv}"; INTERVAL="${2:-5}"; DURATION="${3:-900}"
USER_NAME="$(id -un)"
echo "utc_time,load1,mem_used_mb,mem_total_mb,php_procs,php_rss_mb,all_user_procs,mysql_threads_connected,mysql_threads_running,mysql_max_used" > "$OUT"
end=$(( $(date +%s) + DURATION ))
while [ "$(date +%s)" -lt "$end" ]; do
  load1=$(cut -d' ' -f1 /proc/loadavg)
  mem_total=$(awk '/MemTotal/ {printf "%d", $2/1024}' /proc/meminfo)
  mem_avail=$(awk '/MemAvailable/ {printf "%d", $2/1024}' /proc/meminfo)
  php_procs=$(ps -u "$USER_NAME" -o comm= | grep -c -E '^(lsphp|php)')
  php_rss=$(ps -u "$USER_NAME" -o comm=,rss= | awk '/^(lsphp|php)/ {s+=$2} END {printf "%d", s/1024}')
  all=$(ps -u "$USER_NAME" -o pid= | wc -l)
  tc=""; tr=""; mu=""
  if [ -n "${MYSQL_CMD:-}" ]; then      # ONE connection per tick (several would inflate the number being measured)
    st=$($MYSQL_CMD "SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Max_used_connections')" 2>/dev/null)
    tc=$(echo "$st" | awk '$1=="Threads_connected"{print $2}'); tr=$(echo "$st" | awk '$1=="Threads_running"{print $2}'); mu=$(echo "$st" | awk '$1=="Max_used_connections"{print $2}')
  fi
  echo "$(date -u +%FT%TZ),$load1,$((mem_total - mem_avail)),$mem_total,$php_procs,$php_rss,$all,$tc,$tr,$mu" >> "$OUT"
  sleep "$INTERVAL"
done
echo "wrote $OUT"
