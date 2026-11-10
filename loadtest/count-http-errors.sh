#!/usr/bin/env bash
# Counts HTTP statuses in a web-server access log (Combined / Common log format). Read-only.
# 502 and 504 come from the CDN / web server and never reach Laravel, so this is the only place to count them.
#
#   loadtest/count-http-errors.sh access.log
#
# Output is aggregated: status mix, 4xx/5xx per minute, POST /review per IP network (/24, IPv4 only). No full IPs, no URLs with tokens.
set -u
LOG="${1:?usage: $0 access.log}"

echo "== status mix"
awk '{print $9}' "$LOG" | grep -E '^[0-9]{3}$' | sort | uniq -c | sort -rn

echo; echo "== monitored statuses per minute (429 500 502 503 504 508)"
awk '$9 ~ /^(429|500|502|503|504|508)$/ {print substr($4,2,17), $9}' "$LOG" | sort | uniq -c | sort -k2,2 -k3,3 | head -60

echo; echo "== POST /review per client network /24 (a single network with many POSTs = shared Wi-Fi/NAT or a proxy)"
grep -E '"POST /review/' "$LOG" | awk '{split($1,a,"."); if (length(a)==4) print a[1]"."a[2]"."a[3]".0/24"; else print "non-ipv4"}' | sort | uniq -c | sort -rn | head -10

echo; echo "== slowest 10 requests, if the log has the response time as the last field"
awk '{t=$NF; if (t ~ /^[0-9.]+$/) print t, $6, $7}' "$LOG" | sed -E 's#/review/[A-Za-z0-9]+#/review/<token>#' | sort -rn | head -10
