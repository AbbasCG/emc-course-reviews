// Summarises the server-side view of one run from storage/logs/performance-*.log (JSON lines written by PerformanceMonitor).
//   node loadtest/lab/analyze-perf-log.mjs <log file> <start byte offset> [out.json]
// Needs MONITORING_LOG_ALL=true on the lab, otherwise only slow/error requests are present. Prints aggregates only;
// the log never contains student data, tokens, URLs or raw IPs (see tests/Feature/PerformanceMonitoringTest.php).
import fs from 'node:fs';

const [file, offsetArg, out] = process.argv.slice(2);
if (!file) { console.error('usage: analyze-perf-log.mjs <file> <offset> [out.json]'); process.exit(2); }
const offset = parseInt(offsetArg || '0', 10);
const buf = fs.readFileSync(file);
const lines = buf.subarray(offset).toString('utf8').split('\n').filter(Boolean);
const ev = [];
for (const l of lines) { try { const j = JSON.parse(l); ev.push({ message: j.message, ...j.context }); } catch { /* partial line */ } }

const pct = (arr, p) => { if (!arr.length) return null; const s = [...arr].sort((a, b) => a - b); return s[Math.min(s.length - 1, Math.ceil((p / 100) * s.length) - 1)]; };
const avg = (a) => (a.length ? a.reduce((x, y) => x + y, 0) / a.length : null);
const r1 = (x) => (x === null ? null : Math.round(x * 10) / 10);
const by = {};
for (const e of ev) (by[e.route || 'unmatched'] ||= []).push(e);
const routes = Object.fromEntries(Object.entries(by).map(([r, a]) => [r, {
  n: a.length, duration_ms: { avg: r1(avg(a.map((x) => x.duration_ms))), p95: r1(pct(a.map((x) => x.duration_ms), 95)), p99: r1(pct(a.map((x) => x.duration_ms), 99)), max: r1(Math.max(...a.map((x) => x.duration_ms))) },
  queries_avg: r1(avg(a.map((x) => x.queries ?? 0))), query_ms_avg: r1(avg(a.map((x) => x.query_ms ?? 0))), query_ms_max: r1(Math.max(...a.map((x) => x.query_ms ?? 0))),
  memory_peak_mb_max: Math.max(...a.map((x) => x.memory_peak_mb ?? 0)),
}]));
const statuses = {};
for (const e of ev) statuses[e.status] = (statuses[e.status] || 0) + 1;
const slowQueries = ev.filter((e) => e.slow_queries && e.slow_queries.length);
const res = {
  events: ev.length, laravel_statuses: statuses, routes,
  http_error_events: ev.filter((e) => e.message === 'http.error').length, http_slow_events: ev.filter((e) => e.message === 'http.slow').length,
  requests_with_slow_query_over_threshold: slowQueries.length,
  slow_query_templates: [...new Set(slowQueries.flatMap((e) => e.slow_queries.map((s) => (typeof s === 'string' ? s : s.sql || JSON.stringify(s)))))].slice(0, 5),
};
console.log(JSON.stringify(res, null, 1));
if (out) fs.writeFileSync(out, JSON.stringify(res, null, 2));
