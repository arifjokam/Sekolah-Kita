/**
 * Smoke test — correctness gate.
 *
 * Run this before any performance scenario. A load test that silently 404s or
 * renders a PHP error page produces beautiful, meaningless latency numbers.
 *
 *   BASE_URL=https://staging.sekolah.example k6 run tests/load/smoke.js
 */

import http from 'k6/http';
import { check } from 'k6';
import { BASE_URL, headers, THRESHOLDS } from './common.js';

export const options = {
  vus: 1,
  iterations: 1,
  thresholds: {
    checks: ['rate==1.00'],
    http_req_failed: [`rate<${THRESHOLDS.errorRate}`],
  },
};

const PUBLIC_PAGES = [
  '/',
  '/berita',
  '/prestasi',
  '/galeri',
  '/guru',
  '/profil-sekolah',
  '/ppdb',
  '/kontak',
  '/login',
  '/forgot-password',
];

/**
 * Health endpoints have deliberately different contracts:
 *
 *  /health       liveness  — must stay 200 even when dependencies are down,
 *                             so a load balancer does not remove every node
 *                             during a Redis outage.
 *  /health/ready readiness — 503 when a request cannot be served, so the
 *                             instance leaves rotation.
 */
const HEALTH_EXPECTATIONS = [
  { path: '/health', status: 200, json: 'status' },
  { path: '/health/ready', status: 200, json: 'healthy' },
];

export default function () {
  for (const path of PUBLIC_PAGES) {
    const res = http.get(`${BASE_URL}${path}`, { headers: headers() });

    check(res, {
      [`${path} returns 200`]: (r) => r.status === 200,
      [`${path} renders a page`]: (r) => String(r.body).includes('</html>'),
      [`${path} has no PHP error`]: (r) => !String(r.body).includes('Whoops'),
    });
  }

  for (const { path, status, json } of HEALTH_EXPECTATIONS) {
    const res = http.get(`${BASE_URL}${path}`, { headers: headers() });

    check(res, {
      [`${path} returns ${status}`]: (r) => r.status === status,
      [`${path} reports "${json}"`]: (r) => {
        try {
          return JSON.parse(r.body)[json] !== undefined;
        } catch (e) {
          return false;
        }
      },
    });
  }

  // A private route must never be reachable anonymously.
  const admin = http.get(`${BASE_URL}/admin/audit-logs`, {
    headers: headers(),
    redirects: 'manual',
  });

  check(admin, {
    'admin area is not anonymous': (r) => r.status === 302 || r.status === 401,
    'admin redirect does not leak internals': (r) =>
      !String(r.body || '').includes('Whoops'),
  });

  // Private media must never be served as a static file.
  const storage = http.get(`${BASE_URL}/storage/app/private/anything.pdf`, {
    headers: headers(),
    redirects: 'manual',
  });

  check(storage, {
    'private storage is not web accessible': (r) => r.status >= 400,
  });
}
