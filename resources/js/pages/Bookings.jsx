import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, listOf } from '../api';
import { useAuth } from '../auth';
import { Alert, Empty, RequireAuth, Shell } from '../components/ui';
import { dayLabel, kwacha, STATUS_LABEL, when } from '../format';

export default function Bookings() {
    return (
        <RequireAuth>
            <BookingsScreen />
        </RequireAuth>
    );
}

function BookingsScreen() {
    const { user } = useAuth();
    const [bookings, setBookings] = useState([]);
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        api('/bookings')
            .then((payload) => setBookings(listOf(payload)))
            .catch((reason) => setError(reason.message))
            .finally(() => setLoading(false));
    }, []);

    return (
        <Shell>
            <Head title="Bookings" />
            <div className="mx-auto max-w-3xl px-4 py-6 md:px-8">
                <h1 className="font-display text-3xl font-semibold">Bookings</h1>
                <p className="mt-1 text-sm text-slate">GET /api/bookings. Customers see their own. Providers see jobs assigned to them.</p>
                {error && <div className="mt-4"><Alert>{error}</Alert></div>}
                {loading && <p className="mt-6 text-sm text-slate">Loading bookings…</p>}
                <div className="mt-5 space-y-3">
                    {bookings.map((booking) => {
                        const mine = booking.customer_id === user.id;
                        const other = mine ? booking.provider?.user?.name : booking.customer?.name;
                        return (
                            <Link key={booking.id} href={`/bookings/${booking.id}`} className="card block p-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <p className="font-display text-xs font-bold uppercase tracking-[0.08em] text-zambezi">{booking.reference}</p>
                                        <h2 className="mt-1 font-display text-xl font-semibold">{booking.package?.title || booking.category?.name || 'Booking'}</h2>
                                        <p className="mt-1 text-sm text-slate">{other} · {booking.area} · {when(booking.scheduled_at)}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-display font-semibold">{kwacha(booking.amount)}</p>
                                        <span className="pill mt-2 bg-sand text-ink">{STATUS_LABEL[booking.status] || booking.status}</span>
                                    </div>
                                </div>
                                <p className="mt-3 text-xs text-slate">{dayLabel(booking.scheduled_at)} · escrow {kwacha(booking.escrow_balance)}</p>
                            </Link>
                        );
                    })}
                </div>
                {!loading && !bookings.length && <div className="mt-4"><Empty>No bookings yet.</Empty></div>}
            </div>
        </Shell>
    );
}
