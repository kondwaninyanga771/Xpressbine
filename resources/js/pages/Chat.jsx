import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, listOf } from '../api';
import { useAuth } from '../auth';
import { Alert, RequireAuth, Shell } from '../components/ui';
import { kwacha, STATUS_LABEL, when } from '../format';
import { peersFrom } from './Messages';

export default function Chat({ peerId }) {
    return (
        <RequireAuth>
            <ChatScreen peerId={peerId} />
        </RequireAuth>
    );
}

function storageKey(userId, peerId) {
    return `xpressbine.chat.${userId}.${peerId}`;
}

function ChatScreen({ peerId }) {
    const { user } = useAuth();
    const [peer, setPeer] = useState(null);
    const [booking, setBooking] = useState(null);
    const [draft, setDraft] = useState('');
    const [messages, setMessages] = useState([]);

    useEffect(() => {
        const saved = localStorage.getItem(storageKey(user.id, peerId));
        setMessages(saved ? JSON.parse(saved) : []);

        api('/bookings').then(async (payload) => {
            const bookings = listOf(payload);
            const known = peersFrom(bookings, user).find((item) => item.id === peerId);

            if (known) {
                setPeer({ id: known.id, name: known.name, area: known.area });
                setBooking(known.booking);
                return;
            }

            const providers = listOf(await api('/providers?per_page=50'));
            const match = providers.find((provider) => provider.user?.id === peerId);

            if (match?.user) {
                setPeer({ id: match.user.id, name: match.user.name, area: match.user.area });
            } else {
                setPeer({ id: peerId, name: `Person ${peerId}`, area: null });
            }
        }).catch(() => setPeer({ id: peerId, name: `Person ${peerId}`, area: null }));
    }, [peerId, user.id]);

    function send(event) {
        event.preventDefault();
        const body = draft.trim();

        if (!body) {
            return;
        }

        const next = [...messages, { id: Date.now(), body, mine: true, at: new Date().toISOString() }];
        setMessages(next);
        localStorage.setItem(storageKey(user.id, peerId), JSON.stringify(next));
        setDraft('');
    }

    return (
        <div className="min-h-screen bg-sand">
            <Head title={peer?.name || 'Chat'} />
            <header className="sticky top-0 z-10 flex items-center gap-3 border-b border-cloud bg-sand/95 px-4 py-3">
                <Link href="/messages" className="font-display text-sm font-semibold text-zambezi">Back</Link>
                <div className="min-w-0">
                    <p className="truncate font-display font-semibold">{peer?.name || '…'}</p>
                    <p className="truncate text-xs text-slate">{peer?.area || 'Preview chat'}</p>
                </div>
                {booking && <Link href={`/bookings/${booking.id}`} className="ml-auto font-display text-xs font-semibold text-zambezi">Booking</Link>}
            </header>
            <div className="mx-auto flex max-w-2xl flex-col gap-3 px-4 py-4 pb-28">
                <Alert tone="stub">
                    This thread is not saved on the server. The API has no messages resource. Text below stays in this browser.
                </Alert>
                {booking && (
                    <Link href={`/bookings/${booking.id}`} className="card block p-4">
                        <p className="font-display text-[11px] font-bold uppercase tracking-[0.12em] text-zambezi">{booking.reference}</p>
                        <p className="mt-1 font-display text-lg font-semibold">{booking.package?.title || 'Booking'}</p>
                        <p className="mt-1 text-sm text-slate">{STATUS_LABEL[booking.status] || booking.status} · {kwacha(booking.amount)} · escrow {kwacha(booking.escrow_balance)}</p>
                        <p className="mt-1 text-sm text-slate">{when(booking.scheduled_at)} · {booking.area}</p>
                    </Link>
                )}
                {messages.map((message) => (
                    <div key={message.id} className={`max-w-[80%] rounded-3xl px-4 py-3 text-sm ${message.mine ? 'ml-auto bg-ink text-white' : 'bg-white'}`}>
                        <p>{message.body}</p>
                        <p className={`mt-1 text-[11px] ${message.mine ? 'text-white/60' : 'text-slate'}`}>{when(message.at)} · this browser</p>
                    </div>
                ))}
                {!messages.length && <p className="text-center text-sm text-slate">No preview messages yet.</p>}
            </div>
            <form onSubmit={send} className="fixed inset-x-0 bottom-0 border-t border-cloud bg-sand px-4 py-3">
                <div className="mx-auto flex max-w-2xl items-center gap-2">
                    <input value={draft} onChange={(event) => setDraft(event.target.value)} placeholder="Type a preview message…" className="field" />
                    <button className="btn-primary !px-4" type="submit">Send</button>
                </div>
            </form>
        </div>
    );
}
