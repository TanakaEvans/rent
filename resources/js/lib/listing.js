export const TYPE_LABELS = {
    room: 'Room',
    flat: 'Flat',
    apartment: 'Apartment',
    house: 'Full house',
    townhouse: 'Townhouse',
    cottage: 'Cottage',
    commercial: 'Commercial',
    land: 'Land',
};

export const CURRENCIES = ['USD', 'ZWL'];

export const PAYMENT_TERM_LABELS = {
    monthly: 'Monthly',
    quarterly: 'Quarterly',
    yearly: 'Yearly',
};

export const PARKING_TYPES = {
    none: 'No parking',
    street: 'Street parking',
    secure: 'Secure parking',
};

export const SECURITY_TYPES = {
    gated: 'Gated complex',
    fenced: 'Fenced',
    none: 'None',
};

export const PREFERRED_TENANTS = {
    any: 'Anyone',
    family: 'Families',
    single: 'Singles',
    professionals: 'Professionals',
    students: 'Students',
};

export const LANDLORD_TYPES = {
    direct: 'Direct owner',
    agent: 'Agent-managed',
    corporate: 'Corporate',
};

export const CONTACT_PREFERENCES = {
    platform: 'Via ZimRent',
    whatsapp: 'WhatsApp',
    call: 'Phone',
    email: 'Email',
};

export const ENTRANCE_TYPES = {
    own: 'Own entrance',
    shared: 'Shared entrance',
};

export const BATHROOM_TYPES = {
    own: 'Own bathroom',
    shared: 'Shared bathroom',
};

export const typeLabel = (key) => TYPE_LABELS[key] || key || 'Property';

export const currencySymbol = (currency) => (currency === 'ZWL' ? 'ZWL ' : '$');

export const formatPrice = (value, currency = 'USD') => {
    const amount = Number(value);
    if (!Number.isFinite(amount)) return '—';
    return currencySymbol(currency) + amount.toLocaleString(undefined, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    });
};

export const priceSuffix = (terms) => ({
    monthly: '/month',
    quarterly: '/quarter',
    yearly: '/year',
}[terms] || '/month');

export const paymentPeriod = (terms) => ({
    monthly: 'per month',
    quarterly: 'per quarter',
    yearly: 'per year',
}[terms] || 'per month');

export const yesNoOrDash = (value) => {
    if (value === null || value === undefined) return '—';
    return value ? 'Yes' : 'No';
};

export const optionLabel = (map, key) => (key == null || key === '' ? null : map[key] || null);

export const BADGE_TIERS = {
    none: null,
    bronze: 'Bronze owner',
    silver: 'Silver owner',
    gold: 'Gold owner',
};

export const badgeTierLabel = (tier) => (tier == null ? null : BADGE_TIERS[tier] || null);