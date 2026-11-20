import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, firstError, recordOf } from '../api';
import { Alert } from './ui';
import { kwacha, STATUS_LABEL, when } from '../format';

export function ChatPane({ peerId, user }) {
    const [conversation, setConversation] = useState(null);
    const [messages, setMessages] = useState([]);
    const [booking, setBooking] = useState(null);
    const [draft, setDraft] = useState('');
    const [error, setError] = useState('');

    async function load(conversationId) {
        const payload = await api(`/conversations/${conversationId}/messages`);
        setConversation(payload.data.conversation);
        setMessages(payload.data.messages || []);
    }

    useEffect(() => {
        let stop = false;
        let conversationId = null;

        async function open() {
            try {
                const opened = await api('/conversations', { method: 'POST', json: { user_id: Number(peerId) } });
                conversationId = recordOf(opened).id;
                if (!stop) {
                    await load(conversationId);
                }
                const bookings = await api('/bookings');
                const match = (bookings.data || []).find((item) => item.customer_id === Number(peerId) || item.provider?.user?.id === Number(peerId));
                if (!stop) {
                    setBooking(match || null);
                }
            } catch (reason) {
                if (!stop) {
                    setError(firstError(reason));
                }
            }
        }

        open();
        const timer = setInterval(() => {
            if (conversationId) {
                load(conversationId).catch(() => {});
            }
        }, 5000);

        return () => {
            stop = true;
            clearInterval(timer);
        };
    }, [peerId]);

    async function send(event) {
        event.preventDefault();
        const body = draft.trim();

        if (!body || !conversation) {
            return;
        }

        setDraft('');

        try {
            await api(`/conversations/${conversation.id}/messages`, { method: 'POST', json: { body } });
            await load(conversation.id);
        } catch (reason) {
            setError(firstError(reason));
        }
    }

    const other = conversation?.other_user;

    return (
        <div className="flex h-full min-h-[70vh] flex-col">
            <header className="flex items-center gap-3 border-b border-cloud px-4 py-3">
                <Link href="/messages" className="font-display text-sm font-semibold text-zambezi md:hidden">Back</Link>
                <div className="min-w-0">
                    <p className="truncate font-display font-semibold">{other?.name || '…'}</p>
                    <p className="truncate text-xs text-slate">{other?.area || other?.role || ''}</p>
                </div>
                {booking && <Link href={`/bookings/${booking.id}`} className="ml-auto font-display text-xs font-semibold text-zambezi">Booking</Link>}
            </header>
            <div className="flex flex-1 flex-col gap-3 overflow-y-auto px-4 py-4">
                {error && <Alert>{error}</Alert>}
                {booking && (
                    <Link href={`/bookings/${booking.id}`} className="card block p-4 transition hover:border-zambezi/40">
                        <p className="font-display text-[11px] font-bold uppercase tracking-[0.12em] text-zambezi">{booking.reference}</p>
                        <p className="mt-1 font-display text-lg font-semibold">{booking.package?.title || 'Booking'}</p>
                        <p className="mt-1 text-sm text-slate">{STATUS_LABEL[booking.status] || booking.status} · {kwacha(booking.amount)} · escrow {kwacha(booking.escrow_balance)}</p>
                        <p className="mt-1 text-sm text-slate">{when(booking.scheduled_at)} · {booking.area}</p>
                    </Link>
                )}
                {messages.map((message) => {
                    const mine = message.sender_id === user?.id;
                    return (
                        <div key={message.id} className={`max-w-[80%] rounded-3xl px-4 py-3 text-sm ${mine ? 'ml-auto bg-ink text-white' : 'bg-white'}`}>
                            <p>{message.body}</p>
                            <p className={`mt-1 text-[11px] ${mine ? 'text-white/60' : 'text-slate'}`}>{when(message.created_at)}</p>
                        </div>
                    );
                })}
                {!messages.length && !error && <p className="text-center text-sm text-slate">No messages yet. What you send is saved for both people.</p>}
            </div>
            <form onSubmit={send} className="border-t border-cloud px-4 py-3">
                <div className="flex items-center gap-2">
                    <input value={draft} onChange={(event) => setDraft(event.target.value)} placeholder="Type a message..." className="field" />
                    <button className="btn-primary !px-4" type="submit">Send</button>
                </div>
            </form>
        </div>
    );
}
