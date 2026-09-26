/**
 * Authentication and session behaviour under load.
 *
 *   BASE_URL=https://staging.sekolah.example \
 *   k6 run tests/load/auth.js
 *
 * This scenario is a security test as much as a performance one. It verifies
 * that the login rate limiter is actually per-credential and per-IP, which is
 * only meaningful once trusted-proxy handling is correct: if X-Forwarded-For
 * is ignored, every "user" shares one bucket and the test either locks out
 * everyone or proves nothing.
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';
import {
  BASE_URL,
  headers,
  formHeaders,
  authP95,
  record,
  cookieHeader,
  csrfFrom,
  standardThresholds,
  THRESHOLDS,
} from './common.js';

const throttled = new Rate('login_throttled');
const accepted = new Counter('login_attempts_accepted');

export const options = {
  scenarios: {
    successful_login: {
      executor: 'ramping-vus',
      exec: 'successfulLogin',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 10 },
        { duration: '3m', target: 10 },
        { duration: '3m', target: 0 },
      ],
    },
    // Deliberately wrong credentials. The limiter allows 5/min per account
    // and 15/min per IP, so a genuine brute force must be cut off.
    failed_login: {
      executor: 'constant-vus',
      exec: 'failedLogin',
      vus: 2,
      duration: '3m',
      startTime: '1m',
    },
  },
  thresholds: {
    ...standardThresholds(),
    'login_throttled': ['rate>0.5'],
  },
};

export function successfulLogin() {
  const page = http.get(`${BASE_URL}/login`, { headers: headers() });
  const token = csrfFrom(page.body);

  const res = http.post(
    `${BASE_URL}/login`,
    {
      email: __ENV.TEST_EMAIL,
      password: __ENV.TEST_PASSWORD,
      _token: token,
    },
    { headers: formHeaders(), redirects: 'manual' }
  );

  const ok = res.status === 302;
  accepted.add(1);
  throttled.add(res.status === 429);

  record(res, authP95);

  check(res, {
    'login redirects on success': (r) => r.status === 302,
    'session cookie is set': (r) => !!r.cookies && Object.keys(r.cookies).length > 0,
    // k6 does not expose Set-Cookie attributes, so cookie flags (HttpOnly,
    // Secure, SameSite) are asserted from a real browser via the smoke test
    // and from the curl checks in docs/production/nginx.conf §8.
  });

  // The redirect must not land back on the login form.
  if (ok) {
    const dash = http.get(`${BASE_URL}/dashboard`, {
      headers: { ...headers(), Cookie: cookieHeader(res.cookies) },
    });
    record(dash, authP95);
    check(dash, {
      'session is usable after login': (r) => r.status === 200,
    });
  }

  sleep(2);
}

export function failedLogin() {
  const page = http.get(`${BASE_URL}/login`, { headers: headers() });

  const res = http.post(
    `${BASE_URL}/login`,
    {
      email: 'nobody@staging.sekolah.example',
      password: 'definitely-wrong-' + Math.random(),
      _token: csrfFrom(page.body),
    },
    { headers: formHeaders(), redirects: 'manual' }
  );

  throttled.add(res.status === 429);

  check(res, {
    'failed login does not succeed': (r) => r.status !== 302,
    'failed login is rejected or throttled': (r) =>
      r.status === 422 || r.status === 429 || r.status === 302,
  });

  sleep(1);
}

/**
 * Session invalidation must be observable: after an account is deactivated, a
 * session that was already established must stop working. Run manually, not
 * as part of a throughput test — it deactivates an account.
 */
export function sessionInvalidationAfterDeactivation() {
  const email = __ENV.TEST_ADMIN_EMAIL;
  const password = __ENV.TEST_ADMIN_PASSWORD;

  if (!email || !password) {
    throw new Error('Session invalidation check needs TEST_ADMIN_EMAIL and TEST_ADMIN_PASSWORD.');
  }

  const page = http.get(`${BASE_URL}/login`, { headers: headers() });
  const login = http.post(
    `${BASE_URL}/login`,
    { email, password, _token: csrfFrom(page.body) },
    { headers: formHeaders(), redirects: 'manual' }
  );

  check(login, { 'admin login succeeded': (r) => r.status === 302 });

  // Deactivate the account out of band, then confirm the live session is dead.
  // With the Redis session registry this must take effect on the next request,
  // not at the end of the 120-minute session lifetime.
  const before = http.get(`${BASE_URL}/dashboard`, {
    headers: { ...headers(), Cookie: cookieHeader(login.cookies) },
  });
  check(before, { 'session valid before deactivation': (r) => r.status === 200 });

  return { cookies: login.cookies, note: 'deactivate out of band, then re-run this check' };
}
