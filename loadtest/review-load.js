// k6 load test for the EMC review form (Laravel). STAGING / ISOLATED LAB ONLY - never production.
//
// Flow (routes/web.php, ReviewController, resources/views/review/show.blade.php):
//   GET  /review/{token}          HTML page. Starts a session (database driver) and embeds csrf_token() in an inline <script>.
//   POST /review/{token}          fetch() with X-CSRF-TOKEN + Accept: application/json, FormData of 11 fields.
//                                 Limits (PR-2): per IP 300/min (shared Wi-Fi friendly) + per session 6/min. JSON: 200 {redirect},
//                                 409, 422 {errors}, 429, 500.
//   GET  /review/{token}/success  followed by the browser after a 200.
//
// Run (see loadtest/README.md):
//   k6 run -e BASE_URL=https://staging.example.test -e I_CONFIRM_STAGING=yes -e TOKENS=<token> -e RUN_ID=<run> -e LEVELS=50 loadtest/review-load.js
//
// CONCURRENT USERS vs REQUESTS PER SECOND: a "user" (VU) spends most of its time reading/typing (think time), so
// 500 steady VUs produce only ~10-20 req/s. PROFILE chooses the model:
//   steady  500 students with the form open, realistic think times        -> measures sustained concurrency
//   burst   everybody opens the link and submits within a short window     -> measures the arrival spike (QR code on a slide)
//   stress  no think time (THINK_SCALE=0)                                  -> measures the maximum request rate / breaking point

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate, Counter } from 'k6/metrics';

// ---------- configuration (all via -e) ----------
const BASE_URL = (__ENV.BASE_URL || '').replace(/\/+$/, '');
const TOKENS = (__ENV.TOKENS || '').split(',').map((s) => s.trim()).filter(Boolean);
const LEVELS = (__ENV.LEVELS || '').split(',').map((s) => parseInt(s, 10)).filter(Boolean);   // exactly ONE level per run (thresholds are global)
if (LEVELS.length !== 1) throw new Error('Set -e LEVELS=<one level, e.g. 250>. Run each level as its own k6 process so thresholds and the abort rule apply to that level only.');
const PROFILE = __ENV.PROFILE || 'steady';
const DEFAULTS = {
  steady: { ramp: '3m', hold: '6m', thinkScale: 1, submit: 0.3 },
  burst: { ramp: '30s', hold: '3m', thinkScale: 0.3, submit: 0.9 },
  stress: { ramp: '1m', hold: '3m', thinkScale: 0, submit: 0.5 },
}[PROFILE] || { ramp: '3m', hold: '6m', thinkScale: 1, submit: 0.3 };
const RAMP = __ENV.RAMP || DEFAULTS.ramp;
const HOLD = __ENV.HOLD || DEFAULTS.hold;
const RAMPDOWN = __ENV.RAMPDOWN || '30s';
const COOLDOWN_S = parseInt(__ENV.COOLDOWN_S || '120', 10);      // idle gap between levels so the server drains
const SUBMIT_RATIO = parseFloat(__ENV.SUBMIT_RATIO || String(DEFAULTS.submit));
const THINK_SCALE = parseFloat(__ENV.THINK_SCALE || String(DEFAULTS.thinkScale));
const THINK_BROWSE = [5, 20];
const THINK_FORM = [25, 70];
// IP_MODE: single   = every student comes from the load generator's one IP (a whole classroom behind one Wi-Fi/NAT)
//          distinct = a unique X-Forwarded-For per student (needs TRUSTED_PROXIES=<generator ip> on the target)
//          groups:N = students are spread over N shared networks (X-Forwarded-For, same requirement)
const IP_MODE = __ENV.IP_MODE || (__ENV.XFF_MODE === '1' ? 'distinct' : 'single');
const ALLOW_429 = false;                              // a 429 is never 'expected' in an acceptance run: it is counted and judged per IP_MODE (ACCEPTANCE.md row 5)
const BURST_SPREAD_S = parseInt(__ENV.BURST_SPREAD_S || '30', 10);   // burst: every student arrives within this many seconds
const FETCH_ASSETS = __ENV.FETCH_ASSETS === '1';      // also fetch Vite css/js once per VU (cold browser)
const NO_EMAIL_RATIO = parseFloat(__ENV.NO_EMAIL_RATIO || '0.1');
const RUN_ID = (__ENV.RUN_ID || String(Date.now())).toLowerCase();   // the label created by `php artisan loadtest:prepare`
const OUT_DIR = __ENV.OUT_DIR || '.';

// ---------- metrics ----------
const appErrors = new Rate('app_errors');
const csrfMissing = new Counter('csrf_token_missing');
const hostMismatch = new Counter('form_url_host_mismatch');
const submitsOk = new Counter('submits_ok');
const submitsDup = new Counter('submits_duplicate_422');
const submitsInFlight = new Counter('submits_in_flight_409');
const noRequestId = new Counter('responses_without_request_id');
let loggedErrors = 0;

http.setResponseCallback(http.expectedStatuses({ min: 200, max: 399 }, 409, 422, 429));

const secs = (d) => { const m = /^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/.exec(d); if (!m || !d) throw new Error(`Unparseable duration '${d}' (use e.g. 90s, 5m, 1m30s)`); return (+m[1] || 0) * 3600 + (+m[2] || 0) * 60 + (+m[3] || 0); };

function buildScenarios() {
  const scenarios = {};
  let offsetS = 0;
  for (const level of LEVELS) {
    if (PROFILE === 'burst') {                         // ONE wave: each student opens the link once
      scenarios[`vu_${level}`] = { executor: 'per-vu-iterations', vus: level, iterations: 1, maxDuration: '15m', tags: { level: String(level) }, exec: 'student' };
      continue;
    }
    scenarios[`vu_${level}`] = {
      executor: 'ramping-vus', startVUs: 0, startTime: `${offsetS}s`,
      stages: [{ duration: RAMP, target: level }, { duration: HOLD, target: level }, { duration: RAMPDOWN, target: 0 }],
      gracefulRampDown: '120s', gracefulStop: '120s', tags: { level: String(level) }, exec: 'student',
    };
    offsetS += secs(RAMP) + secs(HOLD) + secs(RAMPDOWN) + 120 + COOLDOWN_S;
  }
  return scenarios;
}

const thresholds = {
  // Acceptance criteria: loadtest/ACCEPTANCE.md (fixed BEFORE the first run)
  'http_req_duration{name:GET_review_show}': ['p(95)<1500', 'p(99)<3000', { threshold: 'p(95)<5000', abortOnFail: true, delayAbortEval: '60s' }],
  'http_req_duration{name:POST_review_store}': ['p(95)<2500', 'p(99)<5000'],
  'http_req_duration{name:GET_review_success}': ['p(95)<1500'],
  app_errors: ['rate<0.01'],
  http_req_failed: ['rate<0.01', { threshold: 'rate<0.05', abortOnFail: true, delayAbortEval: '60s' }],
  checks: ['rate>0.99'],
  responses_without_request_id: ['count==0'],
};
for (const s of [200, 302, 409, 419, 422, 429, 403, 404, 421]) thresholds[`http_reqs{status:${s}}`] = ['count>=0'];
for (const s of [403, 500, 502, 503, 504, 508, 520, 521, 522, 523, 524, 525, 526, 527, 0]) thresholds[`http_reqs{status:${s}}`] = ['count==0'];   // acceptance row 4: none of these may occur
for (const level of LEVELS) {                    // per-level sub-metrics (k6 only reports tagged sub-metrics that have a threshold)
  thresholds[`http_req_duration{level:${level}}`] = ['max>=0'];
  thresholds[`http_reqs{level:${level}}`] = ['count>=0'];
  thresholds[`http_req_failed{level:${level}}`] = ['rate>=0'];
  thresholds[`submits_ok{level:${level}}`] = ['count>=0'];
}

export const options = {
  scenarios: buildScenarios(),
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
  discardResponseBodies: false,
  noVUConnectionReuse: true,   // each student is a new browser: no keep-alive shared between students
  userAgent: 'Mozilla/5.0 (EMC-loadtest; staging only)',
  thresholds,
};

// ---------- helpers ----------
const rnd = (min, max) => min + Math.random() * (max - min);
const rint = (min, max) => Math.floor(rnd(min, max + 1));
const pick = (arr) => arr[rint(0, arr.length - 1)];
const DIFFICULTY = ['سهل', 'متوسط', 'صعب'];
const hostOf = (u) => { const m = /^https?:\/\/([^/:?#]+)/i.exec(u); return m ? m[1].toLowerCase() : ''; };
const think = (min, max) => { if (THINK_SCALE > 0) sleep(rnd(min, max) * THINK_SCALE); };

function clientHeaders() {
  if (IP_MODE === 'single') return {};
  const n = __VU * 100000 + __ITER;
  if (IP_MODE === 'distinct') return { 'X-Forwarded-For': `10.${(n >> 16) & 255}.${(n >> 8) & 255}.${(n & 255) || 1}` };
  const groups = parseInt(IP_MODE.split(':')[1] || '10', 10);
  return { 'X-Forwarded-For': `10.0.${(__VU % groups) + 1}.1` };   // N shared networks
}

function outcome(res, endpoint) {
  const s = res.status;
  const ok = s === 200 || (s >= 300 && s < 400) || (s === 429 && ALLOW_429);
  appErrors.add(!ok, { endpoint });
  if (!res.headers['X-Request-Id']) noRequestId.add(1);
  if (!ok && loggedErrors < 25) {                                  // request id = how to find this request in storage/logs
    loggedErrors++;
    console.warn(`unexpected status=${s} endpoint=${endpoint} request_id=${res.headers['X-Request-Id'] || 'none'}`);
  }
  return ok;
}

// ---------- lifecycle ----------
export function setup() {
  if (!BASE_URL) throw new Error('BASE_URL is required (staging / lab URL).');
  if (__ENV.I_CONFIRM_STAGING !== 'yes') throw new Error('Set -e I_CONFIRM_STAGING=yes to confirm BASE_URL is a STAGING copy with synthetic data and written permission for load testing.');
  const host = hostOf(BASE_URL);
  const protectedHosts = (__ENV.PRODUCTION_HOSTS || 'reviews.edumc.nl').split(',').map((h) => h.trim().toLowerCase());
  const allowed = (__ENV.ALLOWED_HOSTS || '').split(',').map((h) => h.trim().toLowerCase()).filter(Boolean);   // exact Staging host names (optional)
  const looksStaging = /^(staging|stage|test|dev|qa|loadtest)[.-][a-z0-9.-]+$/.test(host) || ['localhost', '127.0.0.1'].includes(host);
  if (/[@\s]/.test(BASE_URL) || protectedHosts.includes(host) || !(allowed.includes(host) || looksStaging)) {
    throw new Error(`Refusing to run against '${BASE_URL}': the host must be an explicit Staging/lab host (staging.*, stage.*, test.*, dev.*, qa.*, loadtest.*, localhost) or listed in ALLOWED_HOSTS. Production is never allowed.`);
  }
  if (TOKENS.length === 0) throw new Error('TOKENS is required: tokens printed by `php artisan loadtest:prepare`.');
  if (!/^[a-z0-9]{3,16}$/.test(RUN_ID)) throw new Error('RUN_ID must be the 3-16 character label from `loadtest:prepare`.');
  for (const t of TOKENS) {
    const r = http.get(`${BASE_URL}/review/${t}`, { tags: { name: 'setup_preflight' } });
    if (r.status !== 200 || !/X-CSRF-TOKEN/.test(r.body || '')) throw new Error(`Preflight failed for token ${t}: status ${r.status}`);
  }
  return { startedAt: new Date().toISOString() };
}

const assetsFetchedByVu = {};

export function student() {
  if (PROFILE === 'burst') sleep(Math.random() * BURST_SPREAD_S);   // arrival time of this student inside the wave
  const token = TOKENS[(__VU + __ITER) % TOKENS.length];
  const jar = new http.CookieJar();                // every iteration = a new browser without a session cookie
  const extra = clientHeaders();
  const page = http.get(`${BASE_URL}/review/${token}`, { jar, headers: Object.assign({ Accept: 'text/html' }, extra), tags: { name: 'GET_review_show' } });
  const pageOk = outcome(page, 'GET_review_show');
  check(page, { 'review page 200': (r) => r.status === 200 });
  if (!pageOk || page.status !== 200) { sleep(rnd(1, 3)); return; }

  const body = page.body || '';
  const m = /'X-CSRF-TOKEN':\s*'([^']+)'/.exec(body);
  if (!m) { csrfMissing.add(1); appErrors.add(true, { endpoint: 'csrf_parse' }); sleep(1); return; }
  const action = /fetch\('([^']+)'/.exec(body);
  if (action && hostOf(action[1]) !== hostOf(BASE_URL)) hostMismatch.add(1);   // APP_URL of the target points elsewhere

  if (FETCH_ASSETS && !assetsFetchedByVu[__VU]) {
    assetsFetchedByVu[__VU] = true;
    const urls = [];
    const re = /(?:href|src)="([^"]*\/build\/assets\/[^"]+)"/g;
    let a;
    while ((a = re.exec(body)) !== null) urls.push(a[1].startsWith('/') ? BASE_URL + a[1] : a[1]);
    if (urls.length) http.batch(urls.map((u) => ['GET', u, null, { tags: { name: 'static_asset' } }]));
  }

  if (Math.random() >= SUBMIT_RATIO) { think(THINK_BROWSE[0], THINK_BROWSE[1]); return; }
  think(THINK_FORM[0], THINK_FORM[1]);              // typing the 4-step form

  const payload = {
    student_name: `Load Test ${__VU}-${__ITER}`,
    overall_rating: String(rint(3, 5)), course_content_rating: String(rint(3, 5)), instructor_rating: String(rint(3, 5)), organization_rating: String(rint(3, 5)),
    difficulty_level: pick(DIFFICULTY),
    what_did_you_like: 'synthetic load-test text: content and delivery were clear.',
    what_can_be_improved: 'synthetic load-test text: more practice time.',
    would_recommend: Math.random() < 0.8 ? '1' : '0',
    additional_notes: Math.random() < 0.3 ? 'synthetic note' : '',
    email: Math.random() >= NO_EMAIL_RATIO ? `lt-${RUN_ID}-vu${__VU}-it${__ITER}@loadtest.invalid` : '',
  };
  const post = http.post(`${BASE_URL}/review/${token}`, payload, {
    jar, headers: Object.assign({ 'X-CSRF-TOKEN': m[1], Accept: 'application/json' }, extra), tags: { name: 'POST_review_store' },
  });
  const postOk = outcome(post, 'POST_review_store');
  check(post, { 'submit 200 + redirect': (r) => r.status === 200 && (() => { try { return !!r.json('redirect'); } catch (e) { return false; } })() });
  if (post.status === 422) submitsDup.add(1);
  if (post.status === 409) submitsInFlight.add(1);
  if (!postOk || post.status !== 200) return;
  submitsOk.add(1);

  const success = http.get(`${BASE_URL}/review/${token}/success`, { jar, headers: extra, tags: { name: 'GET_review_success' } });
  outcome(success, 'GET_review_success');
  sleep(THINK_SCALE > 0 ? rnd(1, 3) : 0);
}

// ---------- reporting ----------
const val = (data, key, stat) => { const x = data.metrics[key]; return x && x.values && x.values[stat] !== undefined ? x.values[stat] : null; };
const f = (x, d = 0) => (x === null || x === undefined ? 'n/a' : Number(x).toFixed(d));

export function handleSummary(data) {
  const lines = [`run_id=${RUN_ID} host=${hostOf(BASE_URL)} profile=${PROFILE} level=${LEVELS.join(',')} ip_mode=${IP_MODE} ramp=${RAMP} hold=${HOLD} think_scale=${THINK_SCALE} submit_ratio=${SUBMIT_RATIO}`];
  lines.push(`REACHED users (max concurrent VUs actually running): ${f(val(data, 'vus', 'max'))} of requested ${LEVELS.join(',')}`);
  for (const level of LEVELS) {
    const d = `http_req_duration{level:${level}}`;
    lines.push(`level ${level}: reqs=${f(val(data, `http_reqs{level:${level}}`, 'count'))} rps=${f(val(data, `http_reqs{level:${level}}`, 'rate'), 1)} avg=${f(val(data, d, 'avg'))}ms p95=${f(val(data, d, 'p(95)'))}ms p99=${f(val(data, d, 'p(99)'))}ms failed=${f((val(data, `http_req_failed{level:${level}}`, 'rate') || 0) * 100, 2)}% submits_ok=${f(val(data, `submits_ok{level:${level}}`, 'count'))}`);
  }
  for (const ep of ['GET_review_show', 'POST_review_store', 'GET_review_success']) {
    const k = `http_req_duration{name:${ep}}`;
    lines.push(`${ep}: avg=${f(val(data, k, 'avg'))}ms p50=${f(val(data, k, 'med'))}ms p95=${f(val(data, k, 'p(95)'))}ms p99=${f(val(data, k, 'p(99)'))}ms max=${f(val(data, k, 'max'))}ms`);
  }
  lines.push(`throughput overall: ${f(val(data, 'http_reqs', 'rate'), 2)} req/s, total=${f(val(data, 'http_reqs', 'count'))}`);
  const failed = [];
  for (const [k, m] of Object.entries(data.metrics)) for (const [t, r] of Object.entries(m.thresholds || {})) if (r.ok === false) failed.push(`${k}: ${t}`);
  lines.push(failed.length ? 'THRESHOLDS FAILED: ' + failed.join(' | ') : 'thresholds: all passed');
  lines.push('responses by status: ' + [200, 302, 403, 404, 409, 419, 421, 422, 429, 500, 502, 503, 504, 508, 520, 521, 522, 523, 524, 525, 526, 527, 0].map((s) => `${s}=${f(val(data, `http_reqs{status:${s}}`, 'count'))}`).join('  '));
  lines.push(`submits_ok=${f(val(data, 'submits_ok', 'count'))} duplicates_422=${f(val(data, 'submits_duplicate_422', 'count'))} in_flight_409=${f(val(data, 'submits_in_flight_409', 'count'))} csrf_missing=${f(val(data, 'csrf_token_missing', 'count'))} host_mismatch=${f(val(data, 'form_url_host_mismatch', 'count'))} no_request_id=${f(val(data, 'responses_without_request_id', 'count'))}`);
  lines.push(`NEXT: php artisan loadtest:verify ${RUN_ID} --expected=${val(data, 'submits_ok', 'count') || 0}`);
  const text = lines.join('\n') + '\n';
  return { [`${OUT_DIR}/summary-${RUN_ID}.json`]: JSON.stringify(data, null, 2), [`${OUT_DIR}/summary-${RUN_ID}.txt`]: text, stdout: '\n' + text };
}
