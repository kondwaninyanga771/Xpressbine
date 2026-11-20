import { Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { initials, kilometres, kwacha, leadPackage, wash } from '../format';
import { useAuth } from '../auth';

export function Logo({ light = false }) {
    return (
        <span className="inline-flex items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-zambezi text-white">
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" aria-hidden="true">
                    <path d="M5 16.5c2.2-4.8 4.2-7 7-7s4.8 2.2 7 7" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" />
                    <path d="M8 10.5c1.2 2.2 2.4 3.3 4 3.3s2.8-1.1 4-3.3" stroke="#E8762D" strokeWidth="1.8" strokeLinecap="round" />
                </svg>
            </span>
            <span className={`font-display text-lg font-semibold tracking-tight ${light ? 'text-white' : 'text-ink'}`}>
                Xpressbine
            </span>
        </span>
    );
}

function Icon({ name }) {
    const common = { fill: 'none', stroke: 'currentColor', strokeWidth: 1.8, strokeLinecap: 'round', strokeLinejoin: 'round' };

    if (name === 'feed') {
        return (
            <svg viewBox="0 0 24 24" className="h-5 w-5" {...common}>
                <path d="M4 8h16M4 12h16M4 16h10" />
            </svg>
        );
    }

    if (name === 'discover') {
        return (
            <svg viewBox="0 0 24 24" className="h-5 w-5" {...common}>
                <circle cx="12" cy="12" r="8" />
                <path d="m14.5 9.5-1.2 3.8-3.8 1.2 1.2-3.8 3.8-1.2z" />
            </svg>
        );
    }

    if (name === 'messages') {
        return (
            <svg viewBox="0 0 24 24" className="h-5 w-5" {...common}>
                <path d="M6 16.5 4 19V7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v6A2.5 2.5 0 0 1 17.5 16H8z" />
            </svg>
        );
    }

    return (
        <svg viewBox="0 0 24 24" className="h-5 w-5" {...common}>
            <circle cx="12" cy="8" r="3" />
            <path d="M6 19c1.2-2.4 3-3.5 6-3.5s4.8 1.1 6 3.5" />
        </svg>
    );
}

const NAV = [
    { href: '/', name: 'feed', label: 'Feed' },
    { href: '/discover', name: 'discover', label: 'Discover' },
    { href: '/map', name: 'discover', label: 'Map' },
    { href: '/messages', name: 'messages', label: 'Messages' },
    { href: '/account', name: 'account', label: 'Account' },
];

function navItems(user) {
    const items = [...NAV];

    if (user) {
        items.splice(3, 0, { href: '/bookings', name: 'feed', label: 'Bookings' });
        items.splice(4, 0, { href: '/jobs', name: 'discover', label: 'Jobs' });
        items.splice(5, 0, { href: '/notifications', name: 'messages', label: 'Alerts' });
    }

    if (user?.role === 'provider') {
        items.push({ href: '/dashboard', name: 'account', label: 'Dashboard' });
    }

    if (user?.role === 'business_client') {
        items.push({ href: '/wallet', name: 'account', label: 'Wallet' });
    }

    if (user?.role === 'admin') {
        items.push({ href: '/ops', name: 'account', label: 'Desk' });
    }

    return items;
}

function itemActive(path, href) {
    if (href === '/') {
        return path === '/';
    }

    return path === href || path.startsWith(`${href}/`);
}

export function Shell({ children, tone = 'sand' }) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const { user } = useAuth();
    const items = navItems(user);
    const mobile = items.filter((item) => ['/', '/discover', '/messages', '/account'].includes(item.href));

    return (
        <div className={`${tone === 'ink' ? 'bg-ink text-white' : 'bg-sand text-ink'} min-h-screen lg:grid lg:grid-cols-[240px_minmax(0,1fr)]`}>
            <aside className="sticky top-0 hidden h-screen flex-col border-r border-cloud bg-white px-4 py-6 lg:flex">
                <Link href="/"><Logo /></Link>
                <nav className="mt-8 flex flex-1 flex-col gap-1">
                    {items.map((item) => (
                        <Link
                            key={item.href}
                            href={item.href}
                            className={`rounded-full px-3 py-2 font-display text-sm font-semibold transition hover:bg-sand ${itemActive(path, item.href) ? 'bg-zambezi text-white hover:bg-zambezi' : 'text-slate hover:text-ink'}`}
                        >
                            {item.label}
                        </Link>
                    ))}
                </nav>
                <div className="text-sm text-slate">
                    {user ? user.name : <Link href="/login" className="btn-primary w-full">Log in</Link>}
                </div>
            </aside>
            <div className="min-w-0">
                <header className="sticky top-0 z-20 hidden border-b border-cloud/80 bg-sand/90 backdrop-blur md:block lg:hidden">
                    <div className="mx-auto flex h-16 max-w-[1200px] items-center gap-4 px-6">
                        <Link href="/"><Logo /></Link>
                        <nav className="flex flex-1 items-center gap-1 overflow-x-auto text-sm font-medium text-slate">
                            {items.map((item) => (
                                <Link key={item.href} href={item.href} className={`shrink-0 rounded-full px-3 py-1.5 transition hover:bg-white ${itemActive(path, item.href) ? 'bg-zambezi text-white' : 'hover:text-ink'}`}>
                                    {item.label}
                                </Link>
                            ))}
                        </nav>
                    </div>
                </header>
                <main className="pb-28 md:pb-10">{children}</main>
            </div>
            <nav className="fixed inset-x-3 bottom-3 z-30 mx-auto flex max-w-md items-center justify-around rounded-full border border-cloud bg-white px-2 py-2 text-slate shadow-pop md:hidden">
                {mobile.map((item) => {
                    const active = itemActive(path, item.href);
                    return (
                        <Link key={item.href} href={item.href} className={`flex min-w-16 flex-col items-center gap-0.5 rounded-full px-3 py-1 font-display text-[11px] font-semibold ${active ? 'text-zambezi' : ''}`}>
                            <Icon name={item.name} />
                            {item.label}
                        </Link>
                    );
                })}
            </nav>
        </div>
    );
}

export function Alert({ children, tone = 'error' }) {
    if (!children) {
        return null;
    }

    const styles = tone === 'error'
        ? 'bg-[#ffdad6] text-[#93000a]'
        : tone === 'stub'
            ? 'border border-dashed border-copper/50 bg-copper/10 text-ink'
            : 'bg-mint/15 text-ink';

    return <div className={`rounded-2xl px-4 py-3 text-sm ${styles}`}>{children}</div>;
}

export function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="mb-1.5 block font-display text-xs font-semibold uppercase tracking-[0.04em] text-slate">{label}</span>
            {children}
            {error && <span className="mt-1 block text-xs text-[#93000a]">{error}</span>}
        </label>
    );
}

export function Stars({ value, count, light = false }) {
    const score = Number(value || 0);
    const rounded = Math.round(score);
    const rated = count === undefined ? score > 0 : Number(count) > 0;

    return (
        <span className={`inline-flex items-center gap-1 font-display text-sm font-semibold ${light ? 'text-white' : 'text-ink'}`}>
            <span className="text-copper">{'★'.repeat(rounded)}{'☆'.repeat(Math.max(0, 5 - rounded))}</span>
            <span>{rated ? score.toFixed(1) : 'New'}</span>
            {Number(count) > 0 ? <span className={`font-sans text-xs font-medium ${light ? 'text-white/70' : 'text-slate'}`}>({count})</span> : null}
        </span>
    );
}

export function TradeMark({ slug }) {
    return (
        <span className={`flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br ${wash(slug)} text-white`}>
            <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round">
                <path d="M12 4v16M5 9h14M7 15h10" />
            </svg>
        </span>
    );
}

export function ProviderCard({ provider, origin }) {
    const pack = leadPackage(provider);
    const category = provider.categories?.[0];
    const km = kilometres(provider, origin);
    const photo = provider.photos?.[0]?.url;
    const title = pack?.title || provider.user?.name || 'Provider';

    return (
        <Link href={`/providers/${provider.id}`} className="card flex flex-col p-4 transition hover:-translate-y-0.5 hover:border-zambezi/40 hover:shadow-pop">
            <div className="flex items-start justify-between gap-2">
                {photo ? (
                    <img src={photo} alt="" className="h-11 w-11 rounded-full object-cover" />
                ) : (
                    <TradeMark slug={category?.slug} />
                )}
                {provider.is_featured ? (
                    <span className="pill bg-copper/10 text-copper">Featured</span>
                ) : (
                    <span className="pill bg-sand text-slate">{provider.user?.area || 'Lusaka'}</span>
                )}
            </div>
            <h3 className="mt-4 font-display text-lg font-semibold leading-6 text-ink">{title}</h3>
            <p className="mt-1 text-sm text-slate">
                {provider.user?.name}
                {category ? ` · ${category.name}` : ''}
                {pack ? ` · ${kwacha(pack.price)}` : provider.hourly_rate ? ` · ${kwacha(provider.hourly_rate)}/hr` : ''}
            </p>
            <div className="mt-3 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate">
                <Stars value={provider.rating_average} count={provider.rating_count} />
                {km != null && <span>{km.toFixed(1)} km</span>}
                {provider.verification_status === 'verified' && <span className="font-display font-bold uppercase tracking-wider text-zambezi">Verified</span>}
            </div>
        </Link>
    );
}

export function ViewToggle({ mode }) {
    const { url } = usePage();
    const query = url.includes('?') ? url.slice(url.indexOf('?')) : '';

    return (
        <div className="inline-flex rounded-full bg-white p-1 shadow-card">
            <Link href={`/discover${query}`} className={`rounded-full px-4 py-1.5 font-display text-xs font-semibold ${mode === 'list' ? 'bg-zambezi text-white' : 'text-slate'}`}>List</Link>
            <Link href={`/map${query}`} className={`rounded-full px-4 py-1.5 font-display text-xs font-semibold ${mode === 'map' ? 'bg-zambezi text-white' : 'text-slate'}`}>Map</Link>
        </div>
    );
}

export function LusakaMap({ providers, origin }) {
    const points = providers
        .map((provider) => ({
            provider,
            lat: Number(provider.user?.latitude),
            lng: Number(provider.user?.longitude),
        }))
        .filter((point) => !Number.isNaN(point.lat) && !Number.isNaN(point.lng));

    const lats = points.map((point) => point.lat);
    const lngs = points.map((point) => point.lng);
    const minLat = Math.min(...lats, origin?.latitude ?? -15.45) - 0.02;
    const maxLat = Math.max(...lats, origin?.latitude ?? -15.4) + 0.02;
    const minLng = Math.min(...lngs, origin?.longitude ?? 28.28) - 0.02;
    const maxLng = Math.max(...lngs, origin?.longitude ?? 28.35) + 0.02;

    function place(lat, lng) {
        const x = ((lng - minLng) / (maxLng - minLng)) * 84 + 8;
        const y = ((maxLat - lat) / (maxLat - minLat)) * 76 + 10;

        return { left: `${x}%`, top: `${y}%` };
    }

    return (
        <div className="relative h-80 overflow-hidden rounded-[28px] bg-ink text-white shadow-pop">
            <div
                className="absolute inset-0 opacity-70"
                style={{
                    backgroundImage: 'radial-gradient(rgba(47,211,154,0.45) 1.1px, transparent 1.1px)',
                    backgroundSize: '22px 22px',
                }}
            />
            <div className="absolute left-4 top-4">
                <p className="font-display text-[11px] font-bold uppercase tracking-[0.14em] text-mint">Lusaka</p>
                <p className="font-display text-lg font-semibold">Providers near {origin?.name || 'you'}</p>
            </div>
            {origin && (
                <span className="absolute h-3 w-3 -translate-x-1/2 -translate-y-1/2 rounded-full bg-mint shadow-[0_0_0_6px_rgba(47,211,154,0.25)]" style={place(Number(origin.latitude), Number(origin.longitude))} title="Search origin" />
            )}
            {points.map(({ provider, lat, lng }) => {
                const pack = leadPackage(provider);
                const price = pack ? kwacha(pack.price) : provider.hourly_rate ? `${kwacha(provider.hourly_rate)}/hr` : '';

                return (
                    <Link
                        key={provider.id}
                        href={`/providers/${provider.id}`}
                        className="absolute -translate-x-1/2 -translate-y-1/2"
                        style={place(lat, lng)}
                    >
                        <span className="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-white px-2.5 py-1 font-display text-xs font-semibold text-ink shadow-pop">
                            <span className={`h-1.5 w-1.5 rounded-full ${provider.is_featured ? 'bg-copper' : 'bg-mint'}`} />
                            {(provider.user?.name || 'Provider').split(' ')[0]}
                            {price ? ` · ${price}` : ''}
                        </span>
                    </Link>
                );
            })}
            {!points.length && (
                <p className="absolute inset-x-6 bottom-6 text-sm text-white/70">No provider coordinates in this result.</p>
            )}
        </div>
    );
}

export function RequireAuth({ children, role }) {
    const { user, ready } = useAuth();

    useEffect(() => {
        if (!ready) {
            return;
        }

        if (!user) {
            sessionStorage.setItem('xpressbine.returnTo', window.location.pathname + window.location.search);
            window.location.assign('/login');
        }
    }, [ready, user]);

    if (!ready || !user) {
        return (
            <Shell>
                <p className="px-4 py-16 text-center text-sm text-slate">Checking your session…</p>
            </Shell>
        );
    }

    if (role && !role.split(',').includes(user.role)) {
        return (
            <Shell>
                <div className="mx-auto max-w-lg px-4 py-16">
                    <Alert>This screen is for the {role.replaceAll(',', ' or ')} role. You are signed in as {user.role.replaceAll('_', ' ')}.</Alert>
                </div>
            </Shell>
        );
    }

    return children;
}

export function PageHead({ title, action }) {
    return (
        <div className="mb-4 flex items-end justify-between gap-3">
            <h2 className="font-display text-2xl font-semibold tracking-tight">{title}</h2>
            {action}
        </div>
    );
}

export function Avatar({ name }) {
    return (
        <span className="flex h-12 w-12 items-center justify-center rounded-full bg-ink font-display text-sm font-semibold text-white">
            {initials(name)}
        </span>
    );
}

export function Empty({ children }) {
    return <div className="card px-5 py-8 text-center text-sm text-slate">{children}</div>;
}
