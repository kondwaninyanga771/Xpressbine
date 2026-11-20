import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, listOf } from '../api';
import { Alert, Empty, RequireAuth, Shell } from '../components/ui';
import { when } from '../format';

export default function Notifications() {
    return (
        <RequireAuth>
            <NotificationsScreen />
        </RequireAuth>
    );
}

function NotificationsScreen() {
    const [rows, setRows] = useState([]);
    const [error, setError] = useState('');

    function load() {
        api('/notifications').then((payload) => setRows(listOf(payload))).catch((reason) => setError(reason.message));
    }

    useEffect(() => {
        load();
    }, []);

    async function markRead(id) {
        await api(`/notifications/${id}/read`, { method: 'POST' });
        load();
    }

    return (
        <Shell>
            <Head title="Alerts" />
            <div className="mx-auto max-w-3xl space-y-3 px-4 py-6 md:px-8">
                <h1 className="font-display text-3xl font-semibold">Alerts</h1>
                {error && <Alert>{error}</Alert>}
                {rows.map((item) => (
                    <article key={item.id} className="card flex items-start justify-between gap-3 p-4">
                        <div>
                            <h2 className="font-display font-semibold">{item.title}</h2>
                            <p className="mt-1 text-sm text-slate">{item.body}</p>
                            <p className="mt-1 text-xs text-slate">{when(item.created_at)}{item.read_at ? ' · read' : ''}</p>
                        </div>
                        {!item.read_at && <button type="button" className="btn-ghost !px-3 !py-1.5" onClick={() => markRead(item.id)}>Mark read</button>}
                    </article>
                ))}
                {!rows.length && !error && <Empty>No alerts yet. Booking updates are saved here.</Empty>}
            </div>
        </Shell>
    );
}
