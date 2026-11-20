import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, fieldError, firstError, listOf, recordOf } from '../api';
import { Alert, Field, RequireAuth, Shell } from '../components/ui';
import { AREAS, humanize, kwacha, MOBILE_MONEY, STATUS_LABEL, when } from '../format';

const TABS = ['profile', 'packages', 'jobs', 'earnings'];

export default function Dashboard() {
    return (
        <RequireAuth role="provider">
            <DashboardScreen />
        </RequireAuth>
    );
}

function DashboardScreen() {
    const [tab, setTab] = useState('profile');

    return (
        <Shell>
            <Head title="Provider dashboard" />
            <div className="mx-auto max-w-3xl px-4 py-6 md:px-8">
                <p className="font-display text-xs font-bold uppercase tracking-[0.12em] text-zambezi">Provider</p>
                <h1 className="font-display text-3xl font-semibold">Dashboard</h1>
                <div className="mt-4 flex gap-2 overflow-x-auto">
                    {TABS.map((item) => (
                        <button key={item} type="button" onClick={() => setTab(item)} className={`pill border ${tab === item ? 'border-zambezi bg-zambezi text-white' : 'border-cloud bg-white'}`}>
                            {item}
                        </button>
                    ))}
                </div>
                <div className="mt-5">
                    {tab === 'profile' && <ProfileTab />}
                    {tab === 'packages' && <PackagesTab />}
                    {tab === 'jobs' && <JobsTab />}
                    {tab === 'earnings' && <EarningsTab />}
                </div>
            </div>
        </Shell>
    );
}

function ProfileTab() {
    const [profile, setProfile] = useState(null);
    const [categories, setCategories] = useState([]);
    const [form, setForm] = useState(null);
    const [error, setError] = useState(null);
    const [notice, setNotice] = useState('');
    const [pending, setPending] = useState(false);

    useEffect(() => {
        Promise.all([api('/provider/profile'), api('/categories')]).then(([profilePayload, categoryPayload]) => {
            const next = recordOf(profilePayload);
            setProfile(next);
            setCategories(listOf(categoryPayload));
            setForm({
                bio: next.bio || '',
                nrc_number: next.nrc_number || '',
                years_experience: next.years_experience ?? '',
                hourly_rate: next.hourly_rate ?? '',
                service_radius_km: next.service_radius_km ?? '',
                area: next.user?.area || '',
                mobile_money_provider: next.user?.mobile_money_provider || '',
                mobile_money_number: next.user?.mobile_money_number || '',
                category_ids: (next.categories || []).map((category) => category.id),
            });
        }).catch((reason) => setError(reason));
    }, []);

    function set(key, value) {
        setForm((current) => ({ ...current, [key]: value }));
    }

    async function save(event) {
        event.preventDefault();
        setPending(true);
        setError(null);
        setNotice('');
        const area = AREAS.find((item) => item.name === form.area);

        try {
            const payload = await api('/provider/profile', {
                method: 'PUT',
                json: {
                    bio: form.bio || null,
                    nrc_number: form.nrc_number || null,
                    years_experience: form.years_experience === '' ? null : Number(form.years_experience),
                    hourly_rate: form.hourly_rate === '' ? null : Number(form.hourly_rate),
                    service_radius_km: form.service_radius_km === '' ? null : Number(form.service_radius_km),
                    area: form.area || null,
                    latitude: area?.latitude,
                    longitude: area?.longitude,
                    mobile_money_provider: form.mobile_money_provider || null,
                    mobile_money_number: form.mobile_money_number || null,
                    category_ids: form.category_ids,
                },
            });
            setProfile(recordOf(payload));
            setNotice(`Verification: ${recordOf(payload).verification_status}`);
        } catch (reason) {
            setError(reason);
        } finally {
            setPending(false);
        }
    }

    async function upload(event) {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        const body = new FormData();
        body.append('photo', file);

        try {
            await api('/provider/photos', { method: 'POST', form: body });
            const payload = await api('/provider/profile');
            setProfile(recordOf(payload));
            setNotice('Photo uploaded.');
        } catch (reason) {
            setError(reason);
        }
    }

    if (!form) {
        return error ? <Alert>{firstError(error)}</Alert> : <p className="text-sm text-slate">Loading profile…</p>;
    }

    return (
        <form onSubmit={save} className="card space-y-4 p-5">
            {profile?.verification_status && (
                <p className="pill bg-sand">Status {profile.verification_status}{profile.verified_at ? ` · ${when(profile.verified_at)}` : ''}</p>
            )}
            {profile?.rejection_reason && <Alert>{profile.rejection_reason}</Alert>}
            {notice && <Alert tone="ok">{notice}</Alert>}
            {error && <Alert>{firstError(error)}</Alert>}
            <Field label="Bio" error={fieldError(error, 'bio')}>
                <textarea className="field !h-28 py-3" value={form.bio} onChange={(event) => set('bio', event.target.value)} />
            </Field>
            <Field label="NRC number" error={fieldError(error, 'nrc_number')}>
                <input className="field" value={form.nrc_number} onChange={(event) => set('nrc_number', event.target.value)} />
            </Field>
            <div className="grid gap-3 md:grid-cols-3">
                <Field label="Years" error={fieldError(error, 'years_experience')}>
                    <input type="number" min="0" className="field" value={form.years_experience} onChange={(event) => set('years_experience', event.target.value)} />
                </Field>
                <Field label="Hourly rate" error={fieldError(error, 'hourly_rate')}>
                    <input type="number" min="0" className="field" value={form.hourly_rate} onChange={(event) => set('hourly_rate', event.target.value)} />
                </Field>
                <Field label="Radius km" error={fieldError(error, 'service_radius_km')}>
                    <input type="number" min="1" className="field" value={form.service_radius_km} onChange={(event) => set('service_radius_km', event.target.value)} />
                </Field>
            </div>
            <Field label="Area" error={fieldError(error, 'area')}>
                <input className="field" list="provider-areas" value={form.area} onChange={(event) => set('area', event.target.value)} />
                <datalist id="provider-areas">{AREAS.map((area) => <option key={area.name} value={area.name} />)}</datalist>
            </Field>
            <div className="grid gap-3 md:grid-cols-2">
                <Field label="Payout network" error={fieldError(error, 'mobile_money_provider')}>
                    <select className="field" value={form.mobile_money_provider || ''} onChange={(event) => set('mobile_money_provider', event.target.value)}>
                        <option value="">None</option>
                        {MOBILE_MONEY.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                    </select>
                </Field>
                <Field label="Payout number" error={fieldError(error, 'mobile_money_number')}>
                    <input className="field" value={form.mobile_money_number || ''} onChange={(event) => set('mobile_money_number', event.target.value)} />
                </Field>
            </div>
            <fieldset>
                <legend className="mb-2 font-display text-xs font-semibold uppercase tracking-[0.04em] text-slate">Trades</legend>
                <div className="flex flex-wrap gap-2">
                    {categories.map((category) => {
                        const on = form.category_ids.includes(category.id);
                        return (
                            <button
                                key={category.id}
                                type="button"
                                className={`pill border ${on ? 'border-zambezi bg-zambezi text-white' : 'border-cloud bg-white'}`}
                                onClick={() => set('category_ids', on ? form.category_ids.filter((id) => id !== category.id) : [...form.category_ids, category.id])}
                            >
                                {category.name}
                            </button>
                        );
                    })}
                </div>
            </fieldset>
            <label className="block text-sm">
                <span className="mb-1 block font-display text-xs font-semibold uppercase tracking-[0.04em] text-slate">Past-work photo</span>
                <input type="file" accept="image/*" onChange={upload} />
            </label>
            {profile?.photos?.length > 0 && (
                <div className="grid grid-cols-3 gap-2">
                    {profile.photos.map((photo) => <img key={photo.id} src={photo.url} alt={photo.caption || ''} className="h-24 w-full rounded-xl object-cover" />)}
                </div>
            )}
            <button className="btn-primary" disabled={pending} type="submit">{pending ? 'Saving…' : 'Save profile'}</button>
        </form>
    );
}

function PackagesTab() {
    const [packages, setPackages] = useState([]);
    const [categories, setCategories] = useState([]);
    const [form, setForm] = useState({ category: 'plumbing', title: '', description: '', price: '', duration_minutes: 120 });
    const [error, setError] = useState(null);

    async function load() {
        const [profilePayload, categoryPayload] = await Promise.all([api('/provider/profile'), api('/categories')]);
        setPackages(recordOf(profilePayload).packages || []);
        setCategories(listOf(categoryPayload));
    }

    useEffect(() => {
        load().catch((reason) => setError(reason));
    }, []);

    async function create(event) {
        event.preventDefault();
        setError(null);

        try {
            await api('/provider/packages', {
                method: 'POST',
                json: {
                    category: form.category,
                    title: form.title,
                    description: form.description || null,
                    price: Number(form.price),
                    duration_minutes: Number(form.duration_minutes),
                },
            });
            setForm({ ...form, title: '', description: '', price: '' });
            await load();
        } catch (reason) {
            setError(reason);
        }
    }

    async function toggle(item) {
        setError(null);

        try {
            await api(`/provider/packages/${item.id}`, { method: 'PUT', json: { is_active: !item.is_active } });
            await load();
        } catch (reason) {
            setError(reason);
        }
    }

    return (
        <div className="space-y-4">
            {error && <Alert>{firstError(error)}</Alert>}
            {packages.map((item) => (
                <article key={item.id} className="card flex items-center justify-between gap-3 p-4">
                    <div>
                        <h3 className="font-display font-semibold">{item.title}</h3>
                        <p className="text-sm text-slate">{kwacha(item.price)} · {item.duration_minutes} min · {item.is_active ? 'active' : 'hidden'}</p>
                    </div>
                    <button type="button" className="btn-ghost !px-4 !py-2" onClick={() => toggle(item)}>{item.is_active ? 'Hide' : 'Show'}</button>
                </article>
            ))}
            <form onSubmit={create} className="card space-y-3 p-5">
                <h2 className="font-display text-xl font-semibold">New package</h2>
                <Field label="Trade" error={fieldError(error, 'category')}>
                    <select className="field" value={form.category} onChange={(event) => setForm({ ...form, category: event.target.value })}>
                        {categories.map((category) => <option key={category.id} value={category.slug}>{category.name}</option>)}
                    </select>
                </Field>
                <Field label="Title" error={fieldError(error, 'title')}>
                    <input className="field" required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} />
                </Field>
                <Field label="Description" error={fieldError(error, 'description')}>
                    <textarea className="field !h-20 py-3" value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
                </Field>
                <div className="grid grid-cols-2 gap-3">
                    <Field label="Price (K)" error={fieldError(error, 'price')}>
                        <input type="number" min="1" required className="field" value={form.price} onChange={(event) => setForm({ ...form, price: event.target.value })} />
                    </Field>
                    <Field label="Minutes" error={fieldError(error, 'duration_minutes')}>
                        <input type="number" min="30" className="field" value={form.duration_minutes} onChange={(event) => setForm({ ...form, duration_minutes: event.target.value })} />
                    </Field>
                </div>
                <button className="btn-primary" type="submit">Add package</button>
            </form>
        </div>
    );
}

function JobsTab() {
    const [bookings, setBookings] = useState([]);
    const [error, setError] = useState('');

    useEffect(() => {
        api('/bookings').then((payload) => setBookings(listOf(payload))).catch((reason) => setError(reason.message));
    }, []);

    return (
        <div className="space-y-3">
            {error && <Alert>{error}</Alert>}
            {bookings.map((booking) => (
                <Link key={booking.id} href={`/bookings/${booking.id}`} className="card block p-4">
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="font-display font-semibold">{booking.package?.title || booking.reference}</p>
                            <p className="text-sm text-slate">{booking.customer?.name} · {booking.area} · {when(booking.scheduled_at)}</p>
                        </div>
                        <span className="pill bg-sand">{STATUS_LABEL[booking.status] || booking.status}</span>
                    </div>
                    <p className="mt-2 text-sm">Escrow {kwacha(booking.escrow_balance)} · {kwacha(booking.amount)}</p>
                </Link>
            ))}
            {!bookings.length && !error && <Alert tone="ok">No jobs yet. Customers book a package, pay, then you can accept.</Alert>}
        </div>
    );
}

function EarningsTab() {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        api('/provider/earnings').then((payload) => setData(payload.data)).catch((reason) => setError(reason.message));
    }, []);

    if (error) {
        return <Alert>{error}</Alert>;
    }

    if (!data) {
        return <p className="text-sm text-slate">Loading earnings…</p>;
    }

    return (
        <div className="space-y-3">
            <section className="rounded-[28px] bg-ink p-6 text-white">
                <p className="font-display text-xs font-bold uppercase tracking-[0.12em] text-mint">Paid out</p>
                <p className="mt-2 font-display text-4xl font-semibold">{kwacha(data.earnings)}</p>
                <p className="text-sm text-white/70">{data.currency}. Payout and call-out fee rows only.</p>
            </section>
            {(data.entries || []).map((entry) => (
                <article key={entry.id} className="card flex items-center justify-between px-4 py-3 text-sm">
                    <div>
                        <p className="font-display font-semibold">{humanize(entry.type)}</p>
                        <p className="text-slate">{entry.description}</p>
                    </div>
                    <p className="font-display font-semibold">{kwacha(entry.amount)}</p>
                </article>
            ))}
            {!data.entries?.length && <Alert tone="ok">No payout rows yet.</Alert>}
        </div>
    );
}
