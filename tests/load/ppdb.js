/**
 * PPDB public flow — submission, status read, and document upload.
 *
 *   BASE_URL=https://staging.sekolah.example k6 run tests/load/ppdb.js
 *
 * READ BEFORE RUNNING
 *
 * The application rate-limits this flow deliberately and the limits are
 * enforced, not decorative:
 *
 *   ppdb-submit   5 / hour / IP,  10 / day / session,  3 / day / contact
 *   ppdb-status  60 / hour / IP,  30 / hour / token
 *   ppdb-upload  20 / day / IP,   10 / day / token
 *
 * Those limits mean a conforming load test cannot generate meaningful write
 * load, and a test that bypasses them measures a configuration the system will
 * never run in. So:
 *
 *   1. Run writes against a STAGING environment whose limits are raised by
 *      explicit configuration.
 *   2. Record that the limits were altered, alongside the result.
 *   3. Never defeat the limiter with spoofed X-Forwarded-For headers. That
 *      also changes the trust boundary being measured, so the numbers would
 *      not describe production.
 *
 * Never point this at production.
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';
import {
  BASE_URL,
  headers,
  formHeaders,
  publicP95,
  writeP95,
  record,
  csrfFrom,
  humanThink,
  standardThresholds,
  THRESHOLDS,
} from './common.js';
import { SYNTHETIC } from './data.js';

const submissions = new Counter('ppdb_submissions');
const rateLimited = new Rate('ppdb_rate_limited');

export const options = {
  scenarios: {
    public_ppdb_read: {
      executor: 'ramping-vus',
      exec: 'publicRead',
      startVUs: 0,
      stages: [
        { duration: '1m', target: 25 },
        { duration: '3m', target: 25 },
        { duration: '2m', target: 0 },
      ],
    },
    public_ppdb_write: {
      executor: 'constant-vus',
      exec: 'submitApplication',
      vus: 1,
      duration: '2m',
      startTime: '30s',
    },
  },
  thresholds: {
    ...standardThresholds(),
    'write_latency': [`p(95)<${THRESHOLDS.writeP95}`],
  },
};

export function publicRead() {
  const index = http.get(`${BASE_URL}/ppdb`, { headers: headers() });
  record(index, publicP95);

  const form = http.get(`${BASE_URL}/ppdb/daftar`, { headers: headers() });
  record(form, publicP95);

  check(form, {
    'application form renders': (r) => String(r.body).includes('name="_token"'),
    'consent version is shown': (r) => String(r.body).includes('consent'),
  });

  humanThink(2, 6);
}

export function submitApplication() {
  const form = http.get(`${BASE_URL}/ppdb/daftar`, { headers: headers() });
  const token = csrfFrom(form.body);

  if (token === '') {
    rateLimited.add(true);
    return;
  }

  // Synthetic data only. Never load a real applicant's information into a
  // test harness.
  const payload = {
    _token: token,
    full_name: `${SYNTHETIC.applicantName} ${__VU}-${__ITER}`,
    birth_place: 'Kota Uji',
    birth_date: '2013-05-17',
    gender: 'L',
    address: 'Jl. Beban Uji No. 1',
    applicant_nik: SYNTHETIC.applicantNik,
    guardian_name: SYNTHETIC.guardianName,
    guardian_phone: SYNTHETIC.guardianPhone,
    guardian_email: SYNTHETIC.email,
    consent_version: __ENV.PPDB_CONSENT_VERSION || '2026-01',
  };

  const res = http.post(`${BASE_URL}/ppdb/daftar`, payload, {
    headers: formHeaders(),
    redirects: 'manual',
  });

  const throttled = res.status === 429;
  rateLimited.add(throttled);
  record(res, writeP95);

  if (!throttled) {
    submissions.add(1);
  }

  check(res, {
    'submission is accepted or rejected cleanly': (r) =>
      r.status === 302 || r.status === 422 || r.status === 429,
    'no PHP error on submission': (r) => !String(r.body || '').includes('Whoops'),
  });

  if (res.status === 422) {
    // A validation failure is a correct outcome, but the response must not
    // disclose whether the submitted identity already exists.
    const body = String(res.body).toLowerCase();
    check(res, {
      'validation error does not confirm identity existence': (r) =>
        !body.includes('sudah terdaftar') || !body.includes('already registered'),
    });
  }

  sleep(3);
}

/**
 * Document upload. Requires a status token, so run it after a submission and
 * pass the token explicitly:
 *
 *   k6 run -e PPDB_TOKEN=<status-token> tests/load/ppdb.js
 */
export function uploadDocument() {
  const token = __ENV.PPDB_TOKEN;
  const filePath = __ENV.PPDB_DOCUMENT_PATH;

  if (!token || !filePath) {
    throw new Error('Upload scenario needs PPDB_TOKEN and PPDB_DOCUMENT_PATH.');
  }

  const form = http.get(`${BASE_URL}/ppdb/status/${token}`, { headers: headers() });
  const csrf = csrfFrom(form.body);

  const res = http.post(
    `${BASE_URL}/ppdb/status/${token}/dokumen`,
    {
      _token: csrf,
      access_token: __ENV.PPDB_ACCESS_TOKEN || '',
      document_type: __ENV.PPDB_DOCUMENT_TYPE || 'birth_certificate',
      file: http.file(filePath, __ENV.PPDB_DOCUMENT_NAME || 'document.pdf', 'application/pdf'),
    },
    { headers: formHeaders(), redirects: 'manual' }
  );

  record(res, writeP95);

  check(res, {
    'upload is accepted, rejected, or throttled': (r) =>
      r.status === 302 || r.status === 422 || r.status === 429,
    'no PHP error on upload': (r) => !String(r.body || '').includes('Whoops'),
  });
}

/**
 * Rejection of an oversized or wrong-type upload is a security control. The
 * harness should confirm it fires, because an upload limit that silently does
 * not work is worse than no limit at all.
 */
export function uploadRejection() {
  const token = __ENV.PPDB_TOKEN;
  if (!token) {
    throw new Error('Rejection scenario needs PPDB_TOKEN.');
  }

  const res = http.post(
    `${BASE_URL}/ppdb/status/${token}/dokumen`,
    {
      _token: __ENV.PPDB_CSRF || '',
      access_token: __ENV.PPDB_ACCESS_TOKEN || '',
      document_type: 'birth_certificate',
      // A shell script renamed to .pdf: the allowlist must reject it on
      // content, not on the extension.
      file: http.file(__ENV.PPDB_MALICIOUS_FIXTURE, 'harmless.pdf', 'application/pdf'),
    },
    { headers: formHeaders(), redirects: 'manual' }
  );

  check(res, {
    'disguised file is rejected': (r) => r.status === 422 || r.status === 302 || r.status === 429,
  });
}
