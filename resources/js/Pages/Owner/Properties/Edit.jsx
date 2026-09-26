import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyForm from './PropertyForm';

export default function PropertyEdit({ auth, property, amenityOptions }) {
    return (
        <MainLayout title="Edit Property" auth={auth}>
            <Head title={`Edit ${property.title}`} />

            <div className="mx-auto max-w-5xl">
                <div className="mb-6">
                    <Link href={route('owner.properties.show', property.id)} className="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80">
                        <ArrowLeft className="h-4 w-4" /> Back to Property
                    </Link>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Edit Property</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Update the details and photos of your listing — keep at least one photo. To change its status or renew it, use the buttons on the property page.</p>
                </div>

                <PropertyForm mode="edit" property={property} amenityOptions={amenityOptions} />
            </div>
        </MainLayout>
    );
}