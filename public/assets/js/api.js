const BASE = '/api';

async function apiFetch(path, options = {}) {
  const token = localStorage.getItem('auth_token');
  const headers = { 'Content-Type': 'application/json', ...(options.headers || {}) };
  if (token) headers['Authorization'] = 'Bearer ' + token;
  const res = await fetch(BASE + path, { ...options, headers });
  const data = await res.json();
  if (!data.success) throw new Error(data.error || 'Erreur API');
  return data.data;
}

const api = {
  getSettings:   () => apiFetch('/settings'),
  getCategories: () => apiFetch('/categories'),
  getProducts:   () => apiFetch('/products'),
  getOrder:      (token) => apiFetch('/orders/' + token),
  register:      (body) => apiFetch('/auth/register', { method: 'POST', body: JSON.stringify(body) }),
  login:         (body) => apiFetch('/auth/login',    { method: 'POST', body: JSON.stringify(body) }),
  createOrder:   (body) => apiFetch('/orders',        { method: 'POST', body: JSON.stringify(body) }),
  createIntent:  (body) => apiFetch('/payment/create-intent', { method: 'POST', body: JSON.stringify(body) }),
};
