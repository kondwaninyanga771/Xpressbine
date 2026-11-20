export const AREAS = [
    { name: 'Chilenje', latitude: -15.4455, longitude: 28.3345 },
    { name: 'Woodlands', latitude: -15.431, longitude: 28.331 },
    { name: 'Kabulonga', latitude: -15.4195, longitude: 28.3495 },
    { name: 'Northmead', latitude: -15.41, longitude: 28.3 },
    { name: 'Roma', latitude: -15.39, longitude: 28.32 },
    { name: 'Matero', latitude: -15.383, longitude: 28.267 },
];

export const MOBILE_MONEY = [
    { value: 'airtel_money', label: 'Airtel Money' },
    { value: 'mtn_momo', label: 'MTN MoMo' },
    { value: 'zamtel_kwacha', label: 'Zamtel Kwacha' },
];

export const WALLET_METHODS = [
    ...MOBILE_MONEY,
    { value: 'bank_transfer', label: 'Bank transfer' },
];

export const STATUS_LABEL = {
    requested: 'Requested',
    accepted: 'Accepted',
    in_progress: 'In progress',
    completed: 'Completed',
    disputed: 'Disputed',
    cancelled: 'Cancelled',
    refunded: 'Refunded',
};

export function areaByName(name) {
    return AREAS.find((area) => area.name.toLowerCase() === String(name || '').toLowerCase()) || null;
}

export function originFor(user, areaName) {
    const picked = areaByName(areaName);

    if (picked) {
        return picked;
    }

    if (user?.latitude != null && user?.longitude != null && user.latitude !== '' && user.longitude !== '') {
        return {
            name: user.area || 'You',
            latitude: Number(user.latitude),
            longitude: Number(user.longitude),
        };
    }

    return AREAS[0];
}

export function kwacha(amount) {
    if (amount == null || amount === '') {
        return 'K0';
    }

    const number = Number(amount);

    if (Number.isNaN(number)) {
        return `K${amount}`;
    }

    const digits = Number.isInteger(number) ? 0 : 2;

    return `K${number.toLocaleString('en-ZM', { minimumFractionDigits: digits, maximumFractionDigits: 2 })}`;
}

export function enumValue(value) {
    if (value && typeof value === 'object') {
        return value.value || value.name || '';
    }

    return value ?? '';
}

export function humanize(value) {
    return String(enumValue(value) || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

export function when(iso, options = {}) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);

    if (Number.isNaN(date.getTime())) {
        return String(iso);
    }

    return date.toLocaleString('en-GB', {
        timeZone: 'Africa/Lusaka',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
        ...options,
    });
}

export function dayLabel(iso) {
    if (!iso) {
        return '';
    }

    const date = new Date(iso);
    const today = new Date();
    const target = date.toLocaleDateString('en-CA', { timeZone: 'Africa/Lusaka' });
    const now = today.toLocaleDateString('en-CA', { timeZone: 'Africa/Lusaka' });
    const tomorrow = new Date(today.getTime() + 86400000).toLocaleDateString('en-CA', { timeZone: 'Africa/Lusaka' });

    if (target === now) {
        return 'Today';
    }

    if (target === tomorrow) {
        return 'Tomorrow';
    }

    return date.toLocaleDateString('en-GB', { timeZone: 'Africa/Lusaka', day: 'numeric', month: 'short' });
}

export function defaultSchedule() {
    const local = new Date(new Date().toLocaleString('en-US', { timeZone: 'Africa/Lusaka' }));
    local.setDate(local.getDate() + 1);
    const month = String(local.getMonth() + 1).padStart(2, '0');
    const day = String(local.getDate()).padStart(2, '0');

    return `${local.getFullYear()}-${month}-${day}T09:00`;
}

export function toApiDate(value) {
    if (!value) {
        return value;
    }

    if (value.length === 16) {
        return `${value}:00+02:00`;
    }

    return value;
}

function haversine(lat1, lng1, lat2, lng2) {
    const toRad = (degrees) => (degrees * Math.PI) / 180;
    const earth = 6371;
    const dLat = toRad(lat2 - lat1);
    const dLng = toRad(lng2 - lng1);
    const a = Math.sin(dLat / 2) ** 2
        + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;

    return earth * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function kilometres(provider, origin) {
    if (provider?.distance_km != null && provider.distance_km !== '') {
        const fromApi = Number(provider.distance_km);

        if (!Number.isNaN(fromApi)) {
            return fromApi;
        }
    }

    const lat = Number(provider?.user?.latitude);
    const lng = Number(provider?.user?.longitude);

    if (!origin || Number.isNaN(lat) || Number.isNaN(lng)) {
        return null;
    }

    return haversine(Number(origin.latitude), Number(origin.longitude), lat, lng);
}

export function leadPackage(provider) {
    const packages = [...(provider?.packages || [])].filter((item) => item.is_active !== false);

    if (!packages.length) {
        return null;
    }

    return packages.sort((a, b) => Number(a.price) - Number(b.price))[0];
}

export function matchesQuery(provider, query) {
    const needle = query.trim().toLowerCase();

    if (!needle) {
        return true;
    }

    const haystack = [
        provider.user?.name,
        provider.user?.area,
        provider.bio,
        ...(provider.categories || []).flatMap((category) => [category.name, category.slug]),
        ...(provider.packages || []).flatMap((item) => [item.title, item.description]),
    ].filter(Boolean).join(' ').toLowerCase();

    return haystack.includes(needle);
}

export function canBook(user) {
    return user?.role === 'customer' || user?.role === 'business_client';
}

export function funded(booking) {
    return Number(booking?.escrow_balance || 0) > 0 || Boolean(booking?.escrow_released_at);
}

export function initials(name) {
    return String(name || '?')
        .split(' ')
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
}

const WASHES = {
    plumbing: 'from-[#0E1B2C] via-[#16384a] to-[#0B6B4F]',
    electrical: 'from-[#0E1B2C] via-[#3a2618] to-[#E8762D]',
    cleaning: 'from-[#0E1B2C] via-[#1c3a34] to-[#2FD39A]',
    'pool-cleaning': 'from-[#0E1B2C] via-[#123044] to-[#1d6b8a]',
    carpentry: 'from-[#0E1B2C] via-[#3a2a1c] to-[#8a5a32]',
    'event-planning': 'from-[#0E1B2C] via-[#3a2030] to-[#E8762D]',
    'student-help': 'from-[#0E1B2C] via-[#1d3148] to-[#0B6B4F]',
};

export function wash(slug) {
    return WASHES[slug] || 'from-[#0E1B2C] via-[#1a2c3d] to-[#0B6B4F]';
}
