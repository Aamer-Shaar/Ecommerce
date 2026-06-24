import http from 'k6/http';
import { check } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const registerOk = new Rate('register_ok');
const tokenIssuedOk = new Rate('token_issued_ok');
const registerDuration = new Trend('register_duration');

const status201 = new Counter('status_201');
const status422 = new Counter('status_422');
const status429 = new Counter('status_429');
const status500 = new Counter('status_500');

export const options = {
  scenarios: {
    register_flow: {
      executor: 'per-vu-iterations',
      vus: Number.parseInt(__ENV.TARGET_USERS || '100', 10),
      iterations: 1,
      maxDuration: __ENV.MAX_DURATION || '10m',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<5000'],
    register_ok: ['rate>0.95'],
    token_issued_ok: ['rate>0.95'],
  },
};

const BASE_URL = (__ENV.BASE_URL || 'http://ecommerce.local/api').replace(/\/$/, '');

export function setup() {
  return {
    runId: `${Date.now()}_${Math.floor(Math.random() * 100000)}`,
  };
}

export default function (data) {
  const email = `register_stress_${data.runId}_vu${__VU}_iter${__ITER}@example.com`;
  const payload = JSON.stringify({
    name: `Register Stress User ${__VU}`,
    email,
    password: 'secret123',
    password_confirmation: 'secret123',
  });

  const response = http.post(`${BASE_URL}/register`, payload, {
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    tags: { step: 'register' },
  });

  registerDuration.add(response.timings.duration);

  if (response.status === 201) status201.add(1);
  if (response.status === 422) status422.add(1);
  if (response.status === 429) status429.add(1);
  if (response.status === 500) status500.add(1);

  const ok = response.status === 201;
  registerOk.add(ok);

  let hasToken = false;
  if (ok) {
    try {
      hasToken = Boolean(response.json('data.access_token'));
    } catch (error) {
      hasToken = false;
    }
  }

  tokenIssuedOk.add(hasToken);

  check(response, {
    'register status is 201': (res) => res.status === 201,
    'register token exists': () => hasToken,
  });
}
