import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, firstError, listOf, qs } from '../api';
import { useAuth } from '../auth';
import { Alert, Logo, PageHead, ProviderCard, RequireAuth, Shell, ViewToggle } from '../components/ui';
import { areaByName, AREAS, kwacha, matchesQuery, originFor } from '../format';

export default function Discover() {
    return (
        <RequireAuth>
            <DiscoverScreen />
        </RequireAuth>
    );
}

function queryFrom(url) {
    const search = url.includes('?') ? url.slice(url.indexOf('?') + 1) : '';
    const params = new URLSearchParams(search);

    return {
        category: params.get('category') || '',
        near: params.get('near') || '',
        sort: params.get('sort') || 'distance',
        featured: params.get('featured') === '1',
    };
}

function go(current, next) {
    const params = { ...current, ...next };

    if (next.featured === false) {
        params.featured = false;
    }

    const query = qs({
        category: params.category,
        near: params.near,
        sort: params.sort,
        featured: params.featured ? 1 : undefined,
    });

    router.get(`/discover${query}`, {}, { preserveState: true, preserveScroll: true, replace: true });
}

function DiscoverScreen() {
    const { user } = useAuth();
    const { url } = usePage();
    const filters = queryFrom(url);
    const origin = originFor(user, filters.near || user?.area);
    const [categories, setCategories] = useState([]);
    const [providers, setProviders] = useState([]);
    const [text, setText] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);
    const [estimate, setEstimate] = useState(null);
    const [savedEstimates, setSavedEstimates] = useState([]);
    const [problem, setProblem] = useState('');
    const [estimating, setEstimating] = useState(false);

    useEffect(() => {
        api('/categories').then((payload) => setCategories(listOf(payload))).catch(() => {});
        api('/estimates').then((payload) => setSavedEstimates(listOf(payload))).catch(() => {});
    }, []);

    useEffect(() => {
        let active = true;
        setLoading(true);
        setError('');

        const query = qs({
            category: filters.category,
            latitude: origin.latitude,
            longitude: origin.longitude,
            radius_km: 15,
            sort: filters.sort,
            featured: filters.featured ? 1 : undefined,
            per_page: 50,
        });

        api(`/providers${query}`)
            .then((payload) => {
                if (active) {
                    setProviders(listOf(payload));
                }
            })
            .catch((reason) => {
                if (active) {
                    setError(reason.message);
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [filters.category, filters.sort, filters.featured, origin.latitude, origin.longitude]);

    const visible = providers.filter((provider) => matchesQuery(provider, text));
    const curated = categories.find((category) => category.slug === 'plumbing') || categories[0];

    async function askEstimate(event) {
        event.preventDefault();
        setEstimating(true);
        setError('');

        try {
            const payload = await api('/ai/estimate', { method: 'POST', json: { description: problem } });
            setEstimate(payload.data);
            const saved = await api('/estimates');
            setSavedEstimates(listOf(saved));
        } catch (reason) {
            setError(firstError(reason));
        } finally {
            setEstimating(false);
        }
    }

    return (
        <Shell>
            <Head title="Discover" />
            <div className="mx-auto max-w-[1200px] px-4 pt-4 md:px-8">
                <div className="flex items-center justify-between md:hidden">
                    <Logo />
                    <ViewToggle mode="list" />
                </div>
                <div className="mt-4 hidden items-center justify-between md:flex">
                    <PageHead title="Discover providers" />
                    <ViewToggle mode="list" />
                </div>
                <label className="mt-4 block">
                    <span className="sr-only">Filter the providers already loaded</span>
                    <input
                        value={text}
                        onChange={(event) => setText(event.target.value)}
                        placeholder="Filter by trade, provider, area"
                        className="field !h-12 !rounded-full"
                    />
                </label>
                <p className="mt-2 text-xs text-slate">
                    The list is GET /api/providers with a trade, a 15 km origin, and sort. This box only filters that response. The API has no free-text search field.
                </p>
                <div className="mt-4 flex gap-2 overflow-x-auto pb-1">
                    <FilterPill active={!filters.category && !filters.featured} onClick={() => go(filters, { category: '', featured: false })}>All</FilterPill>
                    {categories.map((category) => (
                        <FilterPill key={category.id} active={filters.category === category.slug} onClick={() => go(filters, { category: category.slug, featured: false })}>
                            {category.name}
                        </FilterPill>
                    ))}
                    <FilterPill active={filters.featured} onClick={() => go(filters, { featured: true, category: '' })}>Featured</FilterPill>
                </div>
                <div className="mt-3 flex flex-wrap items-center gap-2">
                    <select className="field !h-10 !w-auto !rounded-full" value={areaByName(filters.near) ? filters.near : origin.name} onChange={(event) => go(filters, { near: event.target.value })}>
                        {AREAS.map((area) => <option key={area.name}>{area.name}</option>)}
                    </select>
                    <select className="field !h-10 !w-auto !rounded-full" value={filters.sort} onChange={(event) => go(filters, { sort: event.target.value })}>
                        <option value="distance">Nearest</option>
                        <option value="rating">Top rated</option>
                        <option value="price">Lowest hourly rate</option>
                    </select>
                </div>

                <form onSubmit={askEstimate} className="card mt-5 p-4">
                    <p className="font-display text-xs font-bold uppercase tracking-[0.08em] text-zambezi">Describe the problem</p>
                    <p className="mt-1 text-sm text-slate">Sends description to POST /api/ai/estimate.</p>
                    <textarea value={problem} onChange={(event) => setProblem(event.target.value)} minLength={8} required placeholder="My kitchen sink is leaking badly under the cupboard" className="field mt-3 !h-24 py-3" />
                    <button className="btn-primary mt-3" disabled={estimating} type="submit">{estimating ? 'Checking…' : 'Suggest a trade'}</button>
                    {estimate && (
                        <div className="mt-4 rounded-2xl bg-ink p-4 text-white">
                            <p className="font-display text-xs font-bold uppercase tracking-[0.08em] text-mint">{estimate.trade_label || estimate.trade}</p>
                            <p className="mt-2 font-display text-2xl font-semibold">{kwacha(estimate.price_min)} – {kwacha(estimate.price_max)}</p>
                            <p className="mt-2 text-sm text-white/75">{estimate.rationale}</p>
                            <p className="mt-1 text-xs text-white/50">Urgency {estimate.urgency} · driver {estimate.driver}</p>
                            {estimate.trade && (
                                <button type="button" className="btn-copper mt-4" onClick={() => go(filters, { category: estimate.trade, featured: false })}>
                                    See {estimate.trade_label || estimate.trade}
                                </button>
                            )}
                        </div>
                    )}
                </form>
                {savedEstimates.length > 0 && (
                    <div className="mt-3 flex gap-2 overflow-x-auto">
                        {savedEstimates.slice(0, 6).map((item) => (
                            <button key={item.id} type="button" className="card shrink-0 px-4 py-3 text-left transition hover:border-zambezi/40" onClick={() => item.trade && go(filters, { category: item.trade, featured: false })}>
                                <span className="block font-display text-sm font-semibold">{item.trade_label || item.trade}</span>
                                <span className="text-xs text-slate">{kwacha(item.price_min)} – {kwacha(item.price_max)}</span>
                            </button>
                        ))}
                    </div>
                )}

                {error && <div className="mt-4"><Alert>{error}</Alert></div>}

                <div className="mt-8">
                    <PageHead
                        title={filters.featured ? 'Featured providers' : `Near ${origin.name}`}
                        action={<Link href={`/map?near=${encodeURIComponent(filters.near || origin.name)}&category=${filters.category}&sort=${filters.sort}`} className="font-display text-sm font-semibold text-zambezi">Map</Link>}
                    />
                    {loading ? (
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                            {[0, 1, 2, 3].map((item) => <div key={item} className="h-44 animate-pulse rounded-2xl bg-white" />)}
                        </div>
                    ) : visible.length ? (
                        <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                            {visible.map((provider) => <ProviderCard key={provider.id} provider={provider} origin={origin} />)}
                        </div>
                    ) : (
                        <Alert>No verified providers match this trade and area.</Alert>
                    )}
                </div>

                {curated && (
                    <section className="mt-10">
                        <PageHead title="Curated collections" action={<span className="text-sm text-slate">{categories.length} trades</span>} />
                        <div className="overflow-hidden rounded-[28px] bg-ink p-6 text-white">
                            <p className="font-display text-[11px] font-bold uppercase tracking-[0.14em] text-copper">Curated collection</p>
                            <h3 className="mt-3 max-w-xs font-display text-3xl font-semibold leading-tight">{curated.name} in Lusaka</h3>
                            <p className="mt-2 max-w-md text-sm text-white/70">{curated.description}. Typical range {kwacha(curated.price_min)} – {kwacha(curated.price_max)}.</p>
                            <button type="button" className="btn-primary mt-5" onClick={() => go(filters, { category: curated.slug, featured: false })}>
                                Explore {curated.name}
                            </button>
                        </div>
                        <div className="mt-4 grid grid-cols-2 gap-4">
                            <Link href={`/map?near=${encodeURIComponent(origin.name)}`} className="card p-4">
                                <span className="flex h-11 w-11 items-center justify-center rounded-full bg-sand text-zambezi">
                                    <svg viewBox="0 0 24 24" className="h-5 w-5" fill="none" stroke="currentColor" strokeWidth="1.8"><path d="M12 21s6-5.2 6-10a6 6 0 1 0-12 0c0 4.8 6 10 6 10z" /><circle cx="12" cy="11" r="1.6" /></svg>
                                </span>
                                <p className="mt-4 font-display text-lg font-semibold">On the map</p>
                                <p className="text-sm text-slate">{visible.length} in this search</p>
                            </Link>
                            <button type="button" onClick={() => go(filters, { featured: true, category: '' })} className="card p-4 text-left">
                                <span className="flex h-11 w-11 items-center justify-center rounded-full bg-copper/10 text-copper">★</span>
                                <p className="mt-4 font-display text-lg font-semibold">Featured</p>
                                <p className="text-sm text-slate">is_featured on the provider</p>
                            </button>
                        </div>
                    </section>
                )}
            </div>
        </Shell>
    );
}

function FilterPill({ active, onClick, children }) {
    return (
        <button type="button" onClick={onClick} className={`pill shrink-0 border ${active ? 'border-zambezi bg-zambezi text-white' : 'border-cloud bg-white text-ink'}`}>
            {children}
        </button>
    );
}
