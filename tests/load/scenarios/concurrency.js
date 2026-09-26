/**
 * Concurrency ladder — 100 → 250 → 500 → 750 → 1000 concurrent users.
 *
 *   BASE_URL=https://staging.sekolah.example \
 *     k6 run tests/load/scenarios/concurrency.js
 *
 * Why a ladder rather than a single 1000-VU run:
 *
 * A run that reports "failed at 1000" has located a boundary but not the knee.
 * Without knowing the stage where latency degrades, there is no way to decide
 * whether the answer is more workers, more memory, a read replica, a CDN, or a
 * query fix. Run and RECORD each stage separately.
 *
 * Recommended sequence, each as its own invocation so a failure is
 * attributable:
 *
 *   k6 run -e STAGE=100  tests/load/scenarios/concurrency.js
 *   k6 run -e STAGE=250  tests/load/scenarios/concurrency.js
 *   k6 run -e STAGE=500  tests/load/scenarios/concurrency.js
 *   k6 run -e STAGE=750  tests/load/scenarios/concurrency.js
 *   k6 run -e STAGE=1000 tests/load/scenarios/concurrency.js
 *   k6 run                 tests/load/scenarios/concurrency.js   # full ladder
 */

import http from 'k6/http';
import { check } from 'k6';
import exec from 'k6/execution';
import {
  BASE_URL,
  headers,
  publicP95,
  record,
  checkSecurityHeaders,
  humanThink,
  pick,
  THRESHOLDS,
} from '../common.js';
import { PAGES, DETAIL_SLUGS } from '../data.js';

/** Hold each stage long enough for a slow leak or a slow saturation to show. */
const STAGES = [
  { target: 100, hold: '5m' },
  { target: 250, hold: '5m' },
  { target: 500, hold: '5m' },
  { target: 750, hold: '5m' },
  { target: 1000, hold: '10m' },
];

const requestedStage = __ENV.STAGE ? parseInt(__ENV.STAGE, 10) : null;

const ladder = requestedStage
  ? STAGES.filter((s) => s.target === requestedStage)
  : STAGES;

if (ladder.length === 0) {
  throw new Error(`Unknown STAGE=${__ENV.STAGE}. Valid values: 100, 250, 500, 750, 1000.`);
}

const rampStages = [];
ladder.forEach((stage, index) => {
  rampStages.push({ duration: '2m', target: stage.target });
  rampStages.push({ duration: stage.hold, target: stage.target });
  if (index < ladder.length - 1) {
    rampStages.push({ duration: '1m', target: 0 });
  }
});
rampStages.push({ duration: '1m', target: 0 });

export const options = {
  scenarios: {
    concurrency_ladder: {
      executor: 'ramping-vus',
      startVUs: 0,
      gracefulRampDown: '30s',
      stages: rampStages,
    },
  },
  thresholds: {
    'public_latency': [
      `p(95)<${THRESHOLDS.publicP95}`,
      // Tag per stage so a single run reports where the knee is, not just
      // that the target was missed.
      'p(95)<500{stage:100}',
      'p(95)<500{stage:250}',
    ],
    'app_error_rate': [`rate<${THRESHOLDS.errorRate}`],
    http_req_failed: [`rate<${THRESHOLDS.errorRate}`],
    checks: ['rate>0.99'],
    // Guard against the classic load-test mistake: a run where the error
    // rate looks fine because everything 500'd and the threshold only
    // measured the success ratio.
    dropped_iterations: ['count<100'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'p(99.9)', 'max'],
  discardResponseBodies: false,
};

export default function () {
  // Tag each iteration with the stage it belongs to, so the summary can be
  // read per-stage.
  const stage = String(exec.scenario.progress ? currentStageTarget() : '');
  const params = { headers: { ...headers(), 'X-Load-Stage': stage } };

  const useDetail = DETAIL_SLUGS.length > 0 && Math.random() < 0.2;
  const url = useDetail ? `/berita/${pick(DETAIL_SLUGS)}` : pick(PAGES);

  const res = http.get(`${BASE_URL}${url}`, params);

  if (record(res, publicP95)) {
    checkSecurityHeaders(res);
    check(res, {
      'page rendered': (r) => String(r.body).includes('</html>'),
    });
  }

  humanThink();
}

/**
 * Approximate the current ladder target by elapsed time, so the stage tag is
 * correct even when STAGE pins the run to a single stage.
 */
function currentStageTarget() {
  if (requestedStage) {
    return requestedStage;
  }

  const elapsed = exec.scenario.iterationInTest === undefined
    ? 0
    : exec.scenario.iterationInTest;

  // Fall back to the midpoint of the ladder when k6 cannot report progress.
  return elapsed > 0 ? ladder[Math.min(ladder.length - 1, 0)].target : ladder[0].target;
}

export function handleSummary(data) {
  const latency = data.metrics.public_latency?.values || {};
  const errors = data.metrics.app_error_rate?.values || {};

  const lines = [
    '',
    '  concurrency ladder — RECORD EACH STAGE, DO NOT JUST THE TOTAL',
    '  ' + '-'.repeat(64),
    `  p50   ${(latency.med || 0).toFixed(1)} ms`,
    `  p95   ${(latency['p(95)'] || 0).toFixed(1)} ms   (target < ${THRESHOLDS.publicP95})`,
    `  p99   ${(latency['p(99)'] || 0).toFixed(1)} ms`,
    `  p99.9 ${(latency['p(99.9)'] || 0).toFixed(1)} ms`,
    `  max   ${(latency.max || 0).toFixed(1)} ms`,
    `  error ${((errors.rate || 0) * 100).toFixed(2)}%  (target < ${THRESHOLDS.errorRate * 100}%)`,
    '  ' + '-'.repeat(64),
    '  A failure here is a finding. Report it as one; do not relax the',
    '  threshold to obtain a pass.',
    '',
  ];

  return { stdout: lines.join('\n') };
}
