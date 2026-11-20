import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, firstError, listOf } from '../api';
import { Alert, Field, RequireAuth, Shell } from '../components/ui';
import { useAuth } from '../auth';
import { humanize } from '../format';

export default function Account() {
    return (
        <RequireAuth>
            <AccountScreen />
        </RequireAuth>
    );
}

function AccountScreen() {
    const { user, logout } = useAuth();
    const links = [
        { href: '/bookings', label: 'Bookings', show: true },
        { href: '/messages', label: 'Open messages', show: true },
        { href: '/notifications', label: 'Alerts', show: true },
        { href: '/discover', label: 'Discover providers', show: user.role !== 'admin' },
        { href: '/dashboard', label: 'Provider dashboard', show: user.role === 'provider' },
        { href: '/wallet', label: 'Business wallet', show: user.role === 'business_client' },
        { href: '/ops', label: 'Verification and disputes', show: user.role === 'admin' },
    ];

    return (
        <Shell>
            <Head title="Account" />
            <div className="mx-auto max-w-lg px-4 py-6 md:px-8">
                <p className="font-display text-xs font-bold uppercase tracking-[0.12em] text-zambezi">{humanize(user.role)}</p>
                <h1 className="mt-1 font-display text-4xl font-semibold">{user.name}</h1>
                <p className="mt-2 text-sm text-slate">Your profile lives here. Conversations open from the message icon beside your name. Bookings, alerts, and log out are in that menu.</p>
                <dl className="card mt-5 space-y-2 p-5 text-sm">
                    <Line label="Email" value={user.email} />
                    <Line label="Phone" value={user.phone} />
                    <Line label="Area" value={user.area} />
                    <Line label="Mobile money" value={user.mobile_money_provider ? humanize(user.mobile_money_provider) : ''} />
                    <Line label="Number" value={user.mobile_money_number} />
                </dl>
                <div className="mt-4 space-y-2">
                    {links.filter((link) => link.show).map((link) => (
                        <Link key={link.href} href={link.href} className="card flex items-center justify-between px-4 py-4 font-display font-semibold">
                            {link.label}
                            <span className="text-copper">→</span>
                        </Link>
                    ))}
                    {user.role === 'admin' && (
                        <a href="/admin" className="card flex items-center justify-between px-4 py-4 font-display font-semibold">
                            Filament panel
                            <span className="text-copper">→</span>
                        </a>
                    )}
                </div>
                <AppealPanel user={user} />
                <button type="button" onClick={logout} className="btn-ghost mt-6 w-full">Log out</button>
            </div>
        </Shell>
    );
}

function AppealPanel({ user }) {
    const [actions, setActions] = useState([]);
    const [message, setMessage] = useState('');
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        if (user.role !== 'provider') {
            return;
        }

        api('/moderation-actions').then((payload) => setActions(listOf(payload))).catch(() => {});
    }, [user.role]);

    if (user.role !== 'provider') {
        return null;
    }

    async function submit(event) {
        event.preventDefault();
        setError('');
        setNotice('');
        const action = actions[0];

        if (!action) {
            setError('There is no moderation action to appeal.');
            return;
        }

        try {
            await api('/provider/appeals', { method: 'POST', json: { moderation_action_id: action.id, message } });
            setNotice('Appeal saved.');
            setMessage('');
        } catch (reason) {
            setError(firstError(reason));
        }
    }

    return (
        <form onSubmit={submit} className="card mt-4 space-y-3 p-5">
            <h2 className="font-display text-lg font-semibold">Appeal a moderation action</h2>
            {error && <Alert>{error}</Alert>}
            {notice && <Alert tone="ok">{notice}</Alert>}
            {actions[0] ? <p className="text-sm text-slate">Latest action: {actions[0].action}. {actions[0].reason}</p> : <p className="text-sm text-slate">No moderation actions on this account.</p>}
            <Field label="Message">
                <textarea className="field !h-24 py-3" minLength={10} value={message} onChange={(event) => setMessage(event.target.value)} />
            </Field>
            <button className="btn-ghost" type="submit" disabled={!actions[0]}>Submit appeal</button>
        </form>
    );
}

function Line({ label, value }) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex justify-between gap-4">
            <dt className="text-slate">{label}</dt>
            <dd className="text-right font-medium">{value}</dd>
        </div>
    );
}
