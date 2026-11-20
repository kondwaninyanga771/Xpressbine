const TOKEN_KEY = 'xpressbine.token';

export function token() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(value) {
    if (value) {
        localStorage.setItem(TOKEN_KEY, value);
    } else {
        localStorage.removeItem(TOKEN_KEY);
    }
}

export function qs(params) {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
            search.set(key, String(value));
        }
    });

    const text = search.toString();

    return text ? `?${text}` : '';
}

export async function api(path, { method = 'GET', json, form } = {}) {
    const headers = { Accept: 'application/json' };
    const current = token();

    if (current) {
        headers.Authorization = `Bearer ${current}`;
    }

    let body;

    if (form) {
        body = form;
    } else if (json !== undefined) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(json);
    }

    const response = await fetch(`/api${path}`, { method, headers, body });
    const payload = await response.json().catch(() => ({}));

    if (response.status === 401) {
        setToken(null);
        window.dispatchEvent(new Event('xpressbine:unauthorized'));
    }

    if (!response.ok) {
        const error = new Error(payload.message || `Request failed (${response.status})`);
        error.status = response.status;
        error.errors = payload.errors || {};
        throw error;
    }

    return payload;
}

export function listOf(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    if (Array.isArray(payload?.data)) {
        return payload.data;
    }

    return [];
}

export function recordOf(payload) {
    if (payload && typeof payload === 'object' && 'data' in payload && !Array.isArray(payload.data)) {
        return payload.data;
    }

    return payload;
}

export function firstError(error) {
    const errors = error?.errors || {};

    for (const value of Object.values(errors)) {
        if (Array.isArray(value) && value[0]) {
            return value[0];
        }

        if (typeof value === 'string') {
            return value;
        }
    }

    return error?.message || 'Something went wrong.';
}

export function fieldError(error, name) {
    const value = error?.errors?.[name];

    if (Array.isArray(value)) {
        return value[0];
    }

    return value || null;
}
