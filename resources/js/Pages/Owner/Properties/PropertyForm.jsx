import { useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import { AlertCircle, ChevronDown, Save } from 'lucide-react';
import { Button, buttonVariants } from '@/Components/ui/button';
import PhotoUploader from '@/Components/Shared/PhotoUploader';
import LocationPicker from '@/Components/Shared/LocationPicker';
import { cn } from '@/lib/utils';
import {
    CURRENCIES,
    PAYMENT_TERM_LABELS,
    PARKING_TYPES,
    SECURITY_TYPES,
    PREFERRED_TENANTS,
    LANDLORD_TYPES,
    CONTACT_PREFERENCES,
    ENTRANCE_TYPES,
    BATHROOM_TYPES,
} from '@/lib/listing';

// Collapsible sections of the listing form. Basics is open by default; a
// validation error inside any section forces that section open (see below).
const SECTIONS = [
    { id: 'basics', label: 'Basics', hint: 'What the property is and what tenants will call it.' },
    { id: 'pricing', label: 'Pricing', hint: 'Rent, deposit and the running costs tenants should budget for.' },
    { id: 'details', label: 'Rental details', hint: 'The ground rules and who the home suits best.' },
    { id: 'location', label: 'Location', hint: 'Where the property is — pin it on the map so tenants can find it.' },
    { id: 'amenities', label: 'Amenities', hint: 'The features and extras that help your listing stand out.' },
    { id: 'photos', label: 'Photos', hint: 'At least one photo is required. Tenants browse by photo first.' },
];

// Which section owns each field, so a failed submit expands the right section.
// `latitude`/`longitude` sit under Location alongside the map picker, so their
// server-side errors surface in the right place.
const SECTION_FIELDS = {
    basics: ['title', 'description', 'property_type', 'status', 'bedrooms', 'bathrooms', 'building_size', 'land_size', 'floor_area', 'year_built', 'furnished'],
    pricing: ['currency', 'payment_terms', 'price', 'deposit', 'water_cost', 'electricity_cost', 'trash_cost', 'negotiable'],
    details: [
        'entrance_type', 'bathroom_type', 'parking_type', 'security_type', 'distance_to_cbd', 'minimum_stay', 'preferred_tenant',
        'landlord_type', 'contact_preference', 'landmark', 'children_allowed', 'pets_allowed', 'smoking_allowed', 'parties_allowed',
        'show_phone', 'families_allowed',
    ],
    location: ['suburb', 'zone', 'city', 'available_from', 'address', 'latitude', 'longitude'],
    amenities: ['amenities'],
    photos: ['media', 'cover', 'cover_image', 'images'],
};

const sectionForField = (field) => {
    const entry = Object.entries(SECTION_FIELDS).find(([, fields]) => fields.includes(field));
    return entry ? entry[0] : 'photos';
};

const blank = () => ({
    title: '',
    description: '',
    property_type: 'house',
    status: 'available',
    bedrooms: '1',
    bathrooms: '1',
    building_size: '',
    land_size: '',
    floor_area: '',
    year_built: '',
    price: '',
    deposit: '',
    currency: 'USD',
    payment_terms: 'monthly',
    water_cost: '',
    electricity_cost: '',
    trash_cost: '',
    negotiable: false,
    furnished: false,
    entrance_type: '',
    bathroom_type: '',
    parking_type: 'none',
    families_allowed: '',
    distance_to_cbd: '',
    security_type: 'fenced',
    children_allowed: true,
    pets_allowed: false,
    smoking_allowed: false,
    parties_allowed: false,
    minimum_stay: '12',
    preferred_tenant: 'any',
    landlord_type: 'direct',
    contact_preference: 'platform',
    show_phone: false,
    landmark: '',
    suburb: '',
    zone: '',
    city: 'Harare',
    address: '',
    latitude: '',
    longitude: '',
    amenities: [],
    cover: null,
    cover_image: '',
    images: [],
    available_from: '',
});

const fromProperty = (property) => ({
    title: property.title || '',
    description: property.description || '',
    property_type: property.property_type || 'house',
    bedrooms: property.bedrooms ?? '1',
    bathrooms: property.bathrooms ?? '1',
    building_size: property.building_size ?? '',
    land_size: property.land_size ?? '',
    floor_area: property.floor_area ?? '',
    year_built: property.year_built ?? '',
    price: property.price ?? '',
    deposit: property.deposit ?? '',
    currency: property.currency || 'USD',
    payment_terms: property.payment_terms || 'monthly',
    water_cost: property.water_cost ?? '',
    electricity_cost: property.electricity_cost ?? '',
    trash_cost: property.trash_cost ?? '',
    negotiable: Boolean(property.negotiable),
    furnished: Boolean(property.furnished),
    entrance_type: property.entrance_type || '',
    bathroom_type: property.bathroom_type || '',
    parking_type: property.parking_type || 'none',
    families_allowed: property.families_allowed ?? '',
    distance_to_cbd: property.distance_to_cbd ?? '',
    security_type: property.security_type || 'fenced',
    children_allowed: Boolean(property.children_allowed),
    pets_allowed: Boolean(property.pets_allowed),
    smoking_allowed: Boolean(property.smoking_allowed),
    parties_allowed: Boolean(property.parties_allowed),
    minimum_stay: property.minimum_stay ?? '12',
    preferred_tenant: property.preferred_tenant || 'any',
    landlord_type: property.landlord_type || 'direct',
    contact_preference: property.contact_preference || 'platform',
    show_phone: Boolean(property.show_phone),
    landmark: property.landmark || '',
    suburb: property.suburb || '',
    zone: property.zone || '',
    city: property.city || 'Harare',
    address: property.address || '',
    latitude: property.latitude ?? '',
    longitude: property.longitude ?? '',
    amenities: property.amenities || [],
    cover: null,
    cover_image: property.cover_image || '',
    images: (property.images || []).map((image) => image.path),
    available_from: property.available_from || '',
});

function Field({ label, children }) {
    return (
        <div>
            <label className="field-label">{label}</label>
            {children}
        </div>
    );
}

function ErrorText({ error }) {
    if (!error) return null;
    return <p className="mt-1 text-xs font-medium text-rose-600">{error}</p>;
}

function Toggle({ checked, onChange, children, description }) {
    return (
        <label className="flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-muted/30 px-4 py-3">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) => onChange(e.target.checked)}
                className="mt-0.5 h-5 w-5 rounded-md border-border text-primary focus:ring-primary"
            />
            <span className="min-w-0">
                <span className="block text-sm font-semibold text-foreground">{children}</span>
                {description && <span className="mt-0.5 block text-xs text-muted-foreground">{description}</span>}
            </span>
        </label>
    );
}

// One collapsible section. The heading is a button that expands/collapses the
// body; `forceOpen` (an error inside) keeps it open, and an error dot flags it.
function Section({ index, section, open, forceOpen, hasError, onToggle, children }) {
    const expanded = open || forceOpen;
    return (
        <section className="surface overflow-hidden">
            <button
                type="button"
                aria-expanded={expanded}
                onClick={() => onToggle(section.id)}
                className="flex w-full items-center gap-3 px-5 py-4 text-left transition-colors hover:bg-muted/40"
            >
                <span className="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-primary/10 text-xs font-black text-primary">{index + 1}</span>
                <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-foreground">
                        {section.label}
                        {hasError && <span className="inline-block h-2 w-2 shrink-0 rounded-full bg-rose-500" aria-label="Has errors" />}
                    </span>
                    <span className="mt-0.5 block truncate text-xs text-muted-foreground">{section.hint}</span>
                </span>
                <ChevronDown className={cn('h-5 w-5 shrink-0 text-muted-foreground transition-transform', expanded && 'rotate-180')} />
            </button>
            {expanded && <div className="border-t border-border px-5 py-5">{children}</div>}
        </section>
    );
}

export default function PropertyForm({ mode = 'create', property = null, amenityOptions = [] }) {
    const { data, setData, post, transform, processing, errors } = useForm(mode === 'create' ? blank() : fromProperty(property));
    const errorMessages = [...new Set(Object.values(errors).filter(Boolean))];

    // Basics open by default; the rest collapsed.
    const [open, setOpen] = useState(() => new Set(['basics']));
    const toggle = (id) => setOpen((prev) => {
        const next = new Set(prev);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        return next;
    });

    // Which sections currently hold a validation error (so they stay open).
    const erroredSections = new Set(Object.keys(errors).map((field) => sectionForField(field.split('.')[0])));
    const sectionHasError = (id) => erroredSections.has(id);

    // On a failed submit, also add errored sections to the open set for good UX.
    const expandErrors = (failed) => {
        const ids = Object.keys(failed).map((field) => sectionForField(field.split('.')[0]));
        if (ids.length) setOpen((prev) => new Set([...prev, ...ids]));
    };

    const toggleAmenity = (key) => {
        setData('amenities', data.amenities.includes(key)
            ? data.amenities.filter((a) => a !== key)
            : [...data.amenities, key]);
    };

    // The uploader hands back the cover as a new File, the retained photo
    // path, or null once removed; the server keeps `cover_image` and stores `cover`.
    const changePhotos = ({ cover, images }) => {
        setData({
            ...data,
            cover: cover instanceof File ? cover : null,
            cover_image: typeof cover === 'string' ? cover : (cover instanceof File ? data.cover_image : ''),
            images,
        });
    };

    const submit = (e) => {
        e.preventDefault();
        if (mode === 'create') {
            post(route('owner.properties.store'), { onError: expandErrors });
        } else {
            // PHP only parses multipart bodies on POST, so photo uploads are
            // sent as POST with Laravel's method override.
            transform((payload) => ({ ...payload, _method: 'put' }));
            post(route('owner.properties.update', property.id), { forceFormData: true, preserveScroll: true, onError: expandErrors });
        }
    };

    const cancelHref = mode === 'create'
        ? route('owner.properties.index')
        : route('owner.properties.show', property.id);

    const sectionProps = (id, index) => ({
        index,
        section: SECTIONS[index],
        open: open.has(id),
        forceOpen: sectionHasError(id),
        hasError: sectionHasError(id),
        onToggle: toggle,
    });

    return (
        <form onSubmit={submit} className="space-y-4 pb-4">
            {errorMessages.length > 0 && (
                <div role="alert" className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p className="flex items-center gap-2 font-bold">
                        <AlertCircle className="h-4 w-4 shrink-0" /> Please fix the following before saving:
                    </p>
                    <ul className="mt-1.5 list-disc space-y-0.5 pl-10 font-medium">
                        {errorMessages.map((message) => <li key={message}>{message}</li>)}
                    </ul>
                </div>
            )}

            {/* Basics */}
            <Section {...sectionProps('basics', 0)}>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="md:col-span-2">
                        <Field label="Listing title">
                            <input type="text" className="field" placeholder="e.g. 2 Bedroom Apartment in Avondale" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
                            <ErrorText error={errors.title} />
                        </Field>
                    </div>
                    <Field label="Property type">
                        <select className="field" value={data.property_type} onChange={(e) => setData('property_type', e.target.value)}>
                            {Object.entries({
                                house: 'Full house',
                                flat: 'Flat',
                                apartment: 'Apartment',
                                townhouse: 'Townhouse',
                                cottage: 'Cottage',
                                room: 'Room',
                                commercial: 'Commercial',
                                land: 'Land',
                            }).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <ErrorText error={errors.property_type} />
                    </Field>
                    {mode === 'create' && (
                        <Field label="Status">
                            <select className="field" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                {[['available', 'Available'], ['reserved', 'Reserved'], ['occupied', 'Occupied'], ['unavailable', 'Unavailable']].map(([value, label]) => (
                                    <option key={value} value={value}>{label}</option>
                                ))}
                            </select>
                            <ErrorText error={errors.status} />
                        </Field>
                    )}
                    <Field label="Bedrooms">
                        <input type="number" min="0" max="50" className="field" value={data.bedrooms} onChange={(e) => setData('bedrooms', e.target.value)} />
                        <ErrorText error={errors.bedrooms} />
                    </Field>
                    <Field label="Bathrooms">
                        <input type="number" min="0" max="50" className="field" value={data.bathrooms} onChange={(e) => setData('bathrooms', e.target.value)} />
                        <ErrorText error={errors.bathrooms} />
                    </Field>
                    <Field label="Building size (m²)">
                        <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 180" value={data.building_size} onChange={(e) => setData('building_size', e.target.value)} />
                    </Field>
                    <Field label="Land size (m²)">
                        <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 1200" value={data.land_size} onChange={(e) => setData('land_size', e.target.value)} />
                    </Field>
                    <Field label="Year built">
                        <input type="number" min="1900" max="2100" className="field" placeholder="e.g. 2018" value={data.year_built} onChange={(e) => setData('year_built', e.target.value)} />
                        <ErrorText error={errors.year_built} />
                    </Field>
                    <div className="flex items-end pb-1">
                        <Toggle checked={data.furnished} onChange={(v) => setData('furnished', v)}>Furnished</Toggle>
                    </div>
                    <div className="md:col-span-2">
                        <Field label="Description">
                            <textarea className="field min-h-28" rows={4} placeholder="Describe the property, its condition, and what makes it special." value={data.description} onChange={(e) => setData('description', e.target.value)} />
                        </Field>
                    </div>
                </div>
            </Section>

            {/* Pricing */}
            <Section {...sectionProps('pricing', 1)}>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Field label="Currency">
                        <select className="field" value={data.currency} onChange={(e) => setData('currency', e.target.value)}>
                            {CURRENCIES.map((c) => <option key={c} value={c}>{c}</option>)}
                        </select>
                        <ErrorText error={errors.currency} />
                    </Field>
                    <Field label="Payment terms">
                        <select className="field" value={data.payment_terms} onChange={(e) => setData('payment_terms', e.target.value)}>
                            {Object.entries(PAYMENT_TERM_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <ErrorText error={errors.payment_terms} />
                    </Field>
                    <Field label={data.currency === 'ZWL' ? 'Rent (ZWL)' : 'Rent (USD)'}>
                        <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 850" value={data.price} onChange={(e) => setData('price', e.target.value)} required />
                        <ErrorText error={errors.price} />
                    </Field>
                    <Field label="Refundable deposit">
                        <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 850" value={data.deposit} onChange={(e) => setData('deposit', e.target.value)} />
                        <ErrorText error={errors.deposit} />
                    </Field>
                    <Field label="Water (monthly)">
                        <input type="number" min="0" step="0.01" className="field" placeholder="Optional" value={data.water_cost} onChange={(e) => setData('water_cost', e.target.value)} />
                    </Field>
                    <Field label="Electricity (monthly)">
                        <input type="number" min="0" step="0.01" className="field" placeholder="Optional" value={data.electricity_cost} onChange={(e) => setData('electricity_cost', e.target.value)} />
                    </Field>
                    <Field label="Refuse (monthly)">
                        <input type="number" min="0" step="0.01" className="field" placeholder="Optional" value={data.trash_cost} onChange={(e) => setData('trash_cost', e.target.value)} />
                    </Field>
                    <div className="flex items-end pb-1">
                        <Toggle checked={data.negotiable} onChange={(v) => setData('negotiable', v)}>Rent is negotiable</Toggle>
                    </div>
                </div>
            </Section>

            {/* Rental details */}
            <Section {...sectionProps('details', 2)}>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Field label="Entrance">
                        <select className="field" value={data.entrance_type} onChange={(e) => setData('entrance_type', e.target.value)}>
                            <option value="">Select…</option>
                            {Object.entries(ENTRANCE_TYPES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                    <Field label="Bathroom">
                        <select className="field" value={data.bathroom_type} onChange={(e) => setData('bathroom_type', e.target.value)}>
                            <option value="">Select…</option>
                            {Object.entries(BATHROOM_TYPES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                    </Field>
                    <Field label="Parking">
                        <select className="field" value={data.parking_type} onChange={(e) => setData('parking_type', e.target.value)}>
                            {Object.entries(PARKING_TYPES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <ErrorText error={errors.parking_type} />
                    </Field>
                    <Field label="Security">
                        <select className="field" value={data.security_type} onChange={(e) => setData('security_type', e.target.value)}>
                            {Object.entries(SECURITY_TYPES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <ErrorText error={errors.security_type} />
                    </Field>
                    <Field label="Distance to CBD (km)">
                        <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 6.5" value={data.distance_to_cbd} onChange={(e) => setData('distance_to_cbd', e.target.value)} />
                    </Field>
                    <Field label="Minimum stay (months)">
                        <input type="number" min="0" max="120" className="field" value={data.minimum_stay} onChange={(e) => setData('minimum_stay', e.target.value)} />
                        <ErrorText error={errors.minimum_stay} />
                    </Field>
                    <Field label="Preferred tenant">
                        <select className="field" value={data.preferred_tenant} onChange={(e) => setData('preferred_tenant', e.target.value)}>
                            {Object.entries(PREFERRED_TENANTS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <ErrorText error={errors.preferred_tenant} />
                    </Field>
                    <Field label="Landlord type">
                        <select className="field" value={data.landlord_type} onChange={(e) => setData('landlord_type', e.target.value)}>
                            {Object.entries(LANDLORD_TYPES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <ErrorText error={errors.landlord_type} />
                    </Field>
                    <Field label="Contact preference">
                        <select className="field" value={data.contact_preference} onChange={(e) => setData('contact_preference', e.target.value)}>
                            {Object.entries(CONTACT_PREFERENCES).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <ErrorText error={errors.contact_preference} />
                    </Field>
                    <Field label="Local landmark">
                        <input type="text" className="field" placeholder="e.g. Near Avondale Shopping Centre" value={data.landmark} onChange={(e) => setData('landmark', e.target.value)} />
                    </Field>
                </div>
                <div className="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Toggle
                        checked={data.children_allowed}
                        onChange={(v) => setData('children_allowed', v)}
                        description="Families with children can rent."
                    >Children allowed</Toggle>
                    <Toggle
                        checked={data.pets_allowed}
                        onChange={(v) => setData('pets_allowed', v)}
                        description="Tenants may keep pets."
                    >Pets allowed</Toggle>
                    <Toggle
                        checked={data.smoking_allowed}
                        onChange={(v) => setData('smoking_allowed', v)}
                    >Smoking allowed</Toggle>
                    <Toggle
                        checked={data.parties_allowed}
                        onChange={(v) => setData('parties_allowed', v)}
                    >Parties allowed</Toggle>
                    <Toggle
                        checked={data.show_phone}
                        onChange={(v) => setData('show_phone', v)}
                        description="Display your phone number publicly on the listing."
                    >Show my phone number</Toggle>
                </div>
            </Section>

            {/* Location — suburb/zone/city/address plus the required map picker. */}
            <Section {...sectionProps('location', 3)}>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <Field label="Suburb">
                        <input type="text" className="field" placeholder="e.g. Avondale" value={data.suburb} onChange={(e) => setData('suburb', e.target.value)} />
                        <ErrorText error={errors.suburb} />
                    </Field>
                    <Field label="Zone">
                        <input type="text" className="field" placeholder="e.g. Harare North" value={data.zone} onChange={(e) => setData('zone', e.target.value)} />
                        <ErrorText error={errors.zone} />
                    </Field>
                    <Field label="City">
                        <input type="text" className="field" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                        <ErrorText error={errors.city} />
                    </Field>
                    <Field label="Available from">
                        <input type="date" className="field" value={data.available_from} onChange={(e) => setData('available_from', e.target.value)} />
                        <ErrorText error={errors.available_from} />
                    </Field>
                    <div className="md:col-span-2">
                        <Field label="Address">
                            <input type="text" className="field" placeholder="Street number and name (optional but encouraged)" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                            <ErrorText error={errors.address} />
                        </Field>
                    </div>
                    <div className="md:col-span-2">
                        <label className="field-label">Exact location on the map <span className="text-rose-600">*</span></label>
                        <LocationPicker
                            latitude={data.latitude}
                            longitude={data.longitude}
                            onChange={({ latitude, longitude }) => setData({ ...data, latitude, longitude })}
                            error={errors.latitude || errors.longitude}
                        />
                    </div>
                </div>
            </Section>

            {/* Amenities — its own collapsible section */}
            <Section {...sectionProps('amenities', 4)}>
                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                    {amenityOptions.map(({ key, label }) => {
                        const active = data.amenities.includes(key);
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => toggleAmenity(key)}
                                className={cn(
                                    'rounded-xl border px-3 py-2.5 text-left text-xs font-semibold transition-colors',
                                    active
                                        ? 'border-primary bg-primary/10 text-primary'
                                        : 'border-border bg-muted/50 text-muted-foreground hover:border-primary/40'
                                )}
                            >
                                {label}
                            </button>
                        );
                    })}
                </div>
            </Section>

            {/* Photos */}
            <Section {...sectionProps('photos', 5)}>
                <PhotoUploader
                    cover={data.cover || data.cover_image || null}
                    images={data.images}
                    onChange={changePhotos}
                    error={errors.media}
                />
            </Section>

            <div className="flex items-center justify-between gap-3">
                <Link href={cancelHref} className={cn(buttonVariants({ variant: 'outline' }))}>Cancel</Link>
                <Button type="submit" disabled={processing}>
                    <Save className="h-4 w-4" /> {processing ? 'Saving…' : mode === 'create' ? 'Publish Listing' : 'Save Changes'}
                </Button>
            </div>
        </form>
    );
}
