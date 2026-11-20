import { Link, usePage } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import { api, listOf } from '../api';
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
    const common = {
        viewBox: '0 0 24 24',
        className: 'h-5 w-5',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 1.8,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': true,
    };

    if (name === 'feed') {
        return <svg {...common}><path d="M4 8h16M4 12h16M4 16h10" /></svg>;
    }

    if (name === 'discover') {
        return (
            <svg {...common}>
                <circle cx="12" cy="12" r="8" />
                <path d="m14.5 9.5-1.2 3.8-3.8 1.2 1.2-3.8 3.8-1.2z" />
            </svg>
        );
    }

    if (name === 'map') {
        return (
            <svg {...common}>
                <path d="M9 18 3 20V6l6-2 6 2 6-2v14l-6 2-6-2z" />
                <path d="M9 4v14M15 6v14" />
            </svg>
        );
    }

    if (name === 'messages') {
        return <svg {...common}><path d="M6 16.5 4 19V7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v6A2.5 2.5 0 0 1 17.5 16H8z" /></svg>;
    }

    if (name === 'bookings') {
        return (
            <svg {...common}>
                <rect x="4" y="5" width="16" height="15" rx="2" />
                <path d="M8 3v4M16 3v4M4 10h16" />
            </svg>
        );
    }

    if (name === 'jobs') {
        return (
            <svg {...common}>
                <path d="M8 7V5.5A1.5 1.5 0 0 1 9.5 4h5A1.5 1.5 0 0 1 16 5.5V7" />
                <rect x="4" y="7" width="16" height="13" rx="2" />
            </svg>
        );
    }

    if (name === 'dashboard') {
        return (
            <svg {...common}>
                <rect x="4" y="4" width="7" height="7" rx="1.5" />
                <rect x="13" y="4" width="7" height="4" rx="1.5" />
                <rect x="13" y="10" width="7" height="10" rx="1.5" />
                <rect x="4" y="13" width="7" height="7" rx="1.5" />
            </svg>
        );
    }

    if (name === 'wallet') {
        return (
            <svg {...common}>
                <rect x="3" y="6" width="18" height="13" rx="2" />
                <path d="M3 10h18" />
                <circle cx="16" cy="14.5" r="1" />
            </svg>
        );
    }

    if (name === 'ops') {
        return (
            <svg {...common}>
                <path d="M12 4 4 8v5c0 4.2 3.2 6.8 8 8 4.8-1.2 8-3.8 8-8V8l-8-4z" />
                <path d="m9 12 2 2 4-4" />
            </svg>
        );
    }

    return (
        <svg {...common}>
            <circle cx="12" cy="8" r="3" />
            <path d="M6 19c1.2-2.4 3-3.5 6-3.5s4.8 1.1 6 3.5" />
        </svg>
    );
}

function roleDestination(user) {
    if (user?.role === 'provider') {
        return { href: '/dashboard', name: 'dashboard', label: 'Dashboard' };
    }

    if (user?.role === 'business_client') {
        return { href: '/wallet', name: 'wallet', label: 'Wallet' };
    }

    if (user?.role === 'admin') {
        return { href: '/ops', name: 'ops', label: 'Ops' };
    }

    return null;
}

function contentSections(user) {
    const items = [
        { href: '/', name: 'feed', label: 'Feed' },
        { href: '/discover', name: 'discover', label: 'Discover' },
        { href: '/map', name: 'map', label: 'Map' },
    ];

    if (!user) {
        return items;
    }

    items.push(
        { href: '/bookings', name: 'bookings', label: 'Bookings' },
        { href: '/jobs', name: 'jobs', label: 'Jobs' },
    );

    const role = roleDestination(user);

    if (role) {
        items.push(role);
    }

    return items;
}

function menuLinks(user) {
    const links = [
        { href: '/account', label: 'Account' },
        { href: '/bookings', label: 'Bookings' },
        { href: '/notifications', label: 'Alerts' },
    ];
    const role = roleDestination(user);

    if (role) {
        links.push({ href: role.href, label: role.label });
    }

    return links;
}

function itemActive(path, href) {
    if (href === '/') {
        return path === '/';
    }

    return path === href || path.startsWith(`${href}/`);
}

function linkClass(active) {
    return `shrink-0 rounded-full px-3 py-1.5 font-display text-sm font-semibold transition ${active ? 'bg-zambezi text-white' : 'text-slate hover:bg-sand hover:text-ink'}`;
}

function MessagesButton({ active, unread }) {
    const label = unread > 0 ? `Messages, ${unread} unread` : 'Messages';

    return (
        <Link
            href="/messages"
            aria-label={label}
            aria-current={active ? 'page' : undefined}
            className={`relative flex h-10 w-10 items-center justify-center rounded-full transition ${active ? 'bg-zambezi text-white' : 'text-ink hover:bg-sand'}`}
        >
            <Icon name="messages" />
            <span
                data-slot="unread-badge"
                className={`absolute -right-0.5 -top-0.5 min-w-4 rounded-full bg-copper px-1 text-center font-display text-[10px] font-bold leading-4 text-white ${unread > 0 ? '' : 'hidden'}`}
            >
                {unread > 9 ? '9+' : unread || ''}
            </span>
        </Link>
    );
}

function AccountMenu({ user, path, logout }) {
    const [open, setOpen] = useState(false);
    const rootRef = useRef(null);
    const buttonRef = useRef(null);
    const menuRef = useRef(null);
    const menuId = useId();
    const links = menuLinks(user);
    const onAccount = itemActive(path, '/account');

    useEffect(() => {
        if (!open) {
            return undefined;
        }

        function onPointer(event) {
            if (!rootRef.current?.contains(event.target)) {
                setOpen(false);
            }
        }

        function onKey(event) {
            if (event.key === 'Escape') {
                setOpen(false);
                buttonRef.current?.focus();
            }
        }

        document.addEventListener('pointerdown', onPointer);
        document.addEventListener('keydown', onKey);

        return () => {
            document.removeEventListener('pointerdown', onPointer);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    useEffect(() => {
        if (!open) {
            return;
        }

        menuRef.current?.querySelector('[role="menuitem"]')?.focus();
    }, [open]);

    function onMenuKeyDown(event) {
        const nodes = [...(menuRef.current?.querySelectorAll('[role="menuitem"]') || [])];
        const index = nodes.indexOf(document.activeElement);

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            nodes[(index + 1) % nodes.length]?.focus();
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            nodes[(index - 1 + nodes.length) % nodes.length]?.focus();
        } else if (event.key === 'Home') {
            event.preventDefault();
            nodes[0]?.focus();
        } else if (event.key === 'End') {
            event.preventDefault();
            nodes[nodes.length - 1]?.focus();
        } else if (event.key === ' ' && document.activeElement?.getAttribute('role') === 'menuitem') {
            event.preventDefault();
            document.activeElement.click();
        } else if (event.key === 'Tab') {
            setOpen(false);
        }
    }

    return (
        <div ref={rootRef} className="relative">
            <button
                ref={buttonRef}
                type="button"
                className={`flex min-w-0 items-center gap-2 rounded-full py-1 pl-1 pr-2 transition sm:pr-3 ${onAccount || open ? 'bg-zambezi text-white' : 'text-ink hover:bg-sand'}`}
                aria-haspopup="menu"
                aria-expanded={open}
                aria-controls={menuId}
                onClick={() => setOpen((value) => !value)}
                onKeyDown={(event) => {
                    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        setOpen(true);
                    }
                }}
            >
                <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full font-display text-xs font-semibold ${onAccount || open ? 'bg-white text-zambezi' : 'bg-ink text-white'}`}>
                    {initials(user.name)}
                </span>
                <span className="min-w-0 truncate font-display text-sm font-semibold">{user.name}</span>
                <svg viewBox="0 0 20 20" className="h-4 w-4 shrink-0" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
                    <path d="m5 8 5 5 5-5" strokeLinecap="round" strokeLinejoin="round" />
                </svg>
            </button>
            {open && (
                <div
                    ref={menuRef}
                    id={menuId}
                    role="menu"
                    aria-label="Account"
                    onKeyDown={onMenuKeyDown}
                    className="absolute right-0 top-[calc(100%+0.4rem)] z-40 w-56 rounded-2xl border border-cloud bg-white p-1.5 text-ink shadow-pop"
                >
                    {links.map((link) => {
                        const active = itemActive(path, link.href);

                        return (
                            <Link
                                key={link.href}
                                href={link.href}
                                role="menuitem"
                                tabIndex={-1}
                                aria-current={active ? 'page' : undefined}
                                className={`block rounded-xl px-3 py-2 font-display text-sm font-semibold focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zambezi ${active ? 'bg-zambezi text-white' : 'hover:bg-sand'}`}
                                onClick={() => setOpen(false)}
                            >
                                {link.label}
                            </Link>
                        );
                    })}
                    <div className="my-1 border-t border-cloud" />
                    <button
                        type="button"
                        role="menuitem"
                        tabIndex={-1}
                        className="block w-full rounded-xl px-3 py-2 text-left font-display text-sm font-semibold hover:bg-sand focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zambezi"
                        onClick={() => {
                            setOpen(false);
                            logout();
                        }}
                    >
                        Log out
                    </button>
                </div>
            )}
        </div>
    );
}

export function Shell({ children, tone = 'sand' }) {
    const { url } = usePage();
    const path = url.split('?')[0];
    const { user, ready, logout } = useAuth();
    const sections = contentSections(user);
    const messagesActive = itemActive(path, '/messages');
    const [unread, setUnread] = useState(0);

    useEffect(() => {
        if (!user) {
            setUnread(0);
            return undefined;
        }

        let active = true;

        function load() {
            api('/conversations')
                .then((payload) => {
                    if (!active) {
                        return;
                    }

                    const total = listOf(payload).reduce((sum, row) => sum + Number(row.unread || 0), 0);
                    setUnread(total);
                })
                .catch(() => {
                    if (active) {
                        setUnread(0);
                    }
                });
        }

        load();
        window.addEventListener('xpressbine:conversations-changed', load);

        return () => {
            active = false;
            window.removeEventListener('xpressbine:conversations-changed', load);
        };
    }, [user, path]);

    return (
        <div className={tone === 'ink' ? 'min-h-screen bg-ink text-white' : 'min-h-screen bg-sand text-ink'}>
            <header className="sticky top-0 z-20 border-b border-cloud/80 bg-white/95 text-ink backdrop-blur">
                <div className="mx-auto flex h-16 w-full max-w-[1400px] items-center gap-3 px-3 md:gap-6 md:px-8">
                    <Link href="/" className="shrink-0"><Logo /></Link>
                    <nav className="hidden min-w-0 flex-1 items-center gap-1 overflow-x-auto md:flex" aria-label="Primary">
                        {sections.map((item) => (
                            <Link key={item.href} href={item.href} aria-current={itemActive(path, item.href) ? 'page' : undefined} className={linkClass(itemActive(path, item.href))}>
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                    <div className="ml-auto flex min-w-0 items-center gap-1 sm:gap-2">
                        {ready && user && (
                            <>
                                <MessagesButton active={messagesActive} unread={unread} />
                                <AccountMenu user={user} path={path} logout={logout} />
                            </>
                        )}
                        {ready && !user && (
                            <>
                                <Link href="/login" className="rounded-full px-3 py-1.5 font-display text-sm font-semibold text-ink hover:bg-sand">Log in</Link>
                                <Link href="/register" className="btn-primary !px-3 !py-2 sm:!px-4">Create account</Link>
                            </>
                        )}
                    </div>
                </div>
            </header>
            <main className="pb-28 md:pb-10">{children}</main>
            <nav aria-label="Primary" className="fixed inset-x-2 bottom-2 z-30 md:hidden">
                <div className="mx-auto flex max-w-lg items-stretch justify-between gap-0.5 overflow-x-auto rounded-full border border-cloud bg-white px-1.5 py-1.5 text-slate shadow-pop">
                    {sections.map((item) => {
                        const active = itemActive(path, item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={`flex min-w-[3.6rem] flex-1 flex-col items-center gap-0.5 rounded-full px-1.5 py-1 font-display text-[10px] font-semibold leading-none ${active ? 'bg-zambezi text-white' : ''}`}
                            >
                                <Icon name={item.name} />
                                {item.label}
                            </Link>
                        );
                    })}
                </div>
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
