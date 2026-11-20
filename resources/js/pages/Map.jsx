import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, listOf, qs } from '../api';
import { useAuth } from '../auth';
import { Alert, Logo, LusakaMap, PageHead, ProviderCard, RequireAuth, Shell, ViewToggle } from '../components/ui';
import { AREAS, originFor } from '../format';

export default function Map() {
    return (
        <RequireAuth>
            <MapScreen />
        </RequireAuth>
    );
}

function MapScreen() {
    const { user } = useAuth();
    const { url } = usePage();
    const params = new URLSearchParams(url.includes('?') ? url.slice(url.indexOf('?') + 1) : '');
    const category = params.get('category') || '';
    const near = params.get('near') || '';
    const sort = params.get('sort') || 'distance';
    const origin = originFor(user, near || user?.area);
    const [providers, setProviders] = useState([]);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const query = qs({
            category,
            latitude: origin.latitude,
            longitude: origin.longitude,
            radius_km: 15,
            sort,
            per_page: 50,
        });

        api(`/providers${query}`)
            .then((payload) => setProviders(listOf(payload)))
            .catch((reason) => setError(reason.message))
            .finally(() => setLoading(false));
    }, [category, sort, origin.latitude, origin.longitude]);

    const query = qs({ category, near: origin.name, sort });

    return (
        <Shell>
            <Head title="Map" />
            <div className="mx-auto max-w-6xl px-4 pt-4 md:px-8">
                <div className="flex items-center justify-between">
                    <Logo />
                    <ViewToggle mode="map" />
                </div>
                <div className="mt-5 flex items-end justify-between gap-3">
                    <div>
                        <p className="font-display text-xs font-bold uppercase tracking-[0.12em] text-zambezi">{providers.length} nearby</p>
                        <h1 className="font-display text-3xl font-semibold">Providers near {origin.name}</h1>
                    </div>
                    <select
                        className="field !h-10 !w-auto !rounded-full"
                        value={AREAS.some((area) => area.name === origin.name) ? origin.name : AREAS[0].name}
                        onChange={(event) => {
                            const next = qs({ category, near: event.target.value, sort });
                            window.location.assign(`/map${next}`);
                        }}
                    >
                        {AREAS.map((area) => <option key={area.name}>{area.name}</option>)}
                    </select>
                </div>
                <p className="mt-2 text-xs text-slate">
                    Pins use latitude and longitude from each provider. On SQLite the API does not return distance_km, so kilometres on the cards are calculated from those coordinates.
                </p>
                {error && <div className="mt-4"><Alert>{error}</Alert></div>}
                <div className="mt-4">
                    {loading ? <div className="h-80 animate-pulse rounded-[28px] bg-ink" /> : <LusakaMap providers={providers} origin={origin} />}
                </div>
                <div className="mt-4">
                    <Link href={`/discover${query}`} className="btn-ghost">Browse the list</Link>
                </div>
                <div className="mt-8">
                    <PageHead title="In this search" />
                    <div className="grid grid-cols-2 gap-4 lg:grid-cols-3">
                        {providers.map((provider) => <ProviderCard key={provider.id} provider={provider} origin={origin} />)}
                    </div>
                </div>
            </div>
        </Shell>
    );
}
