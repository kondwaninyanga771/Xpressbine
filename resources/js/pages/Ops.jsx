import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, firstError, listOf } from '../api';
import { Alert, Field, RequireAuth, Shell } from '../components/ui';
import { humanize, kwacha, when } from '../format';

export default function Ops() {
    return (
        <RequireAuth role="admin">
            <OpsScreen />
        </RequireAuth>
    );
}

function OpsScreen() {
    const [tab, setTab] = useState('providers');

    return (
        <Shell>
            <Head title="Trust desk" />
            <div className="mx-auto max-w-4xl px-4 py-6 md:px-8">
                <p className="font-display text-xs font-bold uppercase tracking-[0.12em] text-zambezi">Admin</p>
                <h1 className="font-display text-3xl font-semibold">Trust desk</h1>
                <p className="mt-1 text-sm text-slate">Verification and disputes from the admin API. The Filament panel at /admin is unchanged.</p>
                <div className="mt-4 flex gap-2">
                    <button type="button" className={`pill border ${tab === 'providers' ? 'border-zambezi bg-zambezi text-white' : 'border-cloud bg-white'}`} onClick={() => setTab('providers')}>Verification</button>
                    <button type="button" className={`pill border ${tab === 'disputes' ? 'border-zambezi bg-zambezi text-white' : 'border-cloud bg-white'}`} onClick={() => setTab('disputes')}>Disputes</button>
                </div>
                <div className="mt-5">{tab === 'providers' ? <Providers /> : <Disputes />}</div>
            </div>
        </Shell>
    );
}

function Providers() {
    const [rows, setRows] = useState([]);
    const [error, setError] = useState('');
    const [reason, setReason] = useState('');

    function load() {
        api('/admin/providers?status=pending').then((payload) => setRows(listOf(payload))).catch((failure) => setError(failure.message));
    }

    useEffect(() => {
        load();
    }, []);

    async function verify(id) {
        setError('');
        try {
            await api(`/admin/providers/${id}/verify`, { method: 'POST' });
            load();
        } catch (failure) {
            setError(firstError(failure));
        }
    }

    async function reject(id) {
        setError('');
        try {
            await api(`/admin/providers/${id}/reject`, { method: 'POST', json: { reason } });
            load();
        } catch (failure) {
            setError(firstError(failure));
        }
    }

    return (
        <div className="space-y-3">
            {error && <Alert>{error}</Alert>}
            <Field label="Rejection reason, used if you reject">
                <input className="field" value={reason} onChange={(event) => setReason(event.target.value)} placeholder="NRC photo was unreadable" />
            </Field>
            {rows.map((provider) => (
                <article key={provider.id} className="card p-4">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="font-display text-xl font-semibold">{provider.user?.name}</h2>
                            <p className="text-sm text-slate">{provider.user?.area} · {provider.verification_status}</p>
                            <p className="mt-2 text-sm">{provider.bio}</p>
                            {provider.nrc_number && <p className="mt-1 text-xs text-slate">NRC {provider.nrc_number}</p>}
                            <p className="mt-1 text-xs text-slate">{(provider.categories || []).map((category) => category.name).join(', ')}</p>
                        </div>
                        <div className="flex gap-2">
                            <button type="button" className="btn-primary !px-4 !py-2" onClick={() => verify(provider.id)}>Verify</button>
                            <button type="button" className="btn-ghost !px-4 !py-2" onClick={() => reject(provider.id)}>Reject</button>
                        </div>
                    </div>
                </article>
            ))}
            {!rows.length && <Alert tone="ok">No providers are waiting for verification.</Alert>}
        </div>
    );
}

function Disputes() {
    const [rows, setRows] = useState([]);
    const [error, setError] = useState('');
    const [amount, setAmount] = useState('');
    const [notes, setNotes] = useState('');

    function load() {
        api('/admin/disputes').then((payload) => setRows(listOf(payload))).catch((failure) => setError(failure.message));
    }

    useEffect(() => {
        load();
    }, []);

    async function resolve(id, outcome) {
        setError('');
        const json = { outcome, notes: notes || null };

        if (outcome === 'partial_refund') {
            json.amount = Number(amount);
        }

        try {
            await api(`/admin/disputes/${id}/resolve`, { method: 'POST', json });
            load();
        } catch (failure) {
            setError(firstError(failure));
        }
    }

    return (
        <div className="space-y-3">
            {error && <Alert>{error}</Alert>}
            <div className="grid gap-3 md:grid-cols-2">
                <Field label="Partial refund amount">
                    <input className="field" value={amount} onChange={(event) => setAmount(event.target.value)} />
                </Field>
                <Field label="Notes">
                    <input className="field" value={notes} onChange={(event) => setNotes(event.target.value)} />
                </Field>
            </div>
            {rows.map((dispute) => (
                <article key={dispute.id} className="card p-4">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <p className="font-display text-xs font-bold uppercase tracking-[0.08em] text-copper">{humanize(dispute.status)}{dispute.outcome ? ` · ${humanize(dispute.outcome)}` : ''}</p>
                            <h2 className="mt-1 font-display text-lg font-semibold">{dispute.booking?.reference || `Booking ${dispute.booking_id}`}</h2>
                            <p className="mt-1 text-sm">{dispute.reason}</p>
                            <p className="mt-1 text-xs text-slate">{dispute.booking?.customer?.name} · {kwacha(dispute.booking?.amount)} · {when(dispute.created_at)}</p>
                        </div>
                    </div>
                    {dispute.status !== 'resolved' && (
                        <div className="mt-3 flex flex-wrap gap-2">
                            <button type="button" className="btn-primary !px-4 !py-2" onClick={() => resolve(dispute.id, 'full_refund')}>Full refund</button>
                            <button type="button" className="btn-ghost !px-4 !py-2" onClick={() => resolve(dispute.id, 'partial_refund')}>Partial</button>
                            <button type="button" className="btn-ghost !px-4 !py-2" onClick={() => resolve(dispute.id, 'release')}>Release</button>
                            <button type="button" className="btn-copper !px-4 !py-2" onClick={() => resolve(dispute.id, 'fix_first')}>Fix first</button>
                        </div>
                    )}
                </article>
            ))}
            {!rows.length && <Alert tone="ok">No disputes.</Alert>}
        </div>
    );
}
