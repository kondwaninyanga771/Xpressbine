import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { fieldError, firstError } from '../api';
import { resumeAfterAuth, useAuth } from '../auth';
import { Alert, Field, Logo } from '../components/ui';

const DEMOS = [
    { name: 'Natasha Mwila', email: 'natasha.mwila@example.com', role: 'Customer' },
    { name: 'Grace Tembo', email: 'grace.tembo@example.com', role: 'Plumber' },
    { name: 'Mutale Zulu', email: 'mutale.zulu@example.com', role: 'Admin' },
    { name: 'Chanda Phiri', email: 'chanda.phiri@example.com', role: 'Business' },
];

export default function Login() {
    const { login } = useAuth();
    const [email, setEmail] = useState('natasha.mwila@example.com');
    const [password, setPassword] = useState('password');
    const [error, setError] = useState(null);
    const [pending, setPending] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setPending(true);
        setError(null);

        try {
            const user = await login(email, password);
            resumeAfterAuth(user);
        } catch (reason) {
            setError(reason);
            setPending(false);
        }
    }

    return (
        <div className="min-h-screen bg-sand px-4 py-10">
            <Head title="Log in" />
            <div className="mx-auto w-full max-w-md">
                <Link href="/"><Logo /></Link>
                <h1 className="mt-8 font-display text-4xl font-semibold">Log in</h1>
                <p className="mt-2 text-sm text-slate">Uses POST /api/login. The seeded password is password.</p>
                <form onSubmit={submit} className="card mt-6 space-y-4 p-5">
                    {error && <Alert>{firstError(error)}</Alert>}
                    <Field label="Email" error={fieldError(error, 'email')}>
                        <input type="email" className="field" value={email} onChange={(event) => setEmail(event.target.value)} required autoComplete="username" />
                    </Field>
                    <Field label="Password" error={fieldError(error, 'password')}>
                        <input type="password" className="field" value={password} onChange={(event) => setPassword(event.target.value)} required autoComplete="current-password" />
                    </Field>
                    <button className="btn-primary w-full" disabled={pending} type="submit">{pending ? 'Signing in…' : 'Log in'}</button>
                </form>
                <div className="mt-4 flex flex-wrap gap-2">
                    {DEMOS.map((demo) => (
                        <button key={demo.email} type="button" className="pill border border-cloud bg-white" onClick={() => { setEmail(demo.email); setPassword('password'); }}>
                            {demo.name} · {demo.role}
                        </button>
                    ))}
                </div>
                <p className="mt-6 text-sm text-slate">
                    New here? <Link href="/register" className="font-semibold text-zambezi">Create an account</Link>
                </p>
            </div>
        </div>
    );
}
