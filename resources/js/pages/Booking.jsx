import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, firstError, listOf, recordOf } from '../api';
import { useAuth } from '../auth';
import { Alert, Field, RequireAuth, Shell, Stars } from '../components/ui';
import { funded, humanize, kwacha, MOBILE_MONEY, STATUS_LABEL, when } from '../format';

const FLOW = ['requested', 'accepted', 'in_progress', 'completed'];

export default function Booking({ bookingId }) {
    return (
        <RequireAuth>
            <BookingScreen bookingId={bookingId} />
        </RequireAuth>
    );
}

function BookingScreen({ bookingId }) {
    const { user } = useAuth();
    const [booking, setBooking] = useState(null);
    const [ledger, setLedger] = useState([]);
    const [error, setError] = useState('');
    const [notice, setNotice] = useState('');

    async function load() {
        const [bookingPayload, ledgerPayload] = await Promise.all([
            api(`/bookings/${bookingId}`),
            api(`/bookings/${bookingId}/ledger`),
        ]);
        setBooking(recordOf(bookingPayload));
        setLedger(listOf(ledgerPayload));
    }

    useEffect(() => {
        load().catch((reason) => setError(reason.message));
    }, [bookingId]);

    async function act(path, json) {
        setError('');
        setNotice('');

        try {
            const payload = await api(path, { method: 'POST', json });
            if (payload?.data && payload.data.status) {
                setBooking(recordOf(payload));
            }
            await load();
            setNotice('Updated.');
        } catch (reason) {
            setError(firstError(reason));
        }
    }

    if (error && !booking) {
        return <Shell><div className="mx-auto max-w-3xl px-4 py-8"><Alert>{error}</Alert></div></Shell>;
    }

    if (!booking) {
        return <Shell><p className="px-4 py-16 text-center text-sm text-slate">Loading booking…</p></Shell>;
    }

    const isCustomer = booking.customer_id === user.id;
    const isProvider = user.role === 'provider' && booking.provider_profile_id === user.provider_profile_id;
    const otherName = isCustomer ? booking.provider?.user?.name : booking.customer?.name;
    const otherId = isCustomer ? booking.provider?.user?.id : booking.customer?.id;
    const released = Boolean(booking.escrow_released_at);
    const isFunded = funded(booking);
    const myRating = (booking.ratings || []).find((rating) => rating.rater_id === user.id);

    return (
        <Shell>
            <Head title={booking.reference || 'Booking'} />
            <div className="mx-auto max-w-3xl space-y-4 px-4 py-6 md:px-8">
                <Link href="/bookings" className="font-display text-sm font-semibold text-zambezi">All bookings</Link>
                <section className="rounded-[28px] bg-ink p-6 text-white">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="pill bg-white/10">{booking.reference}</span>
                        <span className="pill bg-mint/20 text-mint">{STATUS_LABEL[booking.status] || booking.status}</span>
                        {released && <span className="pill bg-mint text-ink">Escrow released</span>}
                    </div>
                    <h1 className="mt-4 font-display text-3xl font-semibold">{booking.package?.title || booking.category?.name || 'Booking'}</h1>
                    <p className="mt-2 text-sm text-white/75">{otherName} · {booking.area}</p>
                    <p className="mt-4 font-display text-3xl font-semibold">{kwacha(booking.amount)}</p>
                    <p className="mt-1 text-sm text-white/70">Escrow balance {kwacha(booking.escrow_balance)} {booking.currency}</p>
                </section>

                <Tracker booking={booking} />

                {error && <Alert>{error}</Alert>}
                {notice && <Alert tone="ok">{notice}</Alert>}

                <section className="card space-y-2 p-5 text-sm">
                    <Row label="Scheduled" value={when(booking.scheduled_at)} />
                    <Row label="Address" value={booking.address} />
                    <Row label="Area" value={booking.area} />
                    <Row label="Duration" value={booking.duration_minutes ? `${booking.duration_minutes} min` : ''} />
                    <Row label="Notes" value={booking.notes} />
                    <Row label="Accepted" value={when(booking.accepted_at)} />
                    <Row label="Started" value={when(booking.started_at)} />
                    <Row label="Completed" value={when(booking.completed_at)} />
                    <Row label="Confirmed" value={when(booking.customer_confirmed_at)} />
                    <Row label="Auto release" value={when(booking.auto_release_at)} />
                    {booking.escrow_released_at && <Row label="Escrow released" value={when(booking.escrow_released_at)} />}
                    {booking.commission_amount && <Row label="Commission" value={kwacha(booking.commission_amount)} />}
                    {booking.payout_amount && <Row label="Payout" value={kwacha(booking.payout_amount)} />}
                    {booking.cancellation_reason && <Row label="Cancellation" value={booking.cancellation_reason} />}
                    {booking.entry_pin && <Row label="Entry PIN" value={booking.entry_pin} />}
                    {booking.on_site_contact_name && <Row label="On-site contact" value={`${booking.on_site_contact_name} ${booking.on_site_contact_phone || ''}`} />}
                </section>

                {isCustomer && booking.status === 'requested' && !isFunded && (
                    <PayForm booking={booking} user={user} onPaid={() => load()} onError={setError} />
                )}
                {isCustomer && booking.status === 'requested' && isFunded && (
                    <Alert tone="ok">Escrow is funded. The provider can accept the job.</Alert>
                )}

                {isCustomer && booking.status === 'completed' && !released && !booking.customer_confirmed_at && (
                    <section className="card p-5">
                        <h2 className="font-display text-xl font-semibold">Confirm the job</h2>
                        <p className="mt-1 text-sm text-slate">POST /api/bookings/{booking.id}/confirm releases the escrow to the provider.</p>
                        <button type="button" className="btn-primary mt-4" onClick={() => act(`/bookings/${booking.id}/confirm`)}>Confirm job done</button>
                    </section>
                )}

                {isProvider && booking.status === 'requested' && isFunded && (
                    <Action title="Accept this job" body="Escrow is funded. POST /accept." onClick={() => act(`/bookings/${booking.id}/accept`)} label="Accept booking" />
                )}
                {isProvider && booking.status === 'requested' && !isFunded && (
                    <Alert>Waiting for the customer to pay into escrow. Accept stays closed until then.</Alert>
                )}
                {isProvider && booking.status === 'accepted' && (
                    <Action title="Start the job" body="POST /start." onClick={() => act(`/bookings/${booking.id}/start`)} label="Start job" />
                )}
                {isProvider && booking.status === 'in_progress' && (
                    <Action title="Mark the job complete" body="POST /complete. The customer then confirms, or escrow auto-releases." onClick={() => act(`/bookings/${booking.id}/complete`)} label="Mark complete" />
                )}

                {booking.client_absent && isProvider && (booking.status === 'accepted' || booking.status === 'in_progress') && (
                    <PhotoUpload booking={booking} kind={booking.status === 'accepted' ? 'before' : 'after'} onDone={load} onError={setError} />
                )}

                {(isCustomer || isProvider) && (booking.status === 'requested' || booking.status === 'accepted') && (
                    <ReasonForm
                        title="Cancel"
                        hint="POST /cancel. A late cancellation can keep a fee."
                        label="Cancel booking"
                        onSubmit={(reason) => act(`/bookings/${booking.id}/cancel`, { reason })}
                    />
                )}

                {isCustomer && booking.status === 'accepted' && (
                    <Action title="Report a no-show" body="POST /no-show. The API rejects this inside the grace period." onClick={() => act(`/bookings/${booking.id}/no-show`)} label="Report no-show" copper />
                )}

                {isProvider && booking.status === 'accepted' && (
                    <Action title="Customer was not there" body="POST /customer-absent. A call-out fee may apply." onClick={() => act(`/bookings/${booking.id}/customer-absent`)} label="Report customer absent" copper />
                )}

                {isCustomer && booking.status === 'completed' && !released && (
                    <DisputeForm booking={booking} onDone={load} onError={setError} />
                )}

                {(released || booking.customer_confirmed_at) && !myRating && (isCustomer || isProvider) && (
                    <RateForm booking={booking} onDone={load} onError={setError} />
                )}

                {booking.ratings?.length > 0 && (
                    <section className="space-y-2">
                        {booking.ratings.map((rating) => (
                            <article key={rating.id} className="card p-4">
                                <div className="flex items-center justify-between">
                                    <p className="font-display font-semibold">{rating.rater?.name || 'Rating'}</p>
                                    <Stars value={rating.stars} count={1} />
                                </div>
                                {rating.comment && <p className="mt-2 text-sm text-slate">{rating.comment}</p>}
                            </article>
                        ))}
                    </section>
                )}

                {otherId && <Link href={`/messages/${otherId}`} className="btn-ghost">Message {otherName}</Link>}

                <section>
                    <h2 className="font-display text-xl font-semibold">Ledger</h2>
                    <div className="mt-3 space-y-2">
                        {ledger.map((entry) => (
                            <article key={entry.id} className="card flex items-start justify-between gap-3 px-4 py-3 text-sm">
                                <div>
                                    <p className="font-display font-semibold">{humanize(entry.type)}</p>
                                    <p className="text-slate">{entry.description}</p>
                                </div>
                                <div className="text-right">
                                    <p className="font-display font-semibold">{kwacha(entry.amount)}</p>
                                    <p className="text-xs text-slate">escrow {entry.escrow_delta}</p>
                                </div>
                            </article>
                        ))}
                        {!ledger.length && <Alert tone="ok">No ledger rows yet.</Alert>}
                    </div>
                </section>
            </div>
        </Shell>
    );
}

function Row({ label, value }) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex justify-between gap-4">
            <span className="text-slate">{label}</span>
            <span className="text-right font-medium">{value}</span>
        </div>
    );
}

function Tracker({ booking }) {
    if (['cancelled', 'refunded', 'disputed'].includes(booking.status)) {
        return (
            <div className="card p-4">
                <p className="font-display text-lg font-semibold">{STATUS_LABEL[booking.status]}</p>
                <p className="text-sm text-slate">This booking left the accept, start, complete path.</p>
            </div>
        );
    }

    const index = FLOW.indexOf(booking.status);

    return (
        <ol className="grid grid-cols-2 gap-2 md:grid-cols-5">
            {FLOW.map((step, stepIndex) => {
                const done = index > stepIndex || (step === 'completed' && booking.escrow_released_at);
                const current = step === booking.status && !booking.escrow_released_at;
                return (
                    <li key={step} className={`rounded-2xl px-3 py-3 font-display text-xs font-semibold uppercase tracking-wider ${done ? 'bg-zambezi text-white' : current ? 'bg-white text-zambezi ring-2 ring-zambezi' : 'bg-white text-slate'}`}>
                        {stepIndex + 1}. {STATUS_LABEL[step]}
                    </li>
                );
            })}
            <li className={`rounded-2xl px-3 py-3 font-display text-xs font-semibold uppercase tracking-wider ${booking.escrow_released_at ? 'bg-mint text-ink' : 'bg-white text-slate'}`}>
                5. Escrow released
            </li>
        </ol>
    );
}

function Action({ title, body, onClick, label, copper = false }) {
    return (
        <section className="card p-5">
            <h2 className="font-display text-xl font-semibold">{title}</h2>
            <p className="mt-1 text-sm text-slate">{body}</p>
            <button type="button" className={`${copper ? 'btn-copper' : 'btn-primary'} mt-4`} onClick={onClick}>{label}</button>
        </section>
    );
}

function PayForm({ booking, user, onPaid, onError }) {
    const business = user.role === 'business_client';
    const [method, setMethod] = useState(business ? 'wallet' : 'mobile_money');
    const [network, setNetwork] = useState(user.mobile_money_provider || 'mtn_momo');
    const [number, setNumber] = useState(user.mobile_money_number || '');
    const [pending, setPending] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setPending(true);

        const json = method === 'wallet'
            ? { method: 'wallet' }
            : { method: 'mobile_money', mobile_money_provider: network, mobile_money_number: number };

        try {
            await api(`/bookings/${booking.id}/pay`, { method: 'POST', json });
            await onPaid();
        } catch (reason) {
            onError(firstError(reason));
        } finally {
            setPending(false);
        }
    }

    return (
        <form onSubmit={submit} className="card space-y-4 p-5">
            <h2 className="font-display text-xl font-semibold">Pay {kwacha(booking.amount)} into escrow</h2>
            <p className="text-sm text-slate">Mock mobile money. POST /api/bookings/{booking.id}/pay holds the amount until you confirm the job.</p>
            {business ? (
                <Alert tone="ok">This account pays with method wallet. The API rejects mobile money for a business client.</Alert>
            ) : (
                <>
                    <div className="grid gap-2">
                        {MOBILE_MONEY.map((item) => (
                            <label key={item.value} className={`flex cursor-pointer items-center justify-between rounded-2xl border px-4 py-3 ${network === item.value ? 'border-zambezi bg-zambezi/5' : 'border-cloud bg-white'}`}>
                                <span className="font-display font-semibold">{item.label}</span>
                                <input type="radio" name="network" checked={network === item.value} onChange={() => setNetwork(item.value)} />
                            </label>
                        ))}
                    </div>
                    <Field label="Mobile money number">
                        <input className="field" value={number} onChange={(event) => setNumber(event.target.value)} required placeholder="0961111001" />
                    </Field>
                </>
            )}
            <button className="btn-primary w-full" disabled={pending} type="submit">{pending ? 'Paying…' : `Pay ${kwacha(booking.amount)} into escrow`}</button>
        </form>
    );
}

function ReasonForm({ title, hint, label, onSubmit }) {
    const [reason, setReason] = useState('');

    return (
        <form
            className="card space-y-3 p-5"
            onSubmit={(event) => {
                event.preventDefault();
                onSubmit(reason);
            }}
        >
            <h2 className="font-display text-xl font-semibold">{title}</h2>
            <p className="text-sm text-slate">{hint}</p>
            <textarea className="field !h-20 py-3" required minLength={3} value={reason} onChange={(event) => setReason(event.target.value)} />
            <button className="btn-ghost" type="submit">{label}</button>
        </form>
    );
}

function DisputeForm({ booking, onDone, onError }) {
    const [reason, setReason] = useState('');
    const [file, setFile] = useState(null);
    const [pending, setPending] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setPending(true);
        const form = new FormData();
        form.append('reason', reason);
        if (file) {
            form.append('evidence', file);
        }

        try {
            await api(`/bookings/${booking.id}/dispute`, { method: 'POST', form });
            await onDone();
        } catch (reasonError) {
            onError(firstError(reasonError));
        } finally {
            setPending(false);
        }
    }

    return (
        <form onSubmit={submit} className="card space-y-3 p-5">
            <h2 className="font-display text-xl font-semibold">Open a claim</h2>
            <p className="text-sm text-slate">POST /dispute with a reason and an evidence photo, inside the claim window.</p>
            <textarea className="field !h-24 py-3" required minLength={3} value={reason} onChange={(event) => setReason(event.target.value)} />
            <input type="file" accept="image/*" required onChange={(event) => setFile(event.target.files?.[0] || null)} />
            <button className="btn-copper" disabled={pending} type="submit">{pending ? 'Sending…' : 'Submit claim'}</button>
        </form>
    );
}

function RateForm({ booking, onDone, onError }) {
    const [stars, setStars] = useState(5);
    const [comment, setComment] = useState('');
    const [pending, setPending] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setPending(true);

        try {
            await api(`/bookings/${booking.id}/ratings`, { method: 'POST', json: { stars, comment: comment || null } });
            await onDone();
        } catch (reason) {
            onError(firstError(reason));
        } finally {
            setPending(false);
        }
    }

    return (
        <form onSubmit={submit} className="card space-y-3 p-5">
            <h2 className="font-display text-xl font-semibold">Rate this job</h2>
            <div className="flex gap-2">
                {[1, 2, 3, 4, 5].map((value) => (
                    <button key={value} type="button" onClick={() => setStars(value)} className={`h-10 w-10 rounded-full font-display font-semibold ${value <= stars ? 'bg-copper text-white' : 'bg-sand'}`}>{value}</button>
                ))}
            </div>
            <textarea className="field !h-20 py-3" value={comment} onChange={(event) => setComment(event.target.value)} placeholder="Fixed the leak." />
            <button className="btn-primary" disabled={pending} type="submit">{pending ? 'Saving…' : 'Submit rating'}</button>
        </form>
    );
}

function PhotoUpload({ booking, kind, onDone, onError }) {
    const [file, setFile] = useState(null);
    const [pending, setPending] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setPending(true);
        const form = new FormData();
        form.append('kind', kind);
        form.append('photo', file);

        try {
            await api(`/bookings/${booking.id}/photos`, { method: 'POST', form });
            await onDone();
        } catch (reason) {
            onError(firstError(reason));
        } finally {
            setPending(false);
        }
    }

    return (
        <form onSubmit={submit} className="card space-y-3 p-5">
            <h2 className="font-display text-xl font-semibold">{kind === 'before' ? 'Before photo' : 'After photo'}</h2>
            <p className="text-sm text-slate">Client-absent bookings need a {kind} photo. POST /photos with kind `{kind}`.</p>
            <input type="file" accept="image/*" required onChange={(event) => setFile(event.target.files?.[0] || null)} />
            <button className="btn-ghost" disabled={pending || !file} type="submit">{pending ? 'Uploading…' : 'Upload photo'}</button>
        </form>
    );
}
