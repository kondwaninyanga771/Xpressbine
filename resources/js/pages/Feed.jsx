import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, listOf, qs } from '../api';
import { useAuth } from '../auth';
import { Logo, Shell, Stars } from '../components/ui';
import { canBook, kilometres, kwacha, leadPackage, originFor, wash } from '../format';

export default function Feed() {
    const { user, ready } = useAuth();

    return (
        <>
            <Head title="Feed" />
            {!ready && (
                <Shell>
                    <p className="px-4 py-16 text-center text-sm text-slate">Loading Xpressbine…</p>
                </Shell>
            )}
            {ready && !user && <GuestFeed />}
            {ready && user && <ProviderFeed user={user} />}
        </>
    );
}

function GuestFeed() {
    const [categories, setCategories] = useState([]);

    useEffect(() => {
        api('/categories').then((payload) => setCategories(listOf(payload))).catch(() => setCategories([]));
    }, []);

    return (
        <Shell tone="ink">
            <section className="mx-auto flex min-h-[calc(100dvh-7rem)] w-full max-w-md flex-col justify-end bg-gradient-to-b from-[#163044] via-ink to-[#0B6B4F] px-5 pb-8 pt-16 md:my-8 md:min-h-[760px] md:rounded-[32px]">
                <Logo light />
                <div className="mt-8 flex flex-wrap gap-2">
                    <span className="pill bg-white/10 text-white"><span className="h-1.5 w-1.5 rounded-full bg-mint" /> Lusaka</span>
                    <span className="pill bg-white/10 text-white">In person</span>
                    <span className="pill bg-mint/20 text-mint">Escrow</span>
                </div>
                <h1 className="mt-5 font-display text-4xl font-semibold uppercase leading-[0.95] tracking-tight">
                    Local trades, held in escrow
                </h1>
                <p className="mt-4 max-w-sm text-sm leading-6 text-white/80">
                    Book plumbers, electricians, cleaners and other trades around Lusaka. Pay with Airtel Money, MTN MoMo or Zamtel Kwacha. The money stays in escrow until you confirm the job.
                </p>
                <div className="mt-4 flex flex-wrap gap-2">
                    {categories.map((category) => (
                        <span key={category.id} className="pill border border-white/15 text-white/80">{category.name}</span>
                    ))}
                </div>
                <div className="mt-8 grid grid-cols-2 gap-3">
                    <Link href="/login" className="btn-primary">Log in</Link>
                    <Link href="/register" className="btn-ink">Create account</Link>
                </div>
            </section>
        </Shell>
    );
}

function ProviderFeed({ user }) {
    const origin = originFor(user, user.area);
    const [providers, setProviders] = useState([]);
    const [error, setError] = useState('');
    const [index, setIndex] = useState(0);

    useEffect(() => {
        const query = qs({
            latitude: origin.latitude,
            longitude: origin.longitude,
            radius_km: 15,
            sort: 'rating',
            per_page: 50,
        });

        api(`/providers${query}`)
            .then((payload) => setProviders(listOf(payload)))
            .catch((reason) => setError(reason.message));
    }, [origin.latitude, origin.longitude]);

    const provider = providers[index];

    return (
        <Shell>
            <div className="mx-auto grid max-w-[1200px] items-start gap-6 px-0 lg:grid-cols-[420px_minmax(0,1fr)] lg:px-8 lg:py-8">
                <div className="relative lg:sticky lg:top-6">
                    {!provider && !error && <div className="h-[calc(100dvh-7rem)] animate-pulse bg-ink md:h-[760px] md:rounded-[32px]" />}
                    {error && <p className="px-5 py-10 text-sm text-[#93000a]">{error}</p>}
                    {provider && (
                        <FeedCard
                            provider={provider}
                            origin={origin}
                            user={user}
                            position={`${index + 1} / ${providers.length}`}
                            onPrev={() => setIndex((value) => Math.max(0, value - 1))}
                            onNext={() => setIndex((value) => Math.min(providers.length - 1, value + 1))}
                        />
                    )}
                </div>
                <aside className="hidden lg:block">
                    <p className="font-display text-xs font-bold uppercase tracking-[0.14em] text-zambezi">Near {origin.name}</p>
                    <h2 className="mt-1 font-display text-3xl font-semibold">Trades holding a package</h2>
                    <div className="mt-5 grid grid-cols-2 gap-3 xl:grid-cols-2">
                        {providers.map((item, itemIndex) => {
                            const pack = leadPackage(item);
                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => setIndex(itemIndex)}
                                    className={`card flex w-full items-center justify-between px-4 py-3 text-left ${itemIndex === index ? 'border-zambezi' : ''}`}
                                >
                                    <span>
                                        <span className="block font-display font-semibold">{pack?.title || item.user?.name}</span>
                                        <span className="text-sm text-slate">{item.user?.name} · {item.user?.area}</span>
                                    </span>
                                    <span className="font-display font-semibold">{pack ? kwacha(pack.price) : ''}</span>
                                </button>
                            );
                        })}
                    </div>
                </aside>
            </div>
        </Shell>
    );
}

function FeedCard({ provider, origin, user, position, onPrev, onNext }) {
    const pack = leadPackage(provider);
    const category = provider.categories?.[0];
    const photo = provider.photos?.[0]?.url;
    const km = kilometres(provider, origin);
    const title = (pack?.title || provider.user?.name || 'Provider').toUpperCase();
    const [copied, setCopied] = useState(false);

    async function share() {
        const url = `${window.location.origin}/providers/${provider.id}`;

        try {
            await navigator.clipboard.writeText(url);
            setCopied(true);
            setTimeout(() => setCopied(false), 1600);
        } catch {
            setCopied(false);
        }
    }

    return (
        <article className={`relative flex h-[calc(100dvh-7rem)] flex-col justify-end overflow-hidden px-5 pb-6 pt-16 text-white md:h-[760px] md:rounded-[32px] ${photo ? '' : `bg-gradient-to-b ${wash(category?.slug)}`}`} style={photo ? { backgroundImage: `linear-gradient(to top, rgba(14,27,44,0.94) 12%, rgba(14,27,44,0.28) 58%), url(${photo})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined}>
            <div className="absolute left-5 right-20 top-5 flex items-center justify-between text-xs text-white/70">
                <span className="font-display font-semibold uppercase tracking-[0.14em]">Near {origin.name}</span>
                <span>{position}</span>
            </div>
            <div className="absolute right-4 top-1/3 flex flex-col gap-4">
                {provider.user?.id && (
                    <CircleButton label="Message" onClick={() => router.visit(`/messages/${provider.user.id}`)}>
                        <path d="M6 16.5 4 19V7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v6A2.5 2.5 0 0 1 17.5 16H8z" />
                    </CircleButton>
                )}
                <CircleButton label={copied ? 'Copied' : 'Share'} onClick={share}>
                    <circle cx="6" cy="12" r="1.6" />
                    <circle cx="16" cy="7" r="1.6" />
                    <circle cx="16" cy="17" r="1.6" />
                    <path d="m7.5 11.2 7-3.4M7.5 12.8l7 3.4" />
                </CircleButton>
            </div>
            <div className="flex flex-wrap gap-2">
                <span className="pill bg-black/30 text-white"><span className="h-1.5 w-1.5 rounded-full bg-mint" /> {provider.user?.area || 'Lusaka'}</span>
                {category && <span className="pill bg-black/30 text-white">{category.name}</span>}
                {provider.verification_status === 'verified' && <span className="pill bg-mint/20 text-mint">Verified</span>}
                {provider.is_featured && <span className="pill bg-copper text-white">Featured</span>}
            </div>
            <h1 className="mt-4 max-w-[16ch] font-display text-4xl font-semibold uppercase leading-[0.95] tracking-tight">{title}</h1>
            <p className="mt-3 max-w-sm text-sm leading-6 text-white/80">{pack?.description || provider.bio}</p>
            <p className="mt-3 font-display text-sm font-semibold">
                {pack ? kwacha(pack.price) : provider.hourly_rate ? `${kwacha(provider.hourly_rate)}/hr` : 'Price on request'}
                {pack?.duration_minutes ? ` · ${pack.duration_minutes} min` : ''}
                {km != null ? ` · ${km.toFixed(1)} km` : ''}
            </p>
            <div className="mt-2"><Stars light value={provider.rating_average} count={provider.rating_count} /></div>
            <div className="mt-5 grid grid-cols-2 gap-3">
                {canBook(user) && pack ? (
                    <Link href={`/providers/${provider.id}?package=${pack.id}#book`} className="btn-primary">
                        <Bolt /> Quick book
                    </Link>
                ) : (
                    <Link href={`/providers/${provider.id}`} className="btn-primary">View profile</Link>
                )}
                <Link href={`/providers/${provider.id}`} className="btn-ink">View profile</Link>
            </div>
            <div className="mt-4 flex justify-between text-xs text-white/60">
                <button type="button" onClick={onPrev} className="font-display font-semibold uppercase tracking-wider">Previous</button>
                <button type="button" onClick={onNext} className="font-display font-semibold uppercase tracking-wider">Next</button>
            </div>
        </article>
    );
}

function Bolt() {
    return (
        <svg viewBox="0 0 24 24" className="h-4 w-4" fill="currentColor" aria-hidden="true">
            <path d="M13 2 4 14h7l-1 8 9-12h-7l1-8z" />
        </svg>
    );
}

function CircleButton({ label, onClick, children }) {
    return (
        <button type="button" onClick={onClick} className="flex flex-col items-center gap-1 text-[10px] font-display font-semibold uppercase tracking-wider text-white">
            <span className="flex h-12 w-12 items-center justify-center rounded-full border border-white/20 bg-ink/50 backdrop-blur">
                <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
                    {children}
                </svg>
            </span>
            {label}
        </button>
    );
}
