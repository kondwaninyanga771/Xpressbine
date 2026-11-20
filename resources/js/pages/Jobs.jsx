import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, fieldError, firstError, listOf, recordOf } from '../api';
import { useAuth } from '../auth';
import { Alert, Field, RequireAuth, Shell } from '../components/ui';
import { AREAS, kwacha, when } from '../format';

export default function Jobs() {
    return (
        <RequireAuth>
            <JobsScreen />
        </RequireAuth>
    );
}

function JobsScreen() {
    const { user } = useAuth();
    const [jobs, setJobs] = useState([]);
    const [categories, setCategories] = useState([]);
    const [error, setError] = useState(null);
    const [form, setForm] = useState({ category: 'plumbing', title: '', description: '', address: '', area: user.area || 'Chilenje', budget_max: '' });
    const [quote, setQuote] = useState({});

    function load() {
        return Promise.all([api('/job-requests'), api('/categories')]).then(([jobPayload, categoryPayload]) => {
            setJobs(listOf(jobPayload));
            setCategories(listOf(categoryPayload));
        });
    }

    useEffect(() => {
        load().catch((reason) => setError(reason));
    }, []);

    async function createJob(event) {
        event.preventDefault();
        setError(null);

        try {
            await api('/job-requests', {
                method: 'POST',
                json: {
                    category: form.category,
                    title: form.title,
                    description: form.description,
                    address: form.address,
                    area: form.area,
                    budget_max: form.budget_max ? Number(form.budget_max) : null,
                },
            });
            setForm({ ...form, title: '', description: '', address: '', budget_max: '' });
            await load();
        } catch (reason) {
            setError(reason);
        }
    }

    async function sendQuote(jobId) {
        setError(null);
        const draft = quote[jobId] || {};

        try {
            await api(`/job-requests/${jobId}/quotes`, {
                method: 'POST',
                json: { amount: Number(draft.amount), message: draft.message || null },
            });
            await load();
        } catch (reason) {
            setError(reason);
        }
    }

    async function accept(quoteId) {
        setError(null);

        try {
            const payload = await api(`/quotes/${quoteId}/accept`, { method: 'POST' });
            window.location.assign(`/bookings/${recordOf(payload).id}`);
        } catch (reason) {
            setError(reason);
        }
    }

    const canPost = user.role === 'customer' || user.role === 'business_client';

    return (
        <Shell>
            <Head title="Jobs" />
            <div className="mx-auto max-w-[1100px] space-y-4 px-4 py-6 md:px-8">
                <h1 className="font-display text-3xl font-semibold">Job requests</h1>
                <p className="text-sm text-slate">Post a job and providers send quotes. Accepting a quote creates a booking.</p>
                {error && <Alert>{firstError(error)}</Alert>}
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="space-y-3">
                        {jobs.map((job) => (
                            <article key={job.id} className="card p-4">
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <p className="font-display text-xs font-bold uppercase tracking-[0.08em] text-zambezi">{job.status}</p>
                                        <h2 className="font-display text-xl font-semibold">{job.title}</h2>
                                        <p className="mt-1 text-sm text-slate">{job.category?.name} · {job.area} · {job.address}</p>
                                    </div>
                                    <p className="font-display font-semibold">{job.budget_max ? `up to ${kwacha(job.budget_max)}` : ''}</p>
                                </div>
                                <p className="mt-2 text-sm">{job.description}</p>
                                <p className="mt-1 text-xs text-slate">{job.customer?.name} · {when(job.preferred_at)}</p>
                                <div className="mt-3 space-y-2">
                                    {(Array.isArray(job.quotes) ? job.quotes : (job.quotes?.data || [])).map((item) => (
                                        <div key={item.id} className="flex flex-wrap items-center justify-between gap-2 rounded-2xl bg-sand px-3 py-2 text-sm">
                                            <span>{item.provider?.user?.name || 'Provider'} · {kwacha(item.amount)} · {item.status}</span>
                                            {canPost && item.status === 'pending' && job.customer_id === user.id && (
                                                <button type="button" className="btn-primary !px-3 !py-1.5" onClick={() => accept(item.id)}>Accept quote</button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                {user.role === 'provider' && job.status === 'open' && (
                                    <div className="mt-3 grid gap-2 md:grid-cols-[140px_1fr_auto]">
                                        <input className="field" placeholder="Amount" value={quote[job.id]?.amount || ''} onChange={(event) => setQuote({ ...quote, [job.id]: { ...quote[job.id], amount: event.target.value } })} />
                                        <input className="field" placeholder="Message" value={quote[job.id]?.message || ''} onChange={(event) => setQuote({ ...quote, [job.id]: { ...quote[job.id], message: event.target.value } })} />
                                        <button type="button" className="btn-copper" onClick={() => sendQuote(job.id)}>Send quote</button>
                                    </div>
                                )}
                            </article>
                        ))}
                        {!jobs.length && <Alert tone="ok">No job requests yet.</Alert>}
                    </div>
                    {canPost && (
                        <form onSubmit={createJob} className="card h-fit space-y-3 p-5">
                            <h2 className="font-display text-xl font-semibold">Post a job</h2>
                            <Field label="Trade" error={fieldError(error, 'category')}>
                                <select className="field" value={form.category} onChange={(event) => setForm({ ...form, category: event.target.value })}>
                                    {categories.map((category) => <option key={category.id} value={category.slug}>{category.name}</option>)}
                                </select>
                            </Field>
                            <Field label="Title" error={fieldError(error, 'title')}>
                                <input className="field" required value={form.title} onChange={(event) => setForm({ ...form, title: event.target.value })} />
                            </Field>
                            <Field label="Description" error={fieldError(error, 'description')}>
                                <textarea className="field !h-24 py-3" required value={form.description} onChange={(event) => setForm({ ...form, description: event.target.value })} />
                            </Field>
                            <Field label="Address" error={fieldError(error, 'address')}>
                                <input className="field" required value={form.address} onChange={(event) => setForm({ ...form, address: event.target.value })} />
                            </Field>
                            <Field label="Area" error={fieldError(error, 'area')}>
                                <input className="field" list="job-areas" value={form.area} onChange={(event) => setForm({ ...form, area: event.target.value })} />
                                <datalist id="job-areas">{AREAS.map((area) => <option key={area.name} value={area.name} />)}</datalist>
                            </Field>
                            <Field label="Budget max (K)" error={fieldError(error, 'budget_max')}>
                                <input type="number" min="0" className="field" value={form.budget_max} onChange={(event) => setForm({ ...form, budget_max: event.target.value })} />
                            </Field>
                            <button className="btn-primary w-full" type="submit">Post job</button>
                        </form>
                    )}
                </div>
            </div>
        </Shell>
    );
}
