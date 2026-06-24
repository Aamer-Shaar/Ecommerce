import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';

const status200 = new Counter('status_200');
const status400 = new Counter('status_400');
const status401 = new Counter('status_401');
const status403 = new Counter('status_403');
const status404 = new Counter('status_404');
const status408 = new Counter('status_408');
const status429 = new Counter('status_429');
const status500 = new Counter('status_500');
const status502 = new Counter('status_502');
const status503 = new Counter('status_503');
const status504 = new Counter('status_504');

const status2xx = new Counter('status_2xx');
const status3xx = new Counter('status_3xx');
const status4xx = new Counter('status_4xx');
const status5xx = new Counter('status_5xx');
const statusOther = new Counter('status_other');
const requestOk = new Rate('request_ok');
const rate4xx = new Rate('rate_4xx');
const rate5xx = new Rate('rate_5xx');

export const options = {
  stages: [
    { duration: '20s', target: 20 },
    { duration: '20s', target: 50 },
    { duration: '20s', target: 100 },
    { duration: '60s', target: 100 },
    { duration: '20s', target: 0 },
  ],
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<1000'],
    rate_4xx: ['rate<0.01'],
    rate_5xx: ['rate<0.01'],
    request_ok: ['rate>0.99'],
  },
};

const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8000/api';
const SLEEP_SECONDS = Number.parseFloat(__ENV.SLEEP_SECONDS || '3');

export default function () {
  const res = http.get(`${BASE_URL}/products`, {
    headers: {
      Accept: 'application/json',
    },
  });

  if (res.status === 200) status200.add(1);
  if (res.status === 400) status400.add(1);
  if (res.status === 401) status401.add(1);
  if (res.status === 403) status403.add(1);
  if (res.status === 404) status404.add(1);
  if (res.status === 408) status408.add(1);
  if (res.status === 429) status429.add(1);
  if (res.status === 500) status500.add(1);
  if (res.status === 502) status502.add(1);
  if (res.status === 503) status503.add(1);
  if (res.status === 504) status504.add(1);

  if (res.status >= 200 && res.status < 300) {
    status2xx.add(1);
  } else if (res.status >= 300 && res.status < 400) {
    status3xx.add(1);
  } else if (res.status >= 400 && res.status < 500) {
    status4xx.add(1);
  } else if (res.status >= 500 && res.status < 600) {
    status5xx.add(1);
  } else {
    statusOther.add(1);
  }

  requestOk.add(res.status === 200);
  rate4xx.add(res.status >= 400 && res.status < 500);
  rate5xx.add(res.status >= 500 && res.status < 600);

  check(res, {
    'status is 200': (r) => r.status === 200,
  });

  sleep(Number.isFinite(SLEEP_SECONDS) && SLEEP_SECONDS >= 0 ? SLEEP_SECONDS : 1);
}
