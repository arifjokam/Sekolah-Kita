/**
 * Public read traffic — the dominant real-world pattern.
 *
 *   BASE_URL=https://staging.sekolah.example \
 *     k6 run tests/load/public-read.js
 *
 * The staged concurrency ladder is in scenarios/concurrency.js. Run the stages
 * individually first: a run that only reports "failed at 1000" has not located
 * the knee, and therefore has not told you what to fix.
 */

import http from 'k6/http';
import {
  BASE_URL,
  headers,
  publicP95,
  record,
  checkSecurityHeaders,
  humanThink,
  pick,
  standardThresholds,
} from './common.js';
import { PAGES, DETAIL_SLUGS } from './data.js';

export const options = {
  scenarios: {
    public_read: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: '2m', target: 100 },
        { duration: '5m', target: 100 },
        { duration: '3m', target: 250 },
        { duration: '5m', target: 250 },
        { duration: '3m', target: 500 },
        { duration: '5m', target: 500 },
        { duration: '3m', target: 750 },
        { duration: '5m', target: 750 },
        { duration: '3m', target: 1000 },
        { duration: '10m', target: 1000 },
        { duration: '2m', target: 0 },
      ],
    },
  },
  thresholds: standardThresholds(),
  // A 10-minute hold at 1000 VUs with think time is not a throughput test;
  // it is a soak. Do not shorten the hold to "get results faster" — sustained
  // degradation (memory growth, connection leaks) is the finding that matters.
  gracefulStop: '30s',
};

export default function () {
  // 80% browse/list, 20% detail, mirroring real navigation.
  if (DETAIL_SLUGS.length === 0 || Math.random() < 0.8) {
    const res = http.get(`${BASE_URL}${pick(PAGES)}`, { headers: headers() });
    if (record(res, publicP95)) {
      checkSecurityHeaders(res);
    }
    humanThink();
    return;
  }

  const res = http.get(`${BASE_URL}/berita/${pick(DETAIL_SLUGS)}`, {
    headers: headers(),
  });
  if (record(res, publicP95)) {
    checkSecurityHeaders(res);
  }
  humanThink();
}

export function handleSummary(data) {
  return {
    stdout: `\n  public read: p95=${data.metrics.public_latency?.values?.['p(95)']?.toFixed?.(1) ?? 'n/a'}ms`
          + `  p99=${data.metrics.public_latency?.values?.['p(99)']?.toFixed?.(1) ?? 'n/a'}ms`
          + `  errors=${(data.metrics.app_error_rate?.values?.rate * 100 || 0).toFixed(2)}%\n`,
  };
}
