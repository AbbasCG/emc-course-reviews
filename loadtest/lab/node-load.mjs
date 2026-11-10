// k6-equivalent load driver in plain Node (no dependencies) for machines where k6 is not installed.
// It models the SAME student flow, thresholds and metrics as loadtest/review-load.js, so numbers from a local lab
// have the same shape - but it is NOT k6, and a lab run is NOT a Staging/Hostinger run (see ACCEPTANCE.md section 3).
//
//   node loadtest/lab/node-load.mjs --base http://127.0.0.1:8088 --token <t> --run <label> --level 250 \
//        --profile steady --ip-mode single --out results/lab-steady-250.json [--hold 120 --ramp 20 --seed 1]
//
// Profiles: steady (real think times, 30% submit) | burst (think x0.3, 90% submit) | stress (no think time, 50% submit).
// It refuses any host that is not localhost / 127.0.0.1 / ::1 unless --i-confirm-staging yes is given, and even then
// refuses *.edumc.nl hosts that do not start with staging/stage/test/dev/qa/loadtest.
import http from 'node:http';
import https from 'node:https';
import fs from 'node:fs';
import path from 'node:path';

// ---------- arguments ----------
const argv = process.argv.slice(2);
const arg = (name, def) => { const i = argv.indexOf(`--${name}`); return i >= 0 && argv[i + 1] !== undefined ? argv[i + 1] : def; };
const BASE = arg('base', '').replace(/\/+$/, '');
const TOKENS = arg('token', '').split(',').map((s) => s.trim()).filter(Boolean);
const RUN = arg('run', '').toLowerCase();
const LEVEL = parseInt(arg('level', '50'), 10);
const PROFILE = arg('profile', 'steady');
const IP_MODE = arg('ip-mode', 'single');
const DEF = { steady: { ramp: 30, hold: 120, think: 1, submit: 0.3 }, burst: { ramp: 15, hold: 90, think: 0.3, submit: 0.9 }, stress: { ramp: 15, hold: 60, think: 0, submit: 0.5 } }[PROFILE];
if (!DEF) { console.error(`unknown profile ${PROFILE}`); process.exit(2); }
const RAMP = parseFloat(arg('ramp', DEF.ramp));
const HOLD = parseFloat(arg('hold', DEF.hold));
const THINK_SCALE = parseFloat(arg('think-scale', DEF.think));
const SUBMIT_RATIO = parseFloat(arg('submit-ratio', DEF.submit));
const NO_EMAIL_RATIO = 0.1;
const TIMEOUT_MS = parseInt(arg('timeout-ms', '30000'), 10);
const KEEPALIVE = arg('keepalive', 'yes') === 'yes';
const OUT = arg('out', '');
const THINK_BROWSE = [5, 20];
const THINK_FORM = [25, 70];

// ---------- safety ----------
function fail(msg) { console.error(`REFUSED: ${msg}`); process.exit(2); }
if (!BASE) fail('--base is required');
const url = new URL(BASE);
const host = url.hostname.toLowerCase();
const isLocal = ['localhost', '127.0.0.1', '[::1]', '::1'].includes(host);
if (!isLocal) {
  if (arg('i-confirm-staging', '') !== 'yes') fail(`${host} is not a local lab host; pass --i-confirm-staging yes only for a Staging copy with written permission.`);
  if (/(^|\.)edumc\.nl$/.test(host) && !/^(staging|stage|test|dev|qa|loadtest)[.-]/.test(host)) fail(`${host} looks like production.`);
}
if (!TOKENS.length) fail('--token is required (printed by `php artisan loadtest:prepare`)');
if (!/^[a-z0-9]{3,16}$/.test(RUN)) fail('--run must be the 3-16 character label from loadtest:prepare');

// ---------- tiny seeded RNG so two runs can be compared ----------
let seed = parseInt(arg('seed', String(Date.now() % 2147483647)), 10) || 1;
const rand = () => { seed = (seed * 48271) % 2147483647; return seed / 2147483647; };
const rnd = (a, b) => a + rand() * (b - a);
const rint = (a, b) => Math.floor(rnd(a, b + 1));
const pick = (arr) => arr[rint(0, arr.length - 1)];
const DIFFICULTY = ['سهل', 'متوسط', 'صعب'];

// ---------- http ----------
const lib = url.protocol === 'https:' ? https : http;
const agent = new lib.Agent({ keepAlive: KEEPALIVE, maxSockets: Infinity, timeout: TIMEOUT_MS });

function request(method, pathname, { headers = {}, body = null, jar = null } = {}) {
  return new Promise((resolve) => {
    const t0 = performance.now();
    const h = { Host: url.host, 'User-Agent': 'emc-lab-load/1', ...headers };
    if (jar && Object.keys(jar).length) h.Cookie = Object.entries(jar).map(([k, v]) => `${k}=${v}`).join('; ');
    if (body !== null) { h['Content-Type'] = 'application/x-www-form-urlencoded'; h['Content-Length'] = Buffer.byteLength(body); }
    let done = false;
    const finish = (r) => { if (!done) { done = true; resolve({ ms: performance.now() - t0, ...r }); } };
    const req = lib.request({ protocol: url.protocol, hostname: url.hostname, port: url.port || (url.protocol === 'https:' ? 443 : 80), path: pathname, method, headers: h, agent }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => {
        for (const c of res.headers['set-cookie'] || []) { const [kv] = c.split(';'); const i = kv.indexOf('='); if (jar && i > 0) jar[kv.slice(0, i)] = kv.slice(i + 1); }
        finish({ status: res.statusCode, requestId: res.headers['x-request-id'] || null, body: Buffer.concat(chunks).toString('utf8') });
      });
      res.on('error', () => finish({ status: 0, requestId: null, body: '' }));
    });
    req.setTimeout(TIMEOUT_MS, () => { req.destroy(); finish({ status: 0, requestId: null, body: '', timeout: true }); });
    req.on('error', () => finish({ status: 0, requestId: null, body: '' }));
    if (body !== null) req.write(body);
    req.end();
  });
}

// ---------- metrics ----------
const t0Wall = Date.now();
const samples = [];                      // {name,status,ms,at}
const statusCount = {};
const named = { GET_review_show: [], POST_review_store: [], GET_review_success: [] };
const counters = { submits_ok: 0, duplicate_422: 0, in_flight_409: 0, csrf_missing: 0, no_request_id: 0, iterations: 0, unexpected: 0, timeouts: 0 };
const unexpectedLog = [];
function record(name, r) {
  samples.push({ name, status: r.status, ms: r.ms, at: (performance.now() - tStart) / 1000 });
  statusCount[r.status] = (statusCount[r.status] || 0) + 1;
  named[name].push(r.ms);
  if (!r.requestId) counters.no_request_id++;
  if (r.timeout) counters.timeouts++;
  const ok = r.status === 200 || (r.status >= 300 && r.status < 400) || r.status === 422 || r.status === 409;
  if (!ok) { counters.unexpected++; if (unexpectedLog.length < 25) unexpectedLog.push({ name, status: r.status, request_id: r.requestId }); }
}

const sleep = (s) => new Promise((r) => setTimeout(r, Math.max(0, s) * 1000));
const think = (a, b) => (THINK_SCALE > 0 ? sleep(rnd(a, b) * THINK_SCALE) : Promise.resolve());

function clientHeaders(vu, iter) {
  if (IP_MODE === 'single') return {};
  const n = vu * 100000 + iter;
  if (IP_MODE === 'distinct') return { 'X-Forwarded-For': `10.${(n >> 16) & 255}.${(n >> 8) & 255}.${(n & 255) || 1}` };
  const groups = parseInt(IP_MODE.split(':')[1] || '10', 10);
  return { 'X-Forwarded-For': `10.0.${(vu % groups) + 1}.1` };
}

async function oneIteration(vu, iter) {
  counters.iterations++;
  const token = TOKENS[(vu + iter) % TOKENS.length];
  const jar = {};                                  // a new browser without cookies each iteration
  const extra = clientHeaders(vu, iter);
  const page = await request('GET', `/review/${token}`, { jar, headers: { Accept: 'text/html', ...extra } });
  record('GET_review_show', page);
  if (page.status !== 200) { await sleep(rnd(1, 3)); return; }
  const m = /'X-CSRF-TOKEN':\s*'([^']+)'/.exec(page.body);
  if (!m) { counters.csrf_missing++; return; }
  if (rand() >= SUBMIT_RATIO) { await think(...THINK_BROWSE); return; }
  await think(...THINK_FORM);
  const form = new URLSearchParams({
    student_name: `Load Test ${vu}-${iter}`,
    overall_rating: String(rint(3, 5)), course_content_rating: String(rint(3, 5)), instructor_rating: String(rint(3, 5)), organization_rating: String(rint(3, 5)),
    difficulty_level: pick(DIFFICULTY),
    what_did_you_like: 'synthetic load-test text: content and delivery were clear.',
    what_can_be_improved: 'synthetic load-test text: more practice time.',
    would_recommend: rand() < 0.8 ? '1' : '0',
    additional_notes: rand() < 0.3 ? 'synthetic note' : '',
    email: rand() >= NO_EMAIL_RATIO ? `lt-${RUN}-${vu}-${iter}@loadtest.invalid` : '',
  }).toString();
  const post = await request('POST', `/review/${token}`, { jar, body: form, headers: { 'X-CSRF-TOKEN': m[1], Accept: 'application/json', ...extra } });
  record('POST_review_store', post);
  if (post.status === 422) counters.duplicate_422++;
  if (post.status === 409) counters.in_flight_409++;
  if (post.status !== 200) return;
  counters.submits_ok++;
  const success = await request('GET', `/review/${token}/success`, { jar, headers: extra });
  record('GET_review_success', success);
  await sleep(THINK_SCALE > 0 ? rnd(1, 3) : 0);
}

// ---------- run ----------
const tStart = performance.now();
const stopAt = RAMP + HOLD;                         // seconds from start after which no new iteration begins
let active = 0, peakActive = 0;
const vuTasks = [];
for (let vu = 1; vu <= LEVEL; vu++) {
  const startDelay = (RAMP * (vu - 1)) / Math.max(1, LEVEL - 1);
  vuTasks.push((async () => {
    await sleep(startDelay);
    active++; peakActive = Math.max(peakActive, active);
    for (let iter = 0; (performance.now() - tStart) / 1000 < stopAt; iter++) await oneIteration(vu, iter);
    active--;
  })());
}
console.log(`lab run=${RUN} profile=${PROFILE} level=${LEVEL} ip_mode=${IP_MODE} ramp=${RAMP}s hold=${HOLD}s think_scale=${THINK_SCALE} submit_ratio=${SUBMIT_RATIO} keepalive=${KEEPALIVE} base=${BASE}`);
await Promise.all(vuTasks);
agent.destroy();
const durationS = (performance.now() - tStart) / 1000;

// ---------- reporting ----------
const pct = (arr, p) => { if (!arr.length) return null; const s = [...arr].sort((a, b) => a - b); return s[Math.min(s.length - 1, Math.ceil((p / 100) * s.length) - 1)]; };
const avg = (arr) => (arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : null);
const stats = (arr) => ({ n: arr.length, avg: avg(arr), p50: pct(arr, 50), p95: pct(arr, 95), p99: pct(arr, 99), max: arr.length ? Math.max(...arr) : null });
const all = samples.map((s) => s.ms);
// per-5s timeline (rps, p95, errors) to see WHEN it degrades, not just how much
const buckets = {};
for (const s of samples) { const b = Math.floor(s.at / 5) * 5; (buckets[b] ||= []).push(s); }
const timeline = Object.entries(buckets).map(([b, arr]) => ({ t: +b, rps: +(arr.length / 5).toFixed(1), p95: Math.round(pct(arr.map((x) => x.ms), 95)), errors: arr.filter((x) => x.status >= 500 || x.status === 0 || x.status === 429).length, vus: undefined })).sort((a, b) => a.t - b.t);
const holdSamples = samples.filter((s) => s.at >= RAMP && s.at <= stopAt);
const peakRps = Math.max(0, ...timeline.map((x) => x.rps));
const checks = {
  get_p95_lt_1500: pct(named.GET_review_show, 95) < 1500, get_p99_lt_3000: pct(named.GET_review_show, 99) < 3000,
  post_p95_lt_2500: named.POST_review_store.length ? pct(named.POST_review_store, 95) < 2500 : null,
  post_p99_lt_5000: named.POST_review_store.length ? pct(named.POST_review_store, 99) < 5000 : null,
  success_p95_lt_1500: named.GET_review_success.length ? pct(named.GET_review_success, 95) < 1500 : null,
  server_errors_lt_1pct: samples.filter((s) => s.status >= 500 || s.status === 0).length / Math.max(1, samples.length) < 0.01,
  no_502_503_504: !(statusCount[502] || statusCount[503] || statusCount[504]),
  no_429: !statusCount[429],
  every_response_has_request_id: counters.no_request_id === 0,
};
const result = {
  environment: 'LOCAL LAB (not Staging, not Hostinger)', run: RUN, profile: PROFILE, level: LEVEL, ip_mode: IP_MODE, ramp_s: RAMP, hold_s: HOLD, think_scale: THINK_SCALE, submit_ratio: SUBMIT_RATIO, keepalive: KEEPALIVE,
  started_at: new Date(t0Wall).toISOString(), duration_s: +durationS.toFixed(1), peak_active_vus: peakActive,
  requests: samples.length, rps_avg: +(samples.length / durationS).toFixed(2), rps_peak_5s: peakRps, rps_hold_avg: +(holdSamples.length / Math.max(1, HOLD)).toFixed(2),
  statuses: statusCount, counters, latency_ms: { all: stats(all), ...Object.fromEntries(Object.entries(named).map(([k, v]) => [k, stats(v)])) },
  acceptance_checks_latency_and_errors_only: checks, unexpected_samples: unexpectedLog, timeline,
};
const r0 = (x) => (x === null ? 'n/a' : Math.round(x));
const line = (k, s) => `${k.padEnd(20)} n=${String(s.n).padStart(6)} avg=${String(r0(s.avg)).padStart(5)} p50=${String(r0(s.p50)).padStart(5)} p95=${String(r0(s.p95)).padStart(5)} p99=${String(r0(s.p99)).padStart(5)} max=${String(r0(s.max)).padStart(6)} ms`;
console.log(line('ALL', result.latency_ms.all));
for (const k of Object.keys(named)) console.log(line(k, result.latency_ms[k]));
console.log(`requests=${result.requests} duration=${result.duration_s}s rps_avg=${result.rps_avg} rps_peak(5s)=${result.rps_peak_5s} peak_vus=${peakActive}`);
console.log('statuses:', JSON.stringify(statusCount), ' counters:', JSON.stringify(counters));
console.log('checks:', JSON.stringify(checks));
console.log(`NEXT: php artisan loadtest:verify ${RUN} --expected=${counters.submits_ok}`);
if (OUT) { fs.mkdirSync(path.dirname(OUT), { recursive: true }); fs.writeFileSync(OUT, JSON.stringify(result, null, 2)); console.log(`wrote ${OUT}`); }
