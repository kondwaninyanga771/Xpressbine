import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, firstError } from '../api';
import { Alert, Field, RequireAuth, Shell } from '../components/ui';
import { humanize, kwacha, WALLET_METHODS, when } from '../format';

export default function Wallet() {
    return (
        <RequireAuth role="business_client">
            <WalletScreen />
        </RequireAuth>
    );
}

function WalletScreen() {
    const [business, setBusiness] = useState(null);
    const [statement, setStatement] = useState(null);
    const [amount, setAmount] = useState('1000');
    const [method, setMethod] = useState('mtn_momo');
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    function load() {
        return Promise.all([api('/business'), api('/business/wallet/statement')]).then(([profile, rows]) => {
            setBusiness(profile.data);
            setStatement(rows);
        });
    }

    useEffect(() => {
        load().catch((reason) => setError(reason.message));
    }, []);

    async function topUp(event) {
        event.preventDefault();
        setError('');
        setNotice('');

        try {
            const payload = await api('/business/wallet/topup', { method: 'POST', json: { amount: Number(amount), method } });
            setNotice(`Balance ${kwacha(payload.data.balance)}`);
            await load();
        } catch (reason) {
            setError(firstError(reason));
        }
    }

    return (
        <Shell>
            <Head title="Business wallet" />
            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 md:px-8">
                <p className="font-display text-xs font-bold uppercase tracking-[0.12em] text-zambezi">Business</p>
                <h1 className="font-display text-3xl font-semibold">{business?.name || 'Wallet'}</h1>
                {error && <Alert>{error}</Alert>}
                {notice && <Alert tone="ok">{notice}</Alert>}
                <section className="rounded-[28px] bg-ink p-6 text-white">
                    <p className="font-display text-xs font-bold uppercase tracking-[0.12em] text-mint">Wallet balance</p>
                    <p className="mt-2 font-display text-4xl font-semibold">{kwacha(business?.wallet?.balance)}</p>
                    <p className="text-sm text-white/70">{business?.wallet?.currency}</p>
                </section>
                <section className="space-y-3">
                    <h2 className="font-display text-xl font-semibold">Sites</h2>
                    {(business?.sites || []).map((site) => (
                        <article key={site.id} className="card p-4">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-display font-semibold">{site.name}</h3>
                                    <p className="text-sm text-slate">{site.area} · {site.address}</p>
                                </div>
                                {site.budget_alert && <span className="pill bg-copper/10 text-copper">{site.budget_alert}</span>}
                            </div>
                            <p className="mt-2 text-sm">Spent this month {kwacha(site.spent_this_month)}{site.spending_limit ? ` of ${kwacha(site.spending_limit)}` : ''}</p>
                        </article>
                    ))}
                </section>
                <form onSubmit={topUp} className="card space-y-3 p-5">
                    <h2 className="font-display text-xl font-semibold">Top up</h2>
                    <p className="text-sm text-slate">POST /api/business/wallet/topup. This is the same mock money the API already records.</p>
                    <div className="grid gap-3 md:grid-cols-2">
                        <Field label="Amount">
                            <input type="number" min="1" className="field" value={amount} onChange={(event) => setAmount(event.target.value)} required />
                        </Field>
                        <Field label="Method">
                            <select className="field" value={method} onChange={(event) => setMethod(event.target.value)}>
                                {WALLET_METHODS.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                            </select>
                        </Field>
                    </div>
                    <button className="btn-primary" type="submit">Top up wallet</button>
                </form>
                <section>
                    <h2 className="font-display text-xl font-semibold">Statement</h2>
                    <div className="mt-3 space-y-2">
                        {(statement?.data || []).map((entry) => (
                            <article key={entry.id} className="card flex items-center justify-between px-4 py-3 text-sm">
                                <div>
                                    <p className="font-display font-semibold">{humanize(entry.type)}</p>
                                    <p className="text-slate">{entry.description} · {when(entry.created_at)}</p>
                                </div>
                                <p className="font-display font-semibold">{kwacha(entry.amount)}</p>
                            </article>
                        ))}
                    </div>
                    {statement && <p className="mt-2 text-xs text-slate">Balance on the statement response: {kwacha(statement.balance)}</p>}
                </section>
                <Alert tone="stub">
                    Monthly plans, the 25th plan suggestion, month-end PDF, forecasts, anomaly alerts and provider-value comparison are not in the API. This screen stops at the wallet, sites and statement that exist today.
                </Alert>
            </div>
        </Shell>
    );
}
