const API_BASE = process.env.EXPO_PUBLIC_API_URL || 'http://127.0.0.1:8000';

let authToken = null;

export function setAuthToken(token) {
  authToken = token;
}

export async function api(path, options = {}) {
  const headers = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    ...(options.headers || {}),
  };
  if (authToken) {
    headers.Authorization = `Bearer ${authToken}`;
  }

  const res = await fetch(`${API_BASE}${path}`, { ...options, headers });
  const body = await res.json().catch(() => ({}));
  if (!res.ok) {
    throw new Error(body.message || `Request failed (${res.status})`);
  }
  return body;
}

export { API_BASE };
