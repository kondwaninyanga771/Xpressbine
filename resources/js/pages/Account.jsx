import { Head, Link } from '@inertiajs/react';
import { RequireAuth, Shell } from '../components/ui';
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
        { href: '/messages', label: 'Messages', show: true },
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
                <button type="button" onClick={logout} className="btn-ghost mt-6 w-full">Log out</button>
            </div>
        </Shell>
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
