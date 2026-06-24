import http from 'k6/http';
import { check, sleep } from 'k6';

const vus = Number.parseInt(__ENV.VUS || '10', 10);
const duration = __ENV.DURATION || '30s';
const sleepSeconds = Number.parseFloat(__ENV.SLEEP_SECONDS || '1');

export const options = {
  vus,
  duration,
  thresholds: {
    http_req_failed: ['rate<0.05'],
  },
};

const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000/api').replace(/\/$/, '');
const endpoint = (__ENV.ENDPOINT || '/products').startsWith('/')
  ? (__ENV.ENDPOINT || '/products')
  : `/${__ENV.ENDPOINT}`;
const method = (__ENV.METHOD || 'GET').toUpperCase();
const token = __ENV.TOKEN || '';
const rawBody = __ENV.BODY || '';

export default function () {
  const headers = {
    Accept: 'application/json',
  };

  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let body = null;

  if (rawBody) {
    headers['Content-Type'] = 'application/json';
    body = rawBody;
  }

  const response = http.request(method, `${baseUrl}${endpoint}`, body, { headers });

  check(response, {
    'status is below 500': (res) => res.status < 500,
  });

  sleep(Number.isFinite(sleepSeconds) && sleepSeconds >= 0 ? sleepSeconds : 1);
}
