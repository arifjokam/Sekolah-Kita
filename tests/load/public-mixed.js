/**
 * Mixed traffic — the scenario that actually finds the capacity limit.
 *
 *   BASE_URL=https://staging.sekolah.example \
 *   k6 run tests/load/public-mixed.js
 *
 * Cached public reads are cheap. Authenticated pages and writes are not, and
 * they are what saturates PHP-FPM first. A site that passes public-read at
 * 1000 VUs can still collapse the moment teachers log in and open the
 * dashboard together — which is exactly what happens at 07:00 on a school
 * morning.
 */

import http from 'k6/http';
import { check } from 'k6';
import {
  BASE_URL,
  headers,
  formHeaders,
  publicP95,
  authP95,
  record,
  cookieHeader,
  csrfFrom,
  humanThink,
  pick,
  standardThresholds,
  THRESHOLDS,
} from './common.js';
import { PAGES } from './data.js';

export const options = {
  scenarios: {
    mixed: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 50 },
        { duration: '5m', target: 50 },
        { duration: '3m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '3m', target: 250 },
        { duration: '5m', target: 250 },
        { duration: '2m', target: 0 },
      ],
    },
  },
  thresholds: {
    ...standardThresholds(),
    'auth_latency': [`p(95)<${THRESHOLDS.authP95}`],
  },
};

export function setup() {
  const email = __ENV.TEST_EMAIL;
  const password = __ENV.TEST_PASSWORD;

  if (!email || !password) {
    throw new Error(
      'Mixed traffic requires TEST_EMAIL and TEST_PASSWORD for a synthetic staging account. ' +
      'Never run this scenario against production.'
    );
  }

  const page = http.get(`${BASE_URL}/login`, { headers: headers() });
  const res = http.post(
    `${BASE_URL}/login`,
    { email, password, _token: csrfFrom(page.body) },
    { headers: formHeaders(), redirects: 'manual' }
  );

  const ok = check(res, {
    'login succeeded': (r) => r.status === 302,
  });

  if (!ok) {
    throw new Error(`Login failed with status ${res.status}. Check TEST_EMAIL/TEST_PASSWORD.`);
  }

  return { cookies: res.cookies };
}

export default function (data) {
  const auth = { ...headers(), Cookie: cookieHeader(data.cookies) };

  // 1. Public page — a visitor's first impression, cache-served.
  const home = http.get(`${BASE_URL}/`, { headers: headers() });
  record(home, publicP95);

  // 2. Dashboard — session read + MariaDB, never a cache hit.
  const dash = http.get(`${BASE_URL}/dashboard`, { headers: auth });
  if (record(dash, authP95)) {
    check(dash, {
      'dashboard is not the login page': (r) => !String(r.body).includes('name="password"'),
    });
  }

  // 3. Notifications — exercises the recipient timeline index added in
  //    Phase 8. A regression there shows up as latency climbing with the
  //    number of notifications per user.
  const notes = http.get(`${BASE_URL}/notifications`, { headers: auth });
  record(notes, authP95);

  // 4. Another public page, as a real session would interleave.
  const page = http.get(`${BASE_URL}${pick(PAGES)}`, { headers: auth });
  record(page, publicP95);

  humanThink(1, 5);
}
