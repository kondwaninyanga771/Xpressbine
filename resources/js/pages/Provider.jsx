import { Head, Link, router } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { api, fieldError, firstError, listOf, recordOf } from '../api';
import { useAuth } from '../auth';
import { Alert, Field, RequireAuth, Shell, Stars } from '../components/ui';
import { AREAS, areaByName, canBook, defaultSchedule, kilometres, kwacha, originFor, toApiDate, wash, when } from '../format';

export default function Provider({ providerId }) {
    return (
        <RequireAuth>
            <ProviderScreen providerId={providerId} />
        </RequireAuth>
    );
}

function ProviderScreen({ providerId }) {
    const { user } = useAuth();
    const origin = originFor(user, user?.area);
    const [provider, setProvider] = useState(null);
    const [ratings, setRatings] = useState([]);
    const [error, setError] = useState('');
    const preset = useMemo(() => new URLSearchParams(window.location.search).get('package'), []);

    useEffect(() => {
        let active = true;

        api(`/providers/${providerId}`)
            .then((payload) => {
                if (active) {
                    setProvider(recordOf(payload));
                }
            })
            .catch((reason) => {
                if (active) {
                    setError(reason.message);
                }
            });

        api(`/providers/${providerId}/ratings`)
            .then((payload) => {
                if (active) {
                    setRatings(listOf(payload));
                }
            })
            .catch(() => {});

        return () => {
            active = false;
        };
    }, [providerId]);

    if (error) {
        return <Shell><div className="mx-auto max-w-3xl px-4 py-8"><Alert>{error}</Alert></div></Shell>;
    }

    if (!provider) {
        return <Shell><p className="px-4 py-16 text-center text-sm text-slate">Loading provider…</p></Shell>;
    }

    const category = provider.categories?.[0];
    const km = kilometres(provider, origin);
    const photo = provider.photos?.[0]?.url;

    return (
        <Shell>
            <Head title={provider.user?.name || 'Provider'} />
            <div className="mx-auto max-w-3xl px-4 py-4 md:px-8">
                <Link href="/discover" className="font-display text-sm font-semibold text-zambezi">Back to discover</Link>
                <section className={`mt-3 overflow-hidden rounded-[28px] p-6 text-white ${photo ? '' : `bg-gradient-to-br ${wash(category?.slug)}`}`} style={photo ? { backgroundImage: `linear-gradient(to top, rgba(14,27,44,0.88), rgba(14,27,44,0.45)), url(${photo})`, backgroundSize: 'cover' } : undefined}>
                    <div className="flex flex-wrap gap-2">
                        <span className="pill bg-white/10">{provider.user?.area || 'Lusaka'}</span>
                        {category && <span className="pill bg-white/10">{category.name}</span>}
                        {provider.verification_status === 'verified' && <span className="pill bg-mint/20 text-mint">Verified</span>}
                        {provider.is_featured && <span className="pill bg-copper">Featured</span>}
                    </div>
                    <h1 className="mt-4 font-display text-4xl font-semibold">{provider.user?.name}</h1>
                    <p className="mt-2 max-w-xl text-sm leading-6 text-white/80">{provider.bio}</p>
                    <div className="mt-4 flex flex-wrap gap-4 text-sm">
                        <Stars light value={provider.rating_average} count={provider.rating_count} />
                        {km != null && <span>{km.toFixed(1)} km from {origin.name}</span>}
                        {provider.years_experience != null && <span>{provider.years_experience} years</span>}
                        {provider.hourly_rate ? <span>{kwacha(provider.hourly_rate)}/hr</span> : null}
                    </div>
                    {provider.user?.id && (
                        <Link href={`/messages/${provider.user.id}`} className="btn-ink mt-5">Message</Link>
                    )}
                </section>

                <section className="mt-6">
                    <h2 className="font-display text-2xl font-semibold">Packages</h2>
                    <div className="mt-3 space-y-3">
                        {(provider.packages || []).map((item) => (
                            <article key={item.id} className="card flex flex-wrap items-center justify-between gap-3 p-4">
                                <div>
                                    <h3 className="font-display text-lg font-semibold">{item.title}</h3>
                                    <p className="mt-1 text-sm text-slate">{item.description}</p>
                                    <p className="mt-2 font-display text-sm font-semibold">{kwacha(item.price)} · {item.duration_minutes} min</p>
                                </div>
                                {canBook(user) && (
                                    <button
                                        type="button"
                                        className="btn-primary"
                                        onClick={() => {
                                            window.dispatchEvent(new CustomEvent('xpressbine:package', { detail: item.id }));
                                            document.getElementById('book')?.scrollIntoView({ behavior: 'smooth' });
                                        }}
                                    >
                                        Book
                                    </button>
                                )}
                            </article>
                        ))}
                        {!provider.packages?.length && <Alert>This provider has no active packages.</Alert>}
                    </div>
                </section>

                {provider.photos?.length > 0 && (
                    <section className="mt-6">
                        <h2 className="font-display text-2xl font-semibold">Past work</h2>
                        <div className="mt-3 grid grid-cols-2 gap-3">
                            {provider.photos.map((item) => (
                                <figure key={item.id} className="overflow-hidden rounded-2xl bg-white">
                                    <img src={item.url} alt={item.caption || ''} className="h-40 w-full object-cover" />
                                    {item.caption && <figcaption className="px-3 py-2 text-xs text-slate">{item.caption}</figcaption>}
                                </figure>
                            ))}
                        </div>
                    </section>
                )}

                <section className="mt-6">
                    <h2 className="font-display text-2xl font-semibold">Reviews</h2>
                    <div className="mt-3 space-y-3">
                        {ratings.length ? ratings.map((rating) => (
                            <article key={rating.id} className="card p-4">
                                <div className="flex items-center justify-between">
                                    <p className="font-display font-semibold">{rating.rater?.name || 'Customer'}</p>
                                    <Stars value={rating.stars} count={1} />
                                </div>
                                {rating.comment && <p className="mt-2 text-sm text-slate">{rating.comment}</p>}
                                <p className="mt-2 text-xs text-slate">{when(rating.created_at)}</p>
                            </article>
                        )) : <Alert tone="ok">No reviews yet.</Alert>}
                    </div>
                </section>

                {canBook(user) && (
                    <BookForm provider={provider} preset={preset} user={user} />
                )}
            </div>
        </Shell>
    );
}

function BookForm({ provider, preset, user }) {
    const packages = provider.packages || [];
    const [packageId, setPackageId] = useState(preset || String(packages[0]?.id || ''));
    const [scheduledAt, setScheduledAt] = useState(defaultSchedule());
    const [address, setAddress] = useState('');
    const [area, setArea] = useState(user?.area || 'Chilenje');
    const [notes, setNotes] = useState('');
    const [clientAbsent, setClientAbsent] = useState(false);
    const [contactName, setContactName] = useState('');
    const [contactPhone, setContactPhone] = useState('');
    const [siteId, setSiteId] = useState('');
    const [sites, setSites] = useState([]);
    const [error, setError] = useState(null);
    const [pending, setPending] = useState(false);

    useEffect(() => {
        if (user.role !== 'business_client') {
            return;
        }

        api('/business').then((payload) => setSites(payload.data?.sites || [])).catch(() => {});
    }, [user.role]);

    useEffect(() => {
        function onPick(event) {
            setPackageId(String(event.detail));
        }

        window.addEventListener('xpressbine:package', onPick);

        return () => window.removeEventListener('xpressbine:package', onPick);
    }, []);

    async function submit(event) {
        event.preventDefault();
        setPending(true);
        setError(null);
        const picked = areaByName(area);

        try {
            const payload = await api('/bookings', {
                method: 'POST',
                json: {
                    package_id: Number(packageId),
                    scheduled_at: toApiDate(scheduledAt),
                    address,
                    area,
                    latitude: picked?.latitude,
                    longitude: picked?.longitude,
                    notes: notes || null,
                    client_absent: clientAbsent,
                    on_site_contact_name: clientAbsent ? contactName || null : null,
                    on_site_contact_phone: clientAbsent ? contactPhone || null : null,
                    business_site_id: siteId ? Number(siteId) : null,
                },
            });
            router.visit(`/bookings/${recordOf(payload).id}`);
        } catch (reason) {
            setError(reason);
            setPending(false);
        }
    }

    return (
        <form id="book" onSubmit={submit} className="card mt-8 space-y-4 p-5">
            <h2 className="font-display text-2xl font-semibold">Book a package</h2>
            <p className="text-sm text-slate">Creates a booking with POST /api/bookings. Payment happens on the next screen, into escrow.</p>
            {error && <Alert>{firstError(error)}</Alert>}
            <Field label="Package" error={fieldError(error, 'package_id')}>
                <select id="package_id" className="field" value={packageId} onChange={(event) => setPackageId(event.target.value)} required>
                    {packages.map((item) => (
                        <option key={item.id} value={item.id}>{item.title} · {kwacha(item.price)}</option>
                    ))}
                </select>
            </Field>
            <Field label="When" error={fieldError(error, 'scheduled_at')}>
                <input type="datetime-local" className="field" value={scheduledAt} onChange={(event) => setScheduledAt(event.target.value)} required />
            </Field>
            <Field label="Address" error={fieldError(error, 'address')}>
                <input className="field" value={address} onChange={(event) => setAddress(event.target.value)} required placeholder="House 12, Chilenje South" />
            </Field>
            <Field label="Area" error={fieldError(error, 'area')}>
                <input className="field" list="lusaka-areas" value={area} onChange={(event) => setArea(event.target.value)} />
                <datalist id="lusaka-areas">
                    {AREAS.map((item) => <option key={item.name} value={item.name} />)}
                </datalist>
            </Field>
            <Field label="Notes" error={fieldError(error, 'notes')}>
                <textarea className="field !h-24 py-3" value={notes} onChange={(event) => setNotes(event.target.value)} placeholder="Kitchen sink is leaking" />
            </Field>
            {user.role === 'business_client' && sites.length > 0 && (
                <Field label="Business site" error={fieldError(error, 'business_site_id')}>
                    <select className="field" value={siteId} onChange={(event) => setSiteId(event.target.value)}>
                        <option value="">No site</option>
                        {sites.map((site) => <option key={site.id} value={site.id}>{site.name}</option>)}
                    </select>
                </Field>
            )}
            <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={clientAbsent} onChange={(event) => setClientAbsent(event.target.checked)} className="h-5 w-5 accent-zambezi" />
                I will not be there. Leave access with someone on site.
            </label>
            {clientAbsent && (
                <div className="grid gap-3 md:grid-cols-2">
                    <Field label="On-site contact" error={fieldError(error, 'on_site_contact_name')}>
                        <input className="field" value={contactName} onChange={(event) => setContactName(event.target.value)} />
                    </Field>
                    <Field label="Contact phone" error={fieldError(error, 'on_site_contact_phone')}>
                        <input className="field" value={contactPhone} onChange={(event) => setContactPhone(event.target.value)} />
                    </Field>
                </div>
            )}
            <button className="btn-copper" disabled={pending || !packageId} type="submit">{pending ? 'Booking…' : 'Book this package'}</button>
        </form>
    );
}
