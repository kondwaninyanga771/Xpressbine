import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { api, fieldError, firstError, listOf } from '../api';
import { resumeAfterAuth, useAuth } from '../auth';
import { Alert, Field, Logo } from '../components/ui';
import { AREAS, MOBILE_MONEY } from '../format';

const ROLES = [
    { value: 'customer', label: 'Customer' },
    { value: 'provider', label: 'Provider' },
    { value: 'business_client', label: 'Business' },
];

export default function Register() {
    const { register } = useAuth();
    const [categories, setCategories] = useState([]);
    const [form, setForm] = useState({
        name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        role: 'customer',
        area: 'Chilenje',
        business_name: '',
        bio: '',
        nrc_number: '',
        hourly_rate: '',
        mobile_money_provider: '',
        mobile_money_number: '',
        category_ids: [],
    });
    const [error, setError] = useState(null);
    const [pending, setPending] = useState(false);

    useEffect(() => {
        api('/categories').then((payload) => setCategories(listOf(payload))).catch(() => {});
    }, []);

    function set(key, value) {
        setForm((current) => ({ ...current, [key]: value }));
    }

    function toggleCategory(id) {
        setForm((current) => {
            const has = current.category_ids.includes(id);
            return {
                ...current,
                category_ids: has ? current.category_ids.filter((item) => item !== id) : [...current.category_ids, id],
            };
        });
    }

    async function submit(event) {
        event.preventDefault();
        setPending(true);
        setError(null);
        const area = AREAS.find((item) => item.name === form.area);

        const json = {
            name: form.name,
            email: form.email,
            phone: form.phone,
            password: form.password,
            password_confirmation: form.password_confirmation,
            role: form.role,
            area: form.area || null,
            latitude: area?.latitude,
            longitude: area?.longitude,
        };

        if (form.mobile_money_provider) {
            json.mobile_money_provider = form.mobile_money_provider;
            json.mobile_money_number = form.mobile_money_number || null;
        }

        if (form.role === 'provider') {
            json.bio = form.bio || null;
            json.nrc_number = form.nrc_number || null;
            if (form.hourly_rate !== '') {
                json.hourly_rate = Number(form.hourly_rate);
            }
            if (form.category_ids.length) {
                json.category_ids = form.category_ids;
            }
        }

        if (form.role === 'business_client') {
            json.business_name = form.business_name;
        }

        try {
            const user = await register(json);
            resumeAfterAuth(user);
        } catch (reason) {
            setError(reason);
            setPending(false);
        }
    }

    return (
        <div className="min-h-screen bg-sand px-4 py-10">
            <Head title="Create account" />
            <div className="mx-auto w-full max-w-lg">
                <Link href="/"><Logo /></Link>
                <h1 className="mt-8 font-display text-4xl font-semibold">Create an account</h1>
                <p className="mt-2 text-sm text-slate">POST /api/register. Admin accounts are not open for registration.</p>
                <form onSubmit={submit} className="card mt-6 space-y-4 p-5">
                    {error && <Alert>{firstError(error)}</Alert>}
                    <div className="flex flex-wrap gap-2">
                        {ROLES.map((role) => (
                            <button key={role.value} type="button" onClick={() => set('role', role.value)} className={`pill border ${form.role === role.value ? 'border-zambezi bg-zambezi text-white' : 'border-cloud bg-sand'}`}>
                                {role.label}
                            </button>
                        ))}
                    </div>
                    <Field label="Name" error={fieldError(error, 'name')}>
                        <input className="field" value={form.name} onChange={(event) => set('name', event.target.value)} required />
                    </Field>
                    <Field label="Email" error={fieldError(error, 'email')}>
                        <input type="email" className="field" value={form.email} onChange={(event) => set('email', event.target.value)} required />
                    </Field>
                    <Field label="Phone" error={fieldError(error, 'phone')}>
                        <input className="field" value={form.phone} onChange={(event) => set('phone', event.target.value)} required placeholder="0960000099" />
                    </Field>
                    <Field label="Area" error={fieldError(error, 'area')}>
                        <input className="field" list="areas" value={form.area} onChange={(event) => set('area', event.target.value)} />
                        <datalist id="areas">{AREAS.map((area) => <option key={area.name} value={area.name} />)}</datalist>
                    </Field>
                    <div className="grid gap-3 md:grid-cols-2">
                        <Field label="Password" error={fieldError(error, 'password')}>
                            <input type="password" className="field" value={form.password} onChange={(event) => set('password', event.target.value)} required minLength={8} />
                        </Field>
                        <Field label="Confirm password" error={fieldError(error, 'password_confirmation')}>
                            <input type="password" className="field" value={form.password_confirmation} onChange={(event) => set('password_confirmation', event.target.value)} required />
                        </Field>
                    </div>
                    {form.role === 'business_client' && (
                        <Field label="Business name" error={fieldError(error, 'business_name')}>
                            <input className="field" value={form.business_name} onChange={(event) => set('business_name', event.target.value)} required />
                        </Field>
                    )}
                    {form.role === 'provider' && (
                        <>
                            <Field label="Bio" error={fieldError(error, 'bio')}>
                                <textarea className="field !h-24 py-3" value={form.bio} onChange={(event) => set('bio', event.target.value)} />
                            </Field>
                            <Field label="NRC number" error={fieldError(error, 'nrc_number')}>
                                <input className="field" value={form.nrc_number} onChange={(event) => set('nrc_number', event.target.value)} />
                            </Field>
                            <Field label="Hourly rate (K)" error={fieldError(error, 'hourly_rate')}>
                                <input type="number" min="0" className="field" value={form.hourly_rate} onChange={(event) => set('hourly_rate', event.target.value)} />
                            </Field>
                            <fieldset>
                                <legend className="mb-2 font-display text-xs font-semibold uppercase tracking-[0.04em] text-slate">Trades</legend>
                                <div className="flex flex-wrap gap-2">
                                    {categories.map((category) => (
                                        <label key={category.id} className={`pill cursor-pointer border ${form.category_ids.includes(category.id) ? 'border-zambezi bg-zambezi text-white' : 'border-cloud bg-white'}`}>
                                            <input type="checkbox" className="sr-only" checked={form.category_ids.includes(category.id)} onChange={() => toggleCategory(category.id)} />
                                            {category.name}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                            <div className="grid gap-3 md:grid-cols-2">
                                <Field label="Payout network" error={fieldError(error, 'mobile_money_provider')}>
                                    <select className="field" value={form.mobile_money_provider} onChange={(event) => set('mobile_money_provider', event.target.value)}>
                                        <option value="">Later</option>
                                        {MOBILE_MONEY.map((item) => <option key={item.value} value={item.value}>{item.label}</option>)}
                                    </select>
                                </Field>
                                <Field label="Payout number" error={fieldError(error, 'mobile_money_number')}>
                                    <input className="field" value={form.mobile_money_number} onChange={(event) => set('mobile_money_number', event.target.value)} />
                                </Field>
                            </div>
                        </>
                    )}
                    <button className="btn-primary w-full" disabled={pending} type="submit">{pending ? 'Creating…' : 'Create account'}</button>
                </form>
                <p className="mt-6 text-sm text-slate">
                    Already registered? <Link href="/login" className="font-semibold text-zambezi">Log in</Link>
                </p>
            </div>
        </div>
    );
}
