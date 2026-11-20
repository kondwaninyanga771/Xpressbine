import { RequireAuth } from '../components/ui';
import { Inbox } from './Messages';

export default function Chat({ peerId }) {
    return (
        <RequireAuth>
            <Inbox activeUserId={Number(peerId)} />
        </RequireAuth>
    );
}
