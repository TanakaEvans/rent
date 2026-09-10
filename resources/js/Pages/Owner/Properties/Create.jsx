import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Save, ImagePlus } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import { Button, buttonVariants } from '@/Components/ui/button';
import { cn } from '@/lib/utils';

const typeOptions = [
    ['house', 'House'],
    ['flat', 'Flat / Apartment'],
    ['townhouse', 'Townhouse'],
    ['cottage', 'Cottage'],
    ['room', 'Room'],
    ['commercial', 'Commercial'],
    ['land', 'Land'],
];

const statusOptions = [
    ['available', 'Available'],
    ['reserved', 'Reserved'],
    ['occupied', 'Occupied'],
    ['unavailable', 'Unavailable'],
];

const amenityOptions = [
    ['borehole', 'Borehole'],
    ['generator', 'Generator'],
    ['solar', 'Solar Power'],
    ['water_tank', 'Water Tank'],
    ['security', 'Security'],
    ['staff_quarters', 'Staff Quarters'],
    ['swimming_pool', 'Swimming Pool'],
    ['carport', 'Carport'],
    ['double_garage', 'Double Garage'],
    ['garden', 'Garden'],
    ['aircon', 'Air Conditioning'],
    ['fitted_kitchen', 'Fitted Kitchen'],
];

export default function PropertyCreate({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        property_type: 'house',
        bedrooms: '1',
        bathrooms: '1',
        building_size: '',
        land_size: '',
        price: '',
        deposit: '',
        furnished: false,
        status: 'available',
        suburb: '',
        zone: '',
        city: 'Harare',
        address: '',
        amenities: [],
        cover_image: '',
        available_from: '',
    });

    const toggleAmenity = (key) => {
        const next = data.amenities.includes(key)
            ? data.amenities.filter((a) => a !== key)
            : [...data.amenities, key];
        setData('amenities', next);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('owner.properties.store'));
    };

    return (
        <MainLayout title="List a New Property" auth={auth}>
            <Head title="List a New Property" />

            <div className="mx-auto max-w-4xl">
                <div className="mb-6">
                    <Link href={route('owner.properties.index')} className="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80">
                        <ArrowLeft className="h-4 w-4" /> Back to My Properties
                    </Link>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">List a New Property</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Fill in the details below. Your listing goes live once published.</p>
                </div>

                <form onSubmit={submit} className="space-y-7">
                    <section className="surface p-6">
                        <h3 className="mb-4 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Details</h3>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="md:col-span-2">
                                <label className="field-label">Title</label>
                                <input type="text" className="field" placeholder="e.g. 3 Bedroom House in Borrowdale" required value={data.title} onChange={(e) => setData('title', e.target.value)} />
                                {errors.title && <p className="mt-1 text-xs font-medium text-rose-600">{errors.title}</p>}
                            </div>

                            <div className="md:col-span-2">
                                <label className="field-label">Description</label>
                                <textarea className="field min-h-28" rows={4} placeholder="Describe the property, condition, and what makes it special." value={data.description} onChange={(e) => setData('description', e.target.value)} />
                            </div>

                            <div>
                                <label className="field-label">Property Type</label>
                                <select className="field" value={data.property_type} onChange={(e) => setData('property_type', e.target.value)}>
                                    {typeOptions.map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                                {errors.property_type && <p className="mt-1 text-xs font-medium text-rose-600">{errors.property_type}</p>}
                            </div>

                            <div>
                                <label className="field-label">Status</label>
                                <select className="field" value={data.status} onChange={(e) => setData('status', e.target.value)}>
                                    {statusOptions.map(([value, label]) => (
                                        <option key={value} value={value}>{label}</option>
                                    ))}
                                </select>
                                {errors.status && <p className="mt-1 text-xs font-medium text-rose-600">{errors.status}</p>}
                            </div>

                            <div>
                                <label className="field-label">Bedrooms</label>
                                <input type="number" min="0" max="50" className="field" value={data.bedrooms} onChange={(e) => setData('bedrooms', e.target.value)} />
                            </div>

                            <div>
                                <label className="field-label">Bathrooms</label>
                                <input type="number" min="0" max="50" className="field" value={data.bathrooms} onChange={(e) => setData('bathrooms', e.target.value)} />
                            </div>

                            <div>
                                <label className="field-label">Building Size (m²)</label>
                                <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 180" value={data.building_size} onChange={(e) => setData('building_size', e.target.value)} />
                            </div>

                            <div>
                                <label className="field-label">Land Size (m² or acres)</label>
                                <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 1200" value={data.land_size} onChange={(e) => setData('land_size', e.target.value)} />
                            </div>

                            <div>
                                <label className="field-label">Monthly Rent (USD)</label>
                                <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 850" value={data.price} onChange={(e) => setData('price', e.target.value)} />
                                {errors.price && <p className="mt-1 text-xs font-medium text-rose-600">{errors.price}</p>}
                            </div>

                            <div>
                                <label className="field-label">Deposit (USD)</label>
                                <input type="number" min="0" step="0.01" className="field" placeholder="e.g. 850" value={data.deposit} onChange={(e) => setData('deposit', e.target.value)} />
                            </div>

                            <div>
                                <label className="field-label">Available From</label>
                                <input type="date" className="field" value={data.available_from} onChange={(e) => setData('available_from', e.target.value)} />
                            </div>

                            <div className="flex items-end pb-1">
                                <label className="flex items-center gap-3">
                                    <input
                                        type="checkbox"
                                        checked={data.furnished}
                                        onChange={(e) => setData('furnished', e.target.checked)}
                                        className="h-5 w-5 rounded-md border-border text-primary focus:ring-primary"
                                    />
                                    <span className="text-sm font-semibold text-foreground">Furnished</span>
                                </label>
                            </div>
                        </div>
                    </section>

                    <section className="surface p-6">
                        <h3 className="mb-4 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Location</h3>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label className="field-label">Suburb</label>
                                <input type="text" className="field" placeholder="e.g. Borrowdale" value={data.suburb} onChange={(e) => setData('suburb', e.target.value)} />
                            </div>
                            <div>
                                <label className="field-label">Zone</label>
                                <input type="text" className="field" placeholder="e.g. Harare North" value={data.zone} onChange={(e) => setData('zone', e.target.value)} />
                            </div>
                            <div>
                                <label className="field-label">City</label>
                                <input type="text" className="field" value={data.city} onChange={(e) => setData('city', e.target.value)} />
                            </div>
                            <div className="md:col-span-2">
                                <label className="field-label">Address</label>
                                <input type="text" className="field" placeholder="Street number and name" value={data.address} onChange={(e) => setData('address', e.target.value)} />
                            </div>
                        </div>
                    </section>

                    <section className="surface p-6">
                        <h3 className="mb-4 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Amenities</h3>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4">
                            {amenityOptions.map(([key, label]) => {
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
                    </section>

                    <section className="surface p-6">
                        <h3 className="mb-4 text-sm font-extrabold uppercase tracking-wider text-muted-foreground">Cover Image</h3>
                        <div>
                            <label className="field-label">Image Path or URL</label>
                            <div className="flex items-center gap-2">
                                <input type="text" className="field" placeholder="e.g. /uploads/properties/cover.jpg" value={data.cover_image} onChange={(e) => setData('cover_image', e.target.value)} />
                                <span className="shrink-0 rounded-xl bg-muted p-2.5 text-muted-foreground"><ImagePlus className="h-5 w-5" /></span>
                            </div>
                        </div>
                    </section>

                    <div className="flex items-center justify-end gap-3 pb-4">
                        <Link href={route('owner.properties.index')} className={cn(buttonVariants({ variant: 'outline' }))}>Cancel</Link>
                        <Button type="submit" disabled={processing}>
                            <Save className="h-4 w-4" /> {processing ? 'Saving...' : 'Create Property'}
                        </Button>
                    </div>
                </form>
            </div>
        </MainLayout>
    );
}