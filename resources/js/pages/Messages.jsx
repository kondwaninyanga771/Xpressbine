import { Head, Link } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { api, listOf } from '../api';
import { useAuth } from '../auth';
import { Alert, Avatar, Empty, RequireAuth, Shell } from '../components/ui';
import { dayLabel, kwacha, STATUS_LABEL, when } from '../format';

export default function Messages() {
    return (
        <RequireAuth>
            <MessagesScreen />
        </RequireAuth>
    );
}

function MessagesScreen() {
    const { user } = useAuth();
    const [bookings, setBookings] = useState([]);
    const [text, setText] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        api('/bookings').then((payload) => setBookings(listOf(payload))).catch((reason) => setError(reason.message));
    }, []);

    const peers = useMemo(() => peersFrom(bookings, user), [bookings, user]);
    const visible = peers.filter((peer) => `${peer.name} ${peer.area || ''} ${peer.booking.package?.title || ''}`.toLowerCase().includes(text.toLowerCase()));
    const upcoming = [...bookings]
        .filter((booking) => ['requested', 'accepted', 'in_progress'].includes(booking.status))
        .sort((a, b) => new Date(a.scheduled_at) - new Date(b.scheduled_at))[0];

    return (
        <Shell>
            <Head title="Messages" />
            <div className="mx-auto max-w-3xl px-4 py-4 md:px-8">
                <div className="flex items-center justify-between">
                    <h1 className="font-display text-3xl font-semibold">Messages</h1>
                </div>
                <Alert tone="stub">
                    There is no chat API. This list is your real bookings. Opening someone starts a preview that stays in this browser only.
                </Alert>
                <input value={text} onChange={(event) => setText(event.target.value)} placeholder="Filter people from your bookings" className="field mt-4 !rounded-full" />
                {error && <div className="mt-4"><Alert>{error}</Alert></div>}
                <div className="mt-5 flex gap-4 overflow-x-auto pb-2">
                    {visible.map((peer) => (
                        <Link key={peer.id} href={`/messages/${peer.id}`} className="flex w-16 shrink-0 flex-col items-center gap-1 text-center">
                            <Avatar name={peer.name} />
                            <span className="truncate text-xs text-slate">{peer.name.split(' ')[0]}</span>
                        </Link>
                    ))}
                </div>
                {upcoming && (
                    <Link href={`/bookings/${upcoming.id}`} className="mt-4 flex items-center justify-between gap-3 rounded-[24px] bg-[#e7f6ef] p-4">
                        <span>
                            <span className="flex items-center gap-2 font-display text-sm font-semibold text-zambezi">
                                Next visit
                                <span className="pill bg-copper text-white">{dayLabel(upcoming.scheduled_at)}</span>
                            </span>
                            <span className="mt-1 block text-sm">{upcoming.package?.title || upcoming.category?.name} · {when(upcoming.scheduled_at)}</span>
                        </span>
                        <span className="font-display text-sm font-semibold text-zambezi">View</span>
                    </Link>
                )}
                <div className="mt-6 flex items-center justify-between">
                    <h2 className="font-display text-xl font-semibold">From your bookings</h2>
                </div>
                <div className="mt-3 space-y-3">
                    {visible.map((peer) => (
                        <Link key={peer.id} href={`/messages/${peer.id}`} className="card flex items-center gap-3 p-4">
                            <Avatar name={peer.name} />
                            <span className="min-w-0 flex-1">
                                <span className="flex items-baseline justify-between gap-2">
                                    <span className="font-display font-semibold">{peer.name}</span>
                                    <span className="text-xs text-slate">{when(peer.booking.scheduled_at)}</span>
                                </span>
                                <span className="mt-1 block truncate text-sm text-slate">
                                    {peer.booking.package?.title || 'Booking'} · {STATUS_LABEL[peer.booking.status] || peer.booking.status} · {kwacha(peer.booking.amount)}
                                </span>
                            </span>
                        </Link>
                    ))}
                </div>
                {!visible.length && <div className="mt-4"><Empty>No one to message until you share a booking. Chat is not stored on the server.</Empty></div>}
            </div>
        </Shell>
    );
}

export function peersFrom(bookings, user) {
    const map = new Map();

    bookings.forEach((booking) => {
        const customer = booking.customer_id === user?.id;
        const peer = customer ? booking.provider?.user : booking.customer;
        const id = peer?.id;

        if (!id || map.has(id)) {
            return;
        }

        map.set(id, {
            id,
            name: peer.name || 'Person',
            area: peer.area,
            booking,
        });
    });

    return [...map.values()];
}
