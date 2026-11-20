import { createContext, useContext, useEffect, useState } from 'react';
import { router } from '@inertiajs/react';
import { api, recordOf, setToken, token } from './api';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
    const [user, setUser] = useState(null);
    const [ready, setReady] = useState(false);

    useEffect(() => {
        let active = true;

        async function load() {
            if (!token()) {
                if (active) {
                    setUser(null);
                    setReady(true);
                }

                return;
            }

            try {
                const payload = await api('/me');

                if (active) {
                    setUser(recordOf(payload));
                }
            } catch {
                setToken(null);

                if (active) {
                    setUser(null);
                }
            } finally {
                if (active) {
                    setReady(true);
                }
            }
        }

        load();

        const onUnauthorized = () => setUser(null);
        window.addEventListener('xpressbine:unauthorized', onUnauthorized);

        return () => {
            active = false;
            window.removeEventListener('xpressbine:unauthorized', onUnauthorized);
        };
    }, []);

    async function adopt(payload) {
        setToken(payload.token);
        const me = recordOf(await api('/me'));
        setUser(me);

        return me;
    }

    async function login(email, password) {
        return adopt(await api('/login', { method: 'POST', json: { email, password } }));
    }

    async function register(json) {
        return adopt(await api('/register', { method: 'POST', json }));
    }

    async function logout() {
        try {
            await api('/logout', { method: 'POST' });
        } catch {
            // The token is dropped either way.
        }

        setToken(null);
        setUser(null);
        router.visit('/');
    }

    return (
        <AuthContext.Provider value={{ user, ready, login, register, logout, setUser }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    return useContext(AuthContext);
}

export function homeFor(user) {
    if (user?.role === 'provider') {
        return '/dashboard';
    }

    if (user?.role === 'admin') {
        return '/ops';
    }

    if (user?.role === 'business_client') {
        return '/wallet';
    }

    return '/';
}

export function resumeAfterAuth(user) {
    const next = sessionStorage.getItem('xpressbine.returnTo');
    sessionStorage.removeItem('xpressbine.returnTo');
    router.visit(next || homeFor(user));
}
