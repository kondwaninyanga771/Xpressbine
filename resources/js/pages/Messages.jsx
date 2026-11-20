import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, listOf } from '../api';
import { useAuth } from '../auth';
import { Alert, Avatar, Empty, RequireAuth, Shell } from '../components/ui';
import { when } from '../format';
import { ChatPane } from '../components/ChatPane';

export default function Messages() {
    return (
        <RequireAuth>
            <Inbox />
        </RequireAuth>
    );
}

export function Inbox({ activeUserId = null }) {
    const { user } = useAuth();
    const { url } = usePage();
    const [conversations, setConversations] = useState([]);
    const [text, setText] = useState('');
    const [error, setError] = useState('');

    useEffect(() => {
        api('/conversations').then((payload) => setConversations(listOf(payload))).catch((reason) => setError(reason.message));
    }, [url]);

    const visible = conversations.filter((conversation) => (conversation.other_user?.name || '').toLowerCase().includes(text.toLowerCase()));

    return (
        <Shell>
            <Head title="Messages" />
            <div className="mx-auto grid max-w-[1200px] md:min-h-[70vh] md:grid-cols-[320px_minmax(0,1fr)]">
                <section className={`${activeUserId ? 'hidden md:block' : ''} border-cloud px-4 py-4 md:border-r`}>
                    <h1 className="font-display text-3xl font-semibold">Messages</h1>
                    <input value={text} onChange={(event) => setText(event.target.value)} placeholder="Filter conversations" className="field mt-4 !rounded-full" />
                    {error && <div className="mt-4"><Alert>{error}</Alert></div>}
                    <div className="mt-4 space-y-2">
                        {visible.map((conversation) => {
                            const other = conversation.other_user;
                            const active = other?.id === activeUserId;
                            return (
                                <Link key={conversation.id} href={`/messages/${other?.id}`} className={`flex items-center gap-3 rounded-2xl border px-3 py-3 transition hover:border-zambezi/40 ${active ? 'border-zambezi bg-white' : 'border-transparent bg-white/70'}`}>
                                    <Avatar name={other?.name} />
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-baseline justify-between gap-2">
                                            <span className="font-display font-semibold">{other?.name}</span>
                                            <span className="text-xs text-slate">{when(conversation.last_message?.created_at)}</span>
                                        </span>
                                        <span className="mt-1 block truncate text-sm text-slate">{conversation.last_message?.body || 'No messages yet'}</span>
                                    </span>
                                    {conversation.unread > 0 && <span className="rounded-full bg-copper px-2 py-0.5 font-display text-[11px] font-bold text-white">{conversation.unread}</span>}
                                </Link>
                            );
                        })}
                    </div>
                    {!visible.length && !error && <div className="mt-4"><Empty>No conversations yet. Open a provider and choose Message.</Empty></div>}
                </section>
                <section className={`${activeUserId ? '' : 'hidden md:flex'} min-h-[70vh] flex-col`}>
                    {activeUserId ? (
                        <ChatPane peerId={activeUserId} user={user} />
                    ) : (
                        <div className="m-auto max-w-sm px-6 text-center text-sm text-slate">Choose a conversation. Messages are stored for both people.</div>
                    )}
                </section>
            </div>
        </Shell>
    );
}
