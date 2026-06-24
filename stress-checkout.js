import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const loginOk = new Rate('login_ok');
const cartCleanupOk = new Rate('cart_cleanup_ok');
const cartAddOk = new Rate('cart_add_ok');
const cartViewOk = new Rate('cart_view_ok');
const checkoutOk = new Rate('checkout_ok');
const orderVerifyOk = new Rate('order_verify_ok');
const flowOk = new Rate('flow_ok');

const checkout201 = new Counter('checkout_201');
const checkout400 = new Counter('checkout_400');
const checkout401 = new Counter('checkout_401');
const checkout429 = new Counter('checkout_429');
const checkout500 = new Counter('checkout_500');

const loginDuration = new Trend('login_duration');
const cartCleanupDuration = new Trend('cart_cleanup_duration');
const cartAddDuration = new Trend('cart_add_duration');
const cartViewDuration = new Trend('cart_view_duration');
const checkoutDuration = new Trend('checkout_duration');
const orderVerifyDuration = new Trend('order_verify_duration');

export const options = {
  scenarios: buildScenarios(),
  setupTimeout: '10m',
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<3000'],
    login_ok: ['rate>0.99'],
    cart_add_ok: ['rate>0.98'],
    checkout_ok: ['rate>0.95'],
    order_verify_ok: ['rate>0.95'],
    flow_ok: ['rate>0.90'],
  },
};

const BASE_URL = (__ENV.BASE_URL || 'http://ecommerce.local/api').replace(/\/$/, '');
const USER_COUNT = Number.parseInt(__ENV.USER_COUNT || '100', 10);
const PRODUCT_START_ID = Number.parseInt(__ENV.PRODUCT_START_ID || '1', 10);
const PRODUCT_POOL_SIZE = Number.parseInt(__ENV.PRODUCT_POOL_SIZE || '500', 10);
const PASSWORD = __ENV.TEST_USER_PASSWORD || 'secret123';
const PREAUTH_IN_SETUP = (__ENV.PREAUTH_IN_SETUP || 'true').toLowerCase() !== 'false';
const CLEAN_CART_FIRST = (__ENV.CLEAN_CART_FIRST || 'false').toLowerCase() === 'true';

const SLEEP_AFTER_LOGIN = toPositiveNumber(__ENV.SLEEP_AFTER_LOGIN, 0.6);
const SLEEP_BEFORE_CART_ADD = toPositiveNumber(__ENV.SLEEP_BEFORE_CART_ADD, 1.2);
const SLEEP_AFTER_CART_ADD = toPositiveNumber(__ENV.SLEEP_AFTER_CART_ADD, 0.6);
const SLEEP_AFTER_CART_VIEW = toPositiveNumber(__ENV.SLEEP_AFTER_CART_VIEW, 1.0);
const SLEEP_AFTER_CHECKOUT = toPositiveNumber(__ENV.SLEEP_AFTER_CHECKOUT, 0.5);

export function setup() {
  const users = [];
  const timestamp = Date.now();

  for (let i = 0; i < USER_COUNT; i += 1) {
    const email = `checkout_stress_${timestamp}_${i}@example.com`;
    const payload = JSON.stringify({
      name: `Checkout Stress User ${i + 1}`,
      email,
      password: PASSWORD,
      password_confirmation: PASSWORD,
    });

    const response = http.post(`${BASE_URL}/register`, payload, {
      headers: jsonHeaders(),
      tags: { step: 'setup_register' },
    });

    const ok = response.status === 201;

    if (!ok) {
      throw new Error(`Failed to register setup user ${email}. Status: ${response.status}, body: ${response.body}`);
    }

    const user = { email, password: PASSWORD };

    if (PREAUTH_IN_SETUP) {
      const loginResponse = http.post(
        `${BASE_URL}/login`,
        JSON.stringify({
          email,
          password: PASSWORD,
        }),
        {
          headers: jsonHeaders(),
          tags: { step: 'setup_login' },
        }
      );
      const token = loginResponse.status === 200 ? extractToken(loginResponse) : null;

      if (!token) {
        throw new Error(`Failed to pre-auth setup user ${email}. Status: ${loginResponse.status}, body: ${loginResponse.body}`);
      }

      user.token = token;
    }

    users.push(user);
  }

  return { users };
}

export default function (data) {
  const user = data.users[(__VU - 1) % data.users.length];
  const productId = selectProductId(__VU, __ITER);
  let flowPassed = true;
  let token = user.token || null;

  if (PREAUTH_IN_SETUP && token) {
    loginDuration.add(0);
    loginOk.add(true);
  } else {
    const loginResponse = http.post(
      `${BASE_URL}/login`,
      JSON.stringify({
        email: user.email,
        password: user.password,
      }),
      {
        headers: jsonHeaders(),
        tags: { step: 'login' },
      }
    );
    loginDuration.add(loginResponse.timings.duration);
    token = loginResponse.status === 200 ? extractToken(loginResponse) : null;
    const loginPassed = !!token;
    loginOk.add(loginPassed);

    if (!loginPassed) {
      flowOk.add(false);
      return;
    }
  }

  sleep(SLEEP_AFTER_LOGIN);

  const authHeaders = authorizedJsonHeaders(token);
  sleep(SLEEP_BEFORE_CART_ADD);

  let cleanupPassed = true;

  if (CLEAN_CART_FIRST) {
    const cleanupStart = Date.now();
    const cartBeforeResponse = http.get(`${BASE_URL}/cart`, {
      headers: authHeaders,
      tags: { step: 'cart_cleanup_list' },
    });
    cleanupPassed = cartBeforeResponse.status === 200;

    if (cleanupPassed) {
      const items = extractCartItems(cartBeforeResponse);

      for (const item of items) {
        const deleteResponse = http.del(`${BASE_URL}/cart/${item.id}`, null, {
          headers: authHeaders,
          tags: { step: 'cart_cleanup_delete' },
          responseCallback: http.expectedStatuses(200, 404),
        });

        if (deleteResponse.status !== 200 && deleteResponse.status !== 404) {
          cleanupPassed = false;
          break;
        }
      }
    }

    cartCleanupDuration.add(Date.now() - cleanupStart);
  } else {
    cartCleanupDuration.add(0);
  }

  cartCleanupOk.add(cleanupPassed);
  flowPassed = flowPassed && cleanupPassed;

  const cartAddResponse = http.post(
    `${BASE_URL}/cart`,
    JSON.stringify({
      product_id: productId,
      quantity: 1,
    }),
    {
      headers: authHeaders,
      tags: { step: 'cart_add' },
    }
  );
  cartAddDuration.add(cartAddResponse.timings.duration);
  const cartAddPassed = cartAddResponse.status === 201;
  cartAddOk.add(cartAddPassed);
  flowPassed = flowPassed && cartAddPassed;
  sleep(SLEEP_AFTER_CART_ADD);

  const cartViewResponse = http.get(`${BASE_URL}/cart`, {
    headers: authHeaders,
    tags: { step: 'cart_view' },
  });
  cartViewDuration.add(cartViewResponse.timings.duration);
  const cartViewPassed = cartViewResponse.status === 200;
  cartViewOk.add(cartViewPassed);
  flowPassed = flowPassed && cartViewPassed;
  sleep(SLEEP_AFTER_CART_VIEW);

  const checkoutResponse = http.post(`${BASE_URL}/checkout`, null, {
    headers: authHeaders,
    tags: { step: 'checkout' },
  });
  checkoutDuration.add(checkoutResponse.timings.duration);
  checkoutOk.add(checkoutResponse.status === 201);
  flowPassed = flowPassed && checkoutResponse.status === 201;

  if (checkoutResponse.status === 201) checkout201.add(1);
  if (checkoutResponse.status === 400) checkout400.add(1);
  if (checkoutResponse.status === 401) checkout401.add(1);
  if (checkoutResponse.status === 429) checkout429.add(1);
  if (checkoutResponse.status === 500) checkout500.add(1);

  let orderVerifyPassed = false;
  const orderId = extractOrderId(checkoutResponse);

  if (checkoutResponse.status === 201 && orderId) {
    sleep(SLEEP_AFTER_CHECKOUT);

    const orderVerifyResponse = http.get(`${BASE_URL}/orders/${orderId}`, {
      headers: authHeaders,
      tags: { step: 'order_verify' },
    });
    orderVerifyDuration.add(orderVerifyResponse.timings.duration);
    orderVerifyPassed = orderVerifyResponse.status === 200;
  }

  orderVerifyOk.add(orderVerifyPassed);
  flowPassed = flowPassed && orderVerifyPassed;

  check(checkoutResponse, {
    'checkout status is 201': (response) => response.status === 201,
  });

  flowOk.add(flowPassed);
}

function selectProductId(vu, iteration) {
  const offset = ((vu - 1) * 13 + iteration * USER_COUNT) % PRODUCT_POOL_SIZE;
  return PRODUCT_START_ID + offset;
}

function extractToken(response) {
  try {
    return response.json('data.access_token');
  } catch (error) {
    return null;
  }
}

function extractOrderId(response) {
  try {
    return response.json('data.id');
  } catch (error) {
    return null;
  }
}

function extractCartItems(response) {
  try {
    const items = response.json('data.items');
    return Array.isArray(items) ? items : [];
  } catch (error) {
    return [];
  }
}

function jsonHeaders() {
  return {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  };
}

function authorizedJsonHeaders(token) {
  return {
    ...jsonHeaders(),
    Authorization: `Bearer ${token}`,
  };
}

function toPositiveNumber(value, fallbackValue) {
  const parsed = Number.parseFloat(value ?? '');
  return Number.isFinite(parsed) && parsed >= 0 ? parsed : fallbackValue;
}

function buildStages() {
  const targetUsers = Number.parseInt(__ENV.TARGET_USERS || '100', 10);
  const ramp1 = __ENV.RAMP_1_DURATION || '20s';
  const ramp2 = __ENV.RAMP_2_DURATION || '20s';
  const ramp3 = __ENV.RAMP_3_DURATION || '20s';
  const hold = __ENV.HOLD_DURATION || '60s';
  const rampDown = __ENV.RAMP_DOWN_DURATION || '20s';

  return [
    { duration: ramp1, target: Math.max(1, Math.min(20, targetUsers)) },
    { duration: ramp2, target: Math.max(1, Math.min(50, targetUsers)) },
    { duration: ramp3, target: targetUsers },
    { duration: hold, target: targetUsers },
    { duration: rampDown, target: 0 },
  ];
}

function buildScenarios() {
  const singleFlowMode = (__ENV.SINGLE_FLOW_MODE || 'true').toLowerCase() !== 'false';
  const targetUsers = Number.parseInt(__ENV.TARGET_USERS || '100', 10);

  if (singleFlowMode) {
    return {
      checkout_flow: {
        executor: 'per-vu-iterations',
        vus: targetUsers,
        iterations: 1,
        maxDuration: __ENV.MAX_DURATION || '10m',
      },
    };
  }

  return {
    checkout_flow: {
      executor: 'ramping-vus',
      stages: buildStages(),
      gracefulRampDown: '30s',
    },
  };
}
